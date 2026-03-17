<?php

namespace App\Services;

use App\Models\Registration;
use InvalidArgumentException;

class BotNotificationService
{
    public function __construct(
        private readonly TelegramService $telegramService,
    ) {
    }

    public function sendPaymentSuccess(Registration $registration): void
    {
        $registration->loadMissing(['user', 'olympiad']);

        $user = $registration->user;
        $olympiad = $registration->olympiad;

        if ($user === null || $olympiad === null) {
            throw new InvalidArgumentException('Registration must have related user and olympiad.');
        }

        if ($registration->ticket_number === null) {
            throw new InvalidArgumentException('Registration must have a ticket number before notification.');
        }

        $location = $olympiad->location_name;
        if (! empty($olympiad->location_address)) {
            $location .= ' (' . $olympiad->location_address . ')';
        }

        $date = $olympiad->start_date?->format('d.m.Y H:i') ?? 'Noma’lum';

        $message = "To‘lov muvaffaqiyatli amalga oshirildi ✅\n\n"
            . "🎟 Ticket: {$registration->ticket_number}\n"
            . "📍 Manzil: {$location}\n"
            . "📅 Sana: {$date}";

        $this->telegramService->sendMessage($user->telegram_id, $message);

        if ($olympiad->latitude !== null && $olympiad->longitude !== null) {
            $this->telegramService->sendLocation(
                $user->telegram_id,
                (float) $olympiad->latitude,
                (float) $olympiad->longitude,
            );
        }
    }
}
