<?php

namespace App\Services;

use App\Models\PaymeTransaction;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymePaymentService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TicketService $ticketService,
        private readonly BotNotificationService $notificationService,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  Payment link                                                       */
    /* ------------------------------------------------------------------ */

    public function generatePaymentLink(Registration $registration): string
    {
        $registration->loadMissing('olympiad');
        $amount = (int) $registration->olympiad->price * 100;
        $merchantId = config('services.payme.merchant_id');
        $baseUrl = config('services.payme.checkout_url', 'https://checkout.paycom.uz');

        $params = base64_encode("m={$merchantId};ac.user_id={$registration->id};a={$amount}");

        return rtrim($baseUrl, '/') . '/' . $params;
    }

    /* ------------------------------------------------------------------ */
    /*  JSON-RPC router                                                    */
    /* ------------------------------------------------------------------ */

    public function handleRequest(Request $request): array
    {
        Log::info('Payme request', $request->all());

        $body = $request->all();
        $id = $body['id'] ?? null;

        if (! $this->authenticate($request)) {
            return $this->rpcError($id, -32504, 'Unauthorized');
        }

        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];

        $result = match ($method) {
            'CheckPerformTransaction' => $this->checkPerformTransaction($params),
            'CreateTransaction'       => $this->createTransaction($params),
            'PerformTransaction'      => $this->performTransaction($params),
            'CancelTransaction'       => $this->cancelTransaction($params),
            'CheckTransaction'        => $this->checkTransaction($params),
            default                   => ['error' => $this->error(-32601, 'Method not found')],
        };

        if (isset($result['error'])) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $result['error']];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /* ------------------------------------------------------------------ */
    /*  CheckPerformTransaction                                            */
    /* ------------------------------------------------------------------ */

    private function checkPerformTransaction(array $params): array
    {
        $registration = $this->findRegistration($params);
        if ($registration === null) {
            return ['error' => $this->error(-31050, 'Order not found', 'user_id')];
        }

        $amount = (int) ($params['amount'] ?? 0);
        $expected = (int) $registration->olympiad->price * 100;

        if ($amount !== $expected) {
            return ['error' => $this->error(-31001, 'Incorrect amount', 'amount')];
        }

        if ($registration->payment_status === 'paid') {
            return ['error' => $this->error(-31099, 'Order already paid', 'user_id')];
        }

        return ['allow' => true];
    }

    /* ------------------------------------------------------------------ */
    /*  CreateTransaction                                                  */
    /* ------------------------------------------------------------------ */

    private function createTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $time    = (int) ($params['time'] ?? 0);
        $amount  = (int) ($params['amount'] ?? 0);
        $account = $params['account'] ?? [];

        /* --- Existing transaction with same payme_id ------------------- */
        $existing = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($existing !== null) {
            if ($existing->state !== PaymeTransaction::STATE_CREATED) {
                return ['error' => $this->error(-31008, 'Unable to complete', 'transaction')];
            }
            if ($existing->isExpired()) {
                $this->cancelTx($existing, 4);
                return ['error' => $this->error(-31008, 'Transaction expired', 'transaction')];
            }
            return $this->createResponse($existing);
        }

        /* --- Validate order ------------------------------------------- */
        $registration = $this->findRegistration($params);
        if ($registration === null) {
            return ['error' => $this->error(-31050, 'Order not found', 'user_id')];
        }

        $expected = (int) $registration->olympiad->price * 100;
        if ($amount !== $expected) {
            return ['error' => $this->error(-31001, 'Incorrect amount', 'amount')];
        }

        if ($registration->payment_status === 'paid') {
            return ['error' => $this->error(-31099, 'Order already paid', 'user_id')];
        }

        /* --- Cancel any other pending transaction for this order ------- */
        $activeTx = PaymeTransaction::where('state', PaymeTransaction::STATE_CREATED)
            ->whereHas('payment', fn ($q) => $q->where('registration_id', $registration->id))
            ->first();

        if ($activeTx !== null) {
            $this->cancelTx($activeTx, 4);
        }

        /* --- Create new transaction ----------------------------------- */
        return DB::transaction(function () use ($registration, $paymeId, $time, $amount, $account) {
            $payment = $this->paymentService->createForRegistration($registration);
            $this->paymentService->setSystem($registration, 'payme');

            $tx = PaymeTransaction::create([
                'payment_id' => $payment->id,
                'payme_id'   => $paymeId,
                'state'      => PaymeTransaction::STATE_CREATED,
                'amount'     => $amount,
                'time'       => $time,
                'account'    => $account,
            ]);

            return $this->createResponse($tx);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  PerformTransaction                                                 */
    /* ------------------------------------------------------------------ */

    private function performTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return ['error' => $this->error(-31003, 'Transaction not found', 'transaction')];
        }

        if ($tx->state === PaymeTransaction::STATE_COMPLETED) {
            return $this->performResponse($tx);
        }

        if ($tx->state !== PaymeTransaction::STATE_CREATED) {
            return ['error' => $this->error(-31008, 'Unable to complete', 'transaction')];
        }

        if ($tx->isExpired()) {
            $this->cancelTx($tx, 4);
            return ['error' => $this->error(-31008, 'Transaction expired', 'transaction')];
        }

        return DB::transaction(function () use ($tx) {
            $performTime = now()->getTimestampMs();

            $tx->forceFill([
                'state'        => PaymeTransaction::STATE_COMPLETED,
                'perform_time' => $performTime,
            ])->save();

            $payment = $tx->payment;
            $payment->forceFill([
                'status'         => 'success',
                'transaction_id' => $tx->payme_id,
                'paid_at'        => now(),
            ])->save();

            $registration = $payment->registration;
            $registration->forceFill(['payment_status' => 'paid'])->save();

            try {
                $this->ticketService->createTicket($registration->id);
                $this->notificationService->sendPaymentSuccess($registration->fresh(['user', 'olympiad']));
            } catch (\Throwable $e) {
                Log::error('Post-payment failed (Payme)', ['error' => $e->getMessage()]);
            }

            return $this->performResponse($tx);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  CancelTransaction                                                  */
    /* ------------------------------------------------------------------ */

    private function cancelTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $reason  = (int) ($params['reason'] ?? 1);

        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return ['error' => $this->error(-31003, 'Transaction not found', 'transaction')];
        }

        if ($tx->isCancelled()) {
            return $this->cancelResponse($tx);
        }

        $registration = null;

        $response = DB::transaction(function () use ($tx, $reason, &$registration) {
            $newState = $tx->state === PaymeTransaction::STATE_COMPLETED
                ? PaymeTransaction::STATE_CANCELLED_AFTER_COMPLETE
                : PaymeTransaction::STATE_CANCELLED;

            $tx->forceFill([
                'state'       => $newState,
                'cancel_time' => now()->getTimestampMs(),
                'reason'      => $reason,
            ])->save();

            $payment = $tx->payment;
            if ($payment !== null) {
                $payment->forceFill(['status' => 'failed'])->save();
                $registration = $payment->registration;
            }

            return $this->cancelResponse($tx);
        });

        // After DB commit: notify user and delete registration
        if ($registration !== null) {
            $this->cancelRegistration($registration);
        } else {
            Log::warning('Payme CancelTransaction: registration not found for tx', ['payme_id' => $tx->payme_id]);
        }

        return $response;
    }

    /* ------------------------------------------------------------------ */
    /*  CheckTransaction                                                   */
    /* ------------------------------------------------------------------ */

    private function checkTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return ['error' => $this->error(-31003, 'Transaction not found', 'transaction')];
        }

        return [
            'create_time'  => (int) $tx->time,
            'perform_time' => $tx->perform_time ? (int) $tx->perform_time : 0,
            'cancel_time'  => $tx->cancel_time ? (int) $tx->cancel_time : 0,
            'transaction'  => (string) $tx->id,
            'state'        => (int) $tx->state,
            'reason'       => $tx->reason !== null ? (int) $tx->reason : null,
        ];
    }

    /* ================================================================== */
    /*  Response builders                                                  */
    /* ================================================================== */

    private function createResponse(PaymeTransaction $tx): array
    {
        return [
            'create_time' => (int) $tx->time,
            'transaction' => (string) $tx->id,
            'state'       => (int) $tx->state,
        ];
    }

    private function performResponse(PaymeTransaction $tx): array
    {
        return [
            'transaction'  => (string) $tx->id,
            'perform_time' => $tx->perform_time ? (int) $tx->perform_time : 0,
            'state'        => (int) $tx->state,
        ];
    }

    private function cancelResponse(PaymeTransaction $tx): array
    {
        return [
            'transaction' => (string) $tx->id,
            'cancel_time' => $tx->cancel_time ? (int) $tx->cancel_time : 0,
            'state'       => (int) $tx->state,
        ];
    }

    /* ================================================================== */
    /*  Helpers                                                            */
    /* ================================================================== */

    private function cancelTx(PaymeTransaction $tx, int $reason): void
    {
        $newState = $tx->state === PaymeTransaction::STATE_COMPLETED
            ? PaymeTransaction::STATE_CANCELLED_AFTER_COMPLETE
            : PaymeTransaction::STATE_CANCELLED;

        $tx->forceFill([
            'state'       => $newState,
            'cancel_time' => now()->getTimestampMs(),
            'reason'      => $reason,
        ])->save();
    }

    private function findRegistration(array $params): ?Registration
    {
        $id = $params['account']['user_id'] ?? null;

        return $id ? Registration::with('olympiad')->find($id) : null;
    }

    private function authenticate(Request $request): bool
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6));
        $parts = explode(':', $decoded, 2);

        if (count($parts) !== 2) {
            return false;
        }

        return $parts[0] === 'Paycom' && $parts[1] === config('services.payme.key');
    }

    private function error(int $code, string $message, string $data = ''): array
    {
        return [
            'code'    => $code,
            'message' => ['ru' => $message, 'uz' => $message, 'en' => $message],
            'data'    => $data,
        ];
    }

    private function rpcError(?int $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $this->error($code, $message),
        ];
    }

    private function cancelRegistration(\App\Models\Registration $registration): void
    {
        $registration->loadMissing(['user', 'olympiad']);

        Log::info('Payme: cancelling registration', [
            'registration_id' => $registration->id,
            'has_user'        => $registration->user !== null,
            'telegram_id'     => $registration->user?->telegram_id,
        ]);

        try {
            $this->notificationService->sendPaymentCancelled($registration);
        } catch (\Throwable $e) {
            Log::warning('Payme: failed to send cancellation notification', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }

        try {
            DB::transaction(function () use ($registration) {
                $registration->ticket()->delete();
                $registration->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Payme: failed to delete cancelled registration', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
