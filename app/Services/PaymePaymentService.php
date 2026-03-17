<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymePaymentService
{
    private const STATE_CREATED = 1;
    private const STATE_COMPLETED = 2;
    private const STATE_CANCELLED = -1;

    public const ERROR_INVALID_AMOUNT = -31001;
    public const ERROR_REGISTRATION_NOT_FOUND = -31050;
    public const ERROR_TRANSACTION_NOT_FOUND = -31003;
    public const ERROR_CANNOT_PERFORM = -31008;
    public const ERROR_INVALID_ACCOUNT = -31050;
    public const ERROR_INVALID_METHOD = -32601;

    public function __construct(
        private readonly TicketService $ticketService,
    ) {
    }

    /**
     * Generate a user-facing Payme payment link for the given registration.
     *
     * This is used by the Telegram bot to redirect the user to Payme's
     * payment page, while the actual JSON-RPC callbacks are still handled
     * by the methods below.
     */
    public function generatePaymentLink(Registration $registration): string
    {
        $baseUrl = (string) config('payme.checkout_url', '');
        $merchantId = (string) config('payme.merchant_id', '');

        $amount = $this->expectedAmount($registration); // in so'm

        $params = [
            'merchant' => $merchantId,
            'amount' => $amount,
            'account[registration_id]' => $registration->id,
        ];

        return rtrim($baseUrl, '?') . '?' . http_build_query($params);
    }

    public function checkPerformTransaction(array $params): array
    {
        $registration = $this->findRegistrationFromAccount($params['account'] ?? []);
        $amount = $this->extractAmount($params);

        if ($registration === null) {
            throw new PaymeException(self::ERROR_REGISTRATION_NOT_FOUND, 'Registration not found.');
        }

        if ($registration->payment_status === 'paid') {
            throw new PaymeException(self::ERROR_CANNOT_PERFORM, 'Registration has already been paid.');
        }

        if ($amount !== $this->expectedAmount($registration)) {
            throw new PaymeException(self::ERROR_INVALID_AMOUNT, 'Incorrect amount.');
        }

        return [
            'allow' => true,
        ];
    }

    public function createTransaction(array $params): array
    {
        $transactionId = (string) ($params['id'] ?? '');
        $account = $params['account'] ?? [];
        $registration = $this->findRegistrationFromAccount($account);
        $amount = $this->extractAmount($params);

        if ($registration === null) {
            throw new PaymeException(self::ERROR_REGISTRATION_NOT_FOUND, 'Registration not found.');
        }

        if ($amount !== $this->expectedAmount($registration)) {
            throw new PaymeException(self::ERROR_INVALID_AMOUNT, 'Incorrect amount.');
        }

        $existingPayment = Payment::query()
            ->where('transaction_id', $transactionId)
            ->where('payment_system', 'payme')
            ->first();

        if ($existingPayment !== null) {
            return $this->buildTransactionPayload($existingPayment);
        }

        $successfulPayment = Payment::query()
            ->where('registration_id', $registration->id)
            ->where('payment_system', 'payme')
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if ($successfulPayment !== null || $registration->payment_status === 'paid') {
            throw new PaymeException(self::ERROR_CANNOT_PERFORM, 'Registration has already been paid.');
        }

        $payment = Payment::query()->create([
            'registration_id' => $registration->id,
            'amount' => $amount,
            'payment_system' => 'payme',
            'transaction_id' => $transactionId,
            'status' => 'pending',
        ]);

        $createTime = $this->timestampToCarbon($params['time'] ?? null);
        $payment->forceFill([
            'created_at' => $createTime,
            'updated_at' => $createTime,
        ])->save();

        return $this->buildTransactionPayload($payment->fresh());
    }

    public function performTransaction(array $params): array
    {
        $transactionId = (string) ($params['id'] ?? '');

        $payment = $this->findPaymentByTransactionId($transactionId);
        if ($payment === null) {
            throw new PaymeException(self::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        if ($payment->status === 'success') {
            return $this->buildTransactionPayload($payment);
        }

        if ($payment->status === 'failed') {
            throw new PaymeException(self::ERROR_CANNOT_PERFORM, 'Transaction has been cancelled.');
        }

        $registration = $payment->registration;
        if ($registration === null) {
            throw new PaymeException(self::ERROR_REGISTRATION_NOT_FOUND, 'Registration not found.');
        }

        DB::transaction(function () use ($payment, $registration): void {
            $payment->forceFill([
                'status' => 'success',
                'paid_at' => now(),
            ])->save();

            $registration->forceFill([
                'payment_status' => 'paid',
            ])->save();

            $this->ticketService->createTicket($registration->id);
        });

        return $this->buildTransactionPayload($payment->fresh());
    }

    public function cancelTransaction(array $params): array
    {
        $transactionId = (string) ($params['id'] ?? '');

        $payment = $this->findPaymentByTransactionId($transactionId);
        if ($payment === null) {
            throw new PaymeException(self::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        if ($payment->status === 'success') {
            throw new PaymeException(self::ERROR_CANNOT_PERFORM, 'Completed transaction cannot be cancelled.');
        }

        if ($payment->status !== 'failed') {
            DB::transaction(function () use ($payment): void {
                $payment->forceFill([
                    'status' => 'failed',
                    'paid_at' => null,
                ])->save();
            });
        }

        return $this->buildTransactionPayload($payment->fresh() ?? $payment);
    }

    public function checkTransaction(array $params): array
    {
        $transactionId = (string) ($params['id'] ?? '');

        $payment = $this->findPaymentByTransactionId($transactionId);
        if ($payment === null) {
            throw new PaymeException(self::ERROR_TRANSACTION_NOT_FOUND, 'Transaction not found.');
        }

        return $this->buildTransactionPayload($payment);
    }

    public function authorize(?string $authorizationHeader): void
    {
        if ($authorizationHeader === null || ! str_starts_with($authorizationHeader, 'Basic ')) {
            throw new PaymeException(-32504, 'Unauthorized.');
        }

        $encodedCredentials = substr($authorizationHeader, 6);
        $decodedCredentials = base64_decode($encodedCredentials, true);

        if ($decodedCredentials === false) {
            throw new PaymeException(-32504, 'Unauthorized.');
        }

        [$login, $password] = array_pad(explode(':', $decodedCredentials, 2), 2, null);

        if (
            $login !== (string) config('payme.login')
            || $password !== (string) config('payme.password')
        ) {
            throw new PaymeException(-32504, 'Unauthorized.');
        }
    }

    public function dispatch(string $method, array $params): array
    {
        Log::info('Payme request dispatched', [
            'method' => $method,
            'params' => $params,
        ]);

        return match ($method) {
            'CheckPerformTransaction' => $this->checkPerformTransaction($params),
            'CreateTransaction' => $this->createTransaction($params),
            'PerformTransaction' => $this->performTransaction($params),
            'CancelTransaction' => $this->cancelTransaction($params),
            'CheckTransaction' => $this->checkTransaction($params),
            default => throw new PaymeException(self::ERROR_INVALID_METHOD, 'Method not found.'),
        };
    }

    private function findRegistrationFromAccount(array $account): ?Registration
    {
        $registrationId = $account['registration_id'] ?? null;
        if ($registrationId === null) {
            throw new PaymeException(self::ERROR_INVALID_ACCOUNT, 'registration_id is required.');
        }

        return Registration::query()
            ->with('olympiad')
            ->find($registrationId);
    }

    private function extractAmount(array $params): int
    {
        if (! array_key_exists('amount', $params)) {
            throw new PaymeException(self::ERROR_INVALID_AMOUNT, 'Amount is required.');
        }

        return (int) $params['amount'];
    }

    private function expectedAmount(Registration $registration): int
    {
        if ($registration->olympiad === null) {
            throw new PaymeException(self::ERROR_INVALID_ACCOUNT, 'Registration olympiad not found.');
        }

        return (int) $registration->olympiad->price;
    }

    private function findPaymentByTransactionId(string $transactionId): ?Payment
    {
        return Payment::query()
            ->with('registration')
            ->where('transaction_id', $transactionId)
            ->where('payment_system', 'payme')
            ->first();
    }

    private function buildTransactionPayload(Payment $payment): array
    {
        $state = match ($payment->status) {
            'success' => self::STATE_COMPLETED,
            'failed' => self::STATE_CANCELLED,
            default => self::STATE_CREATED,
        };

        return [
            'create_time' => $this->toMilliseconds($payment->created_at),
            'perform_time' => $this->toMilliseconds($payment->paid_at),
            'cancel_time' => $payment->status === 'failed' ? $this->toMilliseconds($payment->updated_at) : 0,
            'transaction' => (string) $payment->id,
            'state' => $state,
            'reason' => $payment->status === 'failed' ? 5 : null,
        ];
    }

    private function toMilliseconds(Carbon|string|null $value): int
    {
        if ($value === null) {
            return 0;
        }

        $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);

        return (int) $carbon->valueOf();
    }

    private function timestampToCarbon(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        return Carbon::createFromTimestampMs((int) $value);
    }
}
