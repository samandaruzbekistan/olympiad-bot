<?php

namespace App\Services;

use App\Models\ClickTransaction;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClickPaymentService
{
    public const ERROR_SUCCESS = 0;
    public const ERROR_SIGN_CHECK_FAILED = -1;
    public const ERROR_INCORRECT_AMOUNT = -2;
    public const ERROR_ACTION_NOT_FOUND = -3;
    public const ERROR_ALREADY_PAID = -4;
    public const ERROR_USER_NOT_EXIST = -5;
    public const ERROR_TRANSACTION_NOT_EXIST = -6;
    public const ERROR_UPDATE_FAILED = -7;
    public const ERROR_REQUEST_FROM_CLICK = -8;
    public const ERROR_TRANSACTION_CANCELLED = -9;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TicketService $ticketService,
        private readonly BotNotificationService $notificationService,
    ) {}

    public function generatePaymentLink(Registration $registration): string
    {
        $registration->loadMissing('olympiad');
        $amount = (int) $registration->olympiad->price;
        $config = config('services.click');

        return $config['base_url'] . '?' . http_build_query([
            'service_id' => $config['service_id'],
            'merchant_id' => $config['merchant_id'],
            'amount' => $amount,
            'transaction_param' => $registration->id,
        ]);
    }

    public function handleRequest(Request $request): array
    {
        $payload = $request->all();
        Log::info('Click request received', $payload);

        $action = (int) ($payload['action'] ?? -1);

        return match ($action) {
            0 => $this->handlePrepare($payload),
            1 => $this->handleComplete($payload),
            default => $this->errorResponse(
                self::ERROR_ACTION_NOT_FOUND,
                'Action not found',
                $payload,
            ),
        };
    }

    private function handlePrepare(array $payload): array
    {
        if (! $this->verifySignature($payload, 'prepare')) {
            return $this->errorResponse(self::ERROR_SIGN_CHECK_FAILED, 'SIGN CHECK FAILED!', $payload);
        }

        $registrationId = $payload['merchant_trans_id'] ?? null;
        $registration = Registration::with('olympiad')->find($registrationId);

        if ($registration === null) {
            return $this->errorResponse(self::ERROR_USER_NOT_EXIST, 'Order not found', $payload);
        }

        $expectedAmount = (int) $registration->olympiad->price;
        $requestAmount = (int) ($payload['amount'] ?? 0);

        if ($expectedAmount !== $requestAmount) {
            return $this->errorResponse(self::ERROR_INCORRECT_AMOUNT, 'Incorrect amount', $payload);
        }

        $this->paymentService->createForRegistration($registration);
        $this->paymentService->setSystem($registration, 'click');

        $clickTx = ClickTransaction::updateOrCreate(
            ['click_trans_id' => $payload['click_trans_id']],
            [
                'click_paydoc_id' => $payload['click_paydoc_id'] ?? null,
                'merchant_trans_id' => $registration->id,
                'amount' => $requestAmount,
                'status' => ClickTransaction::STATUS_PENDING,
                'sign_time' => $payload['sign_time'] ?? '',
            ],
        );

        return [
            'click_trans_id' => (int) $payload['click_trans_id'],
            'merchant_trans_id' => (string) $registration->id,
            'merchant_prepare_id' => $clickTx->id,
            'error' => self::ERROR_SUCCESS,
            'error_note' => 'Success',
        ];
    }

    private function handleComplete(array $payload): array
    {
        if (! $this->verifySignature($payload, 'complete')) {
            return $this->errorResponse(self::ERROR_SIGN_CHECK_FAILED, 'SIGN CHECK FAILED!', $payload);
        }

        $registrationId = $payload['merchant_trans_id'] ?? null;
        $registration = Registration::with('olympiad', 'payment')->find($registrationId);

        if ($registration === null) {
            return $this->errorResponse(self::ERROR_USER_NOT_EXIST, 'Order not found', $payload);
        }

        $merchantPrepareId = $payload['merchant_prepare_id'] ?? null;
        $clickTx = $this->resolveClickTransaction($merchantPrepareId, $registration->id);

        if ($clickTx === null) {
            return $this->errorResponse(self::ERROR_TRANSACTION_NOT_EXIST, 'Transaction not found', $payload);
        }

        if ($clickTx->status === ClickTransaction::STATUS_SUCCESS) {
            return $this->errorResponse(self::ERROR_ALREADY_PAID, 'Already paid', $payload);
        }

        if ($clickTx->status === ClickTransaction::STATUS_CANCELLED) {
            return $this->errorResponse(self::ERROR_TRANSACTION_CANCELLED, 'Transaction cancelled', $payload);
        }

        $clickError = (int) ($payload['error'] ?? 0);

        if ($clickError < 0) {
            $clickTx->forceFill(['status' => ClickTransaction::STATUS_CANCELLED])->save();

            $payment = $registration->payment;
            if ($payment !== null && $payment->status !== 'success') {
                $payment->forceFill(['status' => 'failed'])->save();
                $this->cancelRegistration($registration);
            }

            return $this->errorResponse(self::ERROR_TRANSACTION_CANCELLED, 'Transaction cancelled', $payload);
        }

        $expectedAmount = (int) $registration->olympiad->price;
        $requestAmount = (int) ($payload['amount'] ?? 0);

        if ($expectedAmount !== $requestAmount) {
            return $this->errorResponse(self::ERROR_INCORRECT_AMOUNT, 'Incorrect amount', $payload);
        }

        $payment = $registration->payment;
        if ($payment === null) {
            return $this->errorResponse(self::ERROR_TRANSACTION_NOT_EXIST, 'Payment not found', $payload);
        }

        return DB::transaction(function () use ($payload, $clickTx, $payment, $registration) {
            $clickTx->forceFill(['status' => ClickTransaction::STATUS_SUCCESS])->save();

            if ($payment->status !== 'success') {
                $payment->forceFill([
                    'status' => 'success',
                    'transaction_id' => $payload['click_trans_id'],
                    'paid_at' => now(),
                ])->save();
            }

            if ($registration->payment_status !== 'paid') {
                $registration->forceFill(['payment_status' => 'paid'])->save();
            }

            try {
                $this->ticketService->createTicket($registration->id);
                $this->notificationService->sendPaymentSuccess($registration->fresh(['user', 'olympiad']));
            } catch (\Throwable $e) {
                Log::error('Post-payment processing failed', ['error' => $e->getMessage()]);
            }

            return [
                'click_trans_id' => (int) $payload['click_trans_id'],
                'merchant_trans_id' => (string) $registration->id,
                'merchant_confirm_id' => $clickTx->id,
                'error' => self::ERROR_SUCCESS,
                'error_note' => 'Success',
            ];
        });
    }

    /**
     * Resolve the ClickTransaction for a complete request.
     *
     * When merchant_prepare_id is provided, look up by ID and verify ownership.
     * When null, find the most recent ClickTransaction for the registration,
     * preferring pending ones for processing.
     */
    private function resolveClickTransaction(?string $merchantPrepareId, int|string $registrationId): ?ClickTransaction
    {
        if ($merchantPrepareId !== null) {
            $tx = ClickTransaction::find($merchantPrepareId);
            if ($tx !== null && (int) $tx->merchant_trans_id === (int) $registrationId) {
                return $tx;
            }
            return null;
        }

        $pending = ClickTransaction::where('merchant_trans_id', $registrationId)
            ->where('status', ClickTransaction::STATUS_PENDING)
            ->latest()
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        return ClickTransaction::where('merchant_trans_id', $registrationId)
            ->latest()
            ->first();
    }

    private function cancelRegistration(Registration $registration): void
    {
        $registration->loadMissing(['user', 'olympiad']);

        Log::info('Click: cancelling registration', [
            'registration_id' => $registration->id,
            'has_user'        => $registration->user !== null,
            'telegram_id'     => $registration->user?->telegram_id,
        ]);

        try {
            $this->notificationService->sendPaymentCancelled($registration);
        } catch (\Throwable $e) {
            Log::warning('Click: failed to send cancellation notification', [
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
            Log::error('Click: failed to delete cancelled registration', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function verifySignature(array $payload, string $stage): bool
    {
        $secretKey = config('services.click.secret_key');
        $clickTransId = $payload['click_trans_id'] ?? '';
        $serviceId = $payload['service_id'] ?? '';
        $merchantTransId = $payload['merchant_trans_id'] ?? '';
        $amount = $payload['amount'] ?? '';
        $action = $payload['action'] ?? '';
        $signTime = $payload['sign_time'] ?? '';
        $signString = $payload['sign_string'] ?? '';

        if ($stage === 'prepare') {
            $expected = md5(
                $clickTransId . $serviceId . $secretKey . $merchantTransId . $amount . $action . $signTime
            );
        } else {
            $merchantPrepareId = $payload['merchant_prepare_id'] ?? '';
            $expected = md5(
                $clickTransId . $serviceId . $secretKey . $merchantTransId . $merchantPrepareId . $amount . $action . $signTime
            );
        }

        return $expected === $signString;
    }

    private function errorResponse(int $error, string $note, array $payload): array
    {
        return [
            'click_trans_id' => (int) ($payload['click_trans_id'] ?? 0),
            'merchant_trans_id' => (string) ($payload['merchant_trans_id'] ?? ''),
            'error' => $error,
            'error_note' => $note,
        ];
    }
}
