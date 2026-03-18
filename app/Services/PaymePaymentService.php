<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymeTransaction;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymePaymentService
{
    private const TIMEOUT_MS = 43_200_000; // 12 hours

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TicketService $ticketService,
        private readonly BotNotificationService $notificationService,
    ) {}

    public function generatePaymentLink(Registration $registration): string
    {
        $registration->loadMissing('olympiad');
        $amount = (int) $registration->olympiad->price * 100; // tiyin
        $merchantId = config('services.payme.merchant_id');
        $baseUrl = config('services.payme.checkout_url', 'https://checkout.paycom.uz');

        $params = base64_encode("m={$merchantId};ac.registration_id={$registration->id};a={$amount}");

        return rtrim($baseUrl, '/') . '/' . $params;
    }

    public function handleRequest(Request $request): array
    {
        Log::info('Payme request received', $request->all());

        if (! $this->authenticate($request)) {
            return $this->errorResponse(-32504, 'Unauthorized', 'auth');
        }

        $body = $request->all();
        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];
        $id = $body['id'] ?? null;

        $result = match ($method) {
            'CheckPerformTransaction' => $this->checkPerformTransaction($params),
            'CreateTransaction' => $this->createTransaction($params),
            'PerformTransaction' => $this->performTransaction($params),
            'CancelTransaction' => $this->cancelTransaction($params),
            'CheckTransaction' => $this->checkTransaction($params),
            default => $this->errorResponse(-32601, 'Method not found', 'method'),
        };

        if (isset($result['error'])) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $result['error']];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function checkPerformTransaction(array $params): array
    {
        $registrationId = $params['account']['registration_id'] ?? null;
        $amount = $params['amount'] ?? 0;

        $registration = Registration::with('olympiad')->find($registrationId);

        if ($registration === null) {
            return $this->errorResponse(-31050, 'Registration not found', 'registration_id');
        }

        $expectedAmount = (int) $registration->olympiad->price * 100;

        if ((int) $amount !== $expectedAmount) {
            return $this->errorResponse(-31001, 'Incorrect amount', 'amount');
        }

        if ($registration->payment_status === 'paid') {
            return $this->errorResponse(-31008, 'Order already paid', 'order');
        }

        return ['allow' => true];
    }

    private function createTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $time = $params['time'] ?? 0;
        $amount = $params['amount'] ?? 0;
        $account = $params['account'] ?? [];
        $registrationId = $account['registration_id'] ?? null;

        $registration = Registration::with('olympiad', 'payment')->find($registrationId);

        if ($registration === null) {
            return $this->errorResponse(-31050, 'Registration not found', 'registration_id');
        }

        $expectedAmount = (int) $registration->olympiad->price * 100;

        if ((int) $amount !== $expectedAmount) {
            return $this->errorResponse(-31001, 'Incorrect amount', 'amount');
        }

        $existingTx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($existingTx !== null) {
            if ($existingTx->state !== PaymeTransaction::STATE_CREATED) {
                return $this->errorResponse(-31008, 'Unable to perform operation', 'transaction');
            }

            if ($this->isExpired($existingTx)) {
                $existingTx->forceFill([
                    'state' => PaymeTransaction::STATE_CANCELLED,
                ])->save();
                return $this->errorResponse(-31008, 'Transaction expired', 'transaction');
            }

            return [
                'create_time' => $existingTx->created_at->getTimestampMs(),
                'transaction' => (string) $existingTx->id,
                'state' => $existingTx->state,
            ];
        }

        if ($registration->payment_status === 'paid') {
            return $this->errorResponse(-31008, 'Order already paid', 'order');
        }

        $activeTx = PaymeTransaction::where('state', PaymeTransaction::STATE_CREATED)
            ->whereHas('payment', fn ($q) => $q->where('registration_id', $registrationId))
            ->first();

        if ($activeTx !== null && $activeTx->payme_id !== $paymeId) {
            if ($this->isExpired($activeTx)) {
                $activeTx->forceFill(['state' => PaymeTransaction::STATE_CANCELLED])->save();
            } else {
                return $this->errorResponse(-31008, 'Another active transaction exists', 'transaction');
            }
        }

        return DB::transaction(function () use ($registration, $paymeId, $time, $amount, $account) {
            $payment = $this->paymentService->createForRegistration($registration);
            $this->paymentService->setSystem($registration, 'payme');

            $tx = PaymeTransaction::create([
                'payment_id' => $payment->id,
                'payme_id' => $paymeId,
                'state' => PaymeTransaction::STATE_CREATED,
                'amount' => $amount,
                'time' => $time,
                'account' => $account,
            ]);

            return [
                'create_time' => $tx->created_at->getTimestampMs(),
                'transaction' => (string) $tx->id,
                'state' => $tx->state,
            ];
        });
    }

    private function performTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;

        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return $this->errorResponse(-31003, 'Transaction not found', 'transaction');
        }

        if ($tx->state === PaymeTransaction::STATE_COMPLETED) {
            return [
                'transaction' => (string) $tx->id,
                'perform_time' => $tx->updated_at->getTimestampMs(),
                'state' => $tx->state,
            ];
        }

        if ($tx->state !== PaymeTransaction::STATE_CREATED) {
            return $this->errorResponse(-31008, 'Unable to perform operation', 'transaction');
        }

        if ($this->isExpired($tx)) {
            $tx->forceFill(['state' => PaymeTransaction::STATE_CANCELLED])->save();
            return $this->errorResponse(-31008, 'Transaction expired', 'transaction');
        }

        return DB::transaction(function () use ($tx) {
            $tx->forceFill(['state' => PaymeTransaction::STATE_COMPLETED])->save();

            $payment = $tx->payment;
            $payment->forceFill([
                'status' => 'success',
                'transaction_id' => $tx->payme_id,
                'paid_at' => now(),
            ])->save();

            $registration = $payment->registration;
            $registration->forceFill(['payment_status' => 'paid'])->save();

            try {
                $this->ticketService->createTicket($registration->id);
                $this->notificationService->sendPaymentSuccess($registration->fresh(['user', 'olympiad']));
            } catch (\Throwable $e) {
                Log::error('Post-payment processing failed (Payme)', ['error' => $e->getMessage()]);
            }

            return [
                'transaction' => (string) $tx->id,
                'perform_time' => $tx->updated_at->getTimestampMs(),
                'state' => $tx->state,
            ];
        });
    }

    private function cancelTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;
        $reason = $params['reason'] ?? null;

        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return $this->errorResponse(-31003, 'Transaction not found', 'transaction');
        }

        if ($tx->state === PaymeTransaction::STATE_COMPLETED) {
            // -31007: order fulfilled, cannot cancel
            return $this->errorResponse(-31007, 'Unable to cancel. Order fulfilled.', 'transaction');
        }

        if (in_array($tx->state, [PaymeTransaction::STATE_CANCELLED, PaymeTransaction::STATE_CANCELLED_AFTER_COMPLETE], true)) {
            return [
                'transaction' => (string) $tx->id,
                'cancel_time' => $tx->updated_at->getTimestampMs(),
                'state' => $tx->state,
            ];
        }

        return DB::transaction(function () use ($tx, $reason) {
            $newState = $tx->state === PaymeTransaction::STATE_COMPLETED
                ? PaymeTransaction::STATE_CANCELLED_AFTER_COMPLETE
                : PaymeTransaction::STATE_CANCELLED;

            $tx->forceFill(['state' => $newState])->save();

            $payment = $tx->payment;
            $payment->forceFill(['status' => 'failed'])->save();
            $payment->registration->forceFill(['payment_status' => 'failed'])->save();

            return [
                'transaction' => (string) $tx->id,
                'cancel_time' => $tx->updated_at->getTimestampMs(),
                'state' => $tx->state,
            ];
        });
    }

    private function checkTransaction(array $params): array
    {
        $paymeId = $params['id'] ?? null;

        $tx = PaymeTransaction::where('payme_id', $paymeId)->first();

        if ($tx === null) {
            return $this->errorResponse(-31003, 'Transaction not found', 'transaction');
        }

        return [
            'create_time' => $tx->created_at->getTimestampMs(),
            'perform_time' => $tx->state === PaymeTransaction::STATE_COMPLETED ? $tx->updated_at->getTimestampMs() : 0,
            'cancel_time' => in_array($tx->state, [PaymeTransaction::STATE_CANCELLED, PaymeTransaction::STATE_CANCELLED_AFTER_COMPLETE], true) ? $tx->updated_at->getTimestampMs() : 0,
            'transaction' => (string) $tx->id,
            'state' => $tx->state,
            'reason' => null,
        ];
    }

    private function authenticate(Request $request): bool
    {
        $authHeader = $request->header('Authorization', '');

        if (! str_starts_with($authHeader, 'Basic ')) {
            return false;
        }

        $decoded = base64_decode(substr($authHeader, 6));
        $parts = explode(':', $decoded, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $login = $parts[0];
        $password = $parts[1];

        return $login === 'Paycom' && $password === config('services.payme.key');
    }

    private function isExpired(PaymeTransaction $tx): bool
    {
        return $tx->created_at->diffInMilliseconds(now()) > self::TIMEOUT_MS;
    }

    private function errorResponse(int $code, string $message, string $data = ''): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => [
                    'ru' => $message,
                    'uz' => $message,
                    'en' => $message,
                ],
                'data' => $data,
            ],
        ];
    }
}
