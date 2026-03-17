<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use InvalidArgumentException;

class PaymentService
{
    /**
     * Create (or return existing) payment for a registration.
     *
     * - Only one active payment per registration.
     * - Amount comes from olympiad price.
     * - payment_system is left null until user selects a method.
     */
    public function createForRegistration(Registration $registration): Payment
    {
        $existing = Payment::query()
            ->where('registration_id', $registration->id)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $olympiad = $registration->olympiad;
        if ($olympiad === null) {
            throw new InvalidArgumentException('Registration does not have an associated olympiad.');
        }

        return Payment::query()->create([
            'registration_id' => $registration->id,
            'amount' => (int) $olympiad->price,
            'payment_system' => null,
            'status' => 'pending',
            'transaction_id' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Set payment system (click, payme, etc) for a registration's payment.
     *
     * Ensures a payment exists and reuses it.
     */
    public function setPaymentSystem(Registration $registration, string $system): Payment
    {
        $system = strtolower($system);

        if (! in_array($system, ['click', 'payme'], true)) {
            throw new InvalidArgumentException("Unsupported payment system: {$system}");
        }

        $payment = $this->createForRegistration($registration);

        if ($payment->payment_system !== $system) {
            $payment->payment_system = $system;
            $payment->save();
        }

        return $payment;
    }
}

