<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function createForRegistration(Registration $registration): Payment
    {
        $existing = $registration->payment;
        if ($existing !== null) {
            return $existing;
        }

        return Payment::create([
            'registration_id' => $registration->id,
            'amount' => $registration->olympiad->price ?? 0,
            'status' => 'pending',
        ]);
    }

    public function setSystem(Registration $registration, string $system): void
    {
        $registration->forceFill(['payment_system' => $system])->save();
    }

    public function markPaid(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->forceFill([
                'status' => 'success',
                'paid_at' => now(),
            ])->save();

            $payment->registration->forceFill([
                'payment_status' => 'paid',
            ])->save();
        });
    }

    public function markFailed(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->forceFill(['status' => 'failed'])->save();

            $payment->registration->forceFill([
                'payment_status' => 'failed',
            ])->save();
        });
    }
}
