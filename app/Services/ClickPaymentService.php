<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ClickPaymentService
{
    private const ACTION_PREPARE = 0;
    private const ACTION_COMPLETE = 1;

    public const ERROR_SUCCESS = 0;
    public const ERROR_SIGN_CHECK_FAILED = -1;
    public const ERROR_INCORRECT_AMOUNT = -2;
    public const ERROR_ACTION_NOT_FOUND = -3;
    public const ERROR_ALREADY_PAID = -4;
    public const ERROR_USER_NOT_EXIST = -5;
    public const ERROR_TRANSACTION_NOT_EXIST = -6;
    public const ERROR_UPDATE_FAILED = -7;
    public const ERROR_REQUEST_FROM_INSIDE = -8;
    public const ERROR_TRANSACTION_CANCELLED = -9;

    public function __construct(
        private readonly TicketService $ticketService,
    ) {
    }

    public function generatePaymentLink(Registration $registration): string
    {
        $payment = $registration->payments()
            ->where('payment_system', 'click')
            ->latest('id')
            ->first();

        if ($payment === null) {
            throw new InvalidArgumentException('Registration does not have a payment record.');
        }

        $serviceId = (string) config('services.click.service_id');
        $merchantId = (string) config('services.click.merchant_id');
        $baseUrl = (string) config('services.click.base_url', 'https://my.click.uz/services/pay');

        $query = http_build_query([
            'service_id' => $serviceId,
            'merchant_id' => $merchantId,
            'amount' => $this->normalizeAmount($payment->amount),
            'transaction_param' => $registration->id,
        ]);

        return rtrim($baseUrl, '?') . '?' . $query;
    }

    public function handleRequest(Request $request): array
    {
        Log::info('Click callback received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $payload = $this->normalizePayload($request);

        if (! $this->verifySignature($payload)) {
            return $this->errorResponse(
                $payload,
                self::ERROR_SIGN_CHECK_FAILED,
                'Invalid signature.',
            );
        }

        if (! in_array($payload['action'], [self::ACTION_PREPARE, self::ACTION_COMPLETE], true)) {
            return $this->errorResponse(
                $payload,
                self::ERROR_ACTION_NOT_FOUND,
                'Unsupported action.',
            );
        }

        return match ($payload['action']) {
            self::ACTION_PREPARE => $this->handlePrepare($payload),
            self::ACTION_COMPLETE => $this->handleComplete($payload),
        };
    }

    public function verifySignature(array $params): bool
    {
        $expected = md5(
            $params['click_trans_id']
            . $params['service_id']
            . $this->secretKey()
            . $params['merchant_trans_id']
            . $params['amount']
            . $params['action']
            . $params['sign_time']
        );

        return hash_equals($expected, $params['sign_string']);
    }

    private function handlePrepare(array $payload): array
    {
        $registration = Registration::query()->find($payload['merchant_trans_id']);

        if ($registration === null) {
            return $this->errorResponse($payload, self::ERROR_USER_NOT_EXIST, 'Registration not found.');
        }

        $payment = Payment::query()
            ->where('registration_id', $registration->id)
            ->where('payment_system', 'click')
            ->latest('id')
            ->first();

        if ($payment === null) {
            return $this->errorResponse($payload, self::ERROR_TRANSACTION_NOT_EXIST, 'Payment record not found.');
        }

        if ($this->normalizeAmount($payment->amount) !== $payload['amount']) {
            return $this->errorResponse($payload, self::ERROR_INCORRECT_AMOUNT, 'Amount mismatch.');
        }

        if ($payment->status === 'success' || $registration->payment_status === 'paid') {
            return $this->errorResponse($payload, self::ERROR_ALREADY_PAID, 'Payment already completed.');
        }

        $payment->forceFill([
            'transaction_id' => $payload['click_trans_id'],
            'status' => 'pending',
        ])->save();

        return $this->successResponse($payload, $payment->id);
    }

    private function handleComplete(array $payload): array
    {
        if ((int) $payload['error'] < 0) {
            $payment = Payment::query()
                ->where('registration_id', $payload['merchant_trans_id'])
                ->where('payment_system', 'click')
                ->latest('id')
                ->first();

            if ($payment !== null && $payment->status !== 'success') {
                $payment->forceFill([
                    'transaction_id' => $payload['click_trans_id'],
                    'status' => 'failed',
                ])->save();
            }

            return $this->errorResponse(
                $payload,
                self::ERROR_TRANSACTION_CANCELLED,
                $payload['error_note'] ?: 'Transaction cancelled.',
            );
        }

        $payment = Payment::query()
            ->where('transaction_id', $payload['click_trans_id'])
            ->where('payment_system', 'click')
            ->latest('id')
            ->first();

        if ($payment === null) {
            $payment = Payment::query()
                ->where('registration_id', $payload['merchant_trans_id'])
                ->where('payment_system', 'click')
                ->latest('id')
                ->first();
        }

        if ($payment === null) {
            return $this->errorResponse($payload, self::ERROR_TRANSACTION_NOT_EXIST, 'Payment record not found.');
        }

        $registration = $payment->registration;
        if ($registration === null) {
            return $this->errorResponse($payload, self::ERROR_USER_NOT_EXIST, 'Registration not found.');
        }

        if ($this->normalizeAmount($payment->amount) !== $payload['amount']) {
            return $this->errorResponse($payload, self::ERROR_INCORRECT_AMOUNT, 'Amount mismatch.');
        }

        if ($payment->status === 'success' || $registration->payment_status === 'paid') {
            return $this->successResponse($payload, $payment->id, $payment->id);
        }

        try {
            DB::transaction(function () use ($payment, $registration, $payload): void {
                $payment->forceFill([
                    'transaction_id' => $payload['click_trans_id'],
                    'status' => 'success',
                    'paid_at' => now(),
                ])->save();

                $registration->forceFill([
                    'payment_status' => 'paid',
                ])->save();

                $this->ticketService->createTicket($registration->id);
            });
        } catch (\Throwable $exception) {
            Log::error('Click callback processing failed', [
                'payload' => $payload,
                'payment_id' => $payment->id,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($payload, self::ERROR_UPDATE_FAILED, 'Failed to update payment.');
        }

        return $this->successResponse($payload, $payment->id, $payment->id);
    }

    private function normalizePayload(Request $request): array
    {
        return [
            'click_trans_id' => (string) $request->input('click_trans_id'),
            'service_id' => (string) $request->input('service_id'),
            'merchant_trans_id' => (string) $request->input('merchant_trans_id', $request->input('transaction_param')),
            'amount' => $this->normalizeAmount($request->input('amount')),
            'action' => (int) $request->input('action'),
            'sign_time' => (string) $request->input('sign_time'),
            'sign_string' => (string) $request->input('sign_string', $request->input('signature')),
            'error' => (int) $request->input('error', 0),
            'error_note' => (string) $request->input('error_note', ''),
            'merchant_prepare_id' => $request->input('merchant_prepare_id'),
        ];
    }

    private function successResponse(array $payload, int|string $merchantPrepareId, int|string|null $merchantConfirmId = null): array
    {
        $response = [
            'click_trans_id' => $payload['click_trans_id'],
            'merchant_trans_id' => $payload['merchant_trans_id'],
            'merchant_prepare_id' => $merchantPrepareId,
            'error' => self::ERROR_SUCCESS,
            'error_note' => 'Success',
        ];

        if ($merchantConfirmId !== null) {
            $response['merchant_confirm_id'] = $merchantConfirmId;
        }

        return $response;
    }

    private function errorResponse(array $payload, int $errorCode, string $errorNote): array
    {
        $response = [
            'click_trans_id' => $payload['click_trans_id'] ?? null,
            'merchant_trans_id' => $payload['merchant_trans_id'] ?? null,
            'merchant_prepare_id' => $payload['merchant_prepare_id'] ?? null,
            'error' => $errorCode,
            'error_note' => $errorNote,
        ];

        if (($payload['action'] ?? null) === self::ACTION_COMPLETE) {
            $response['merchant_confirm_id'] = $payload['merchant_prepare_id'] ?? null;
        }

        return $response;
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function secretKey(): string
    {
        return (string) config('services.click.secret_key');
    }
}
