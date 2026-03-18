<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class BotNotificationService
{
    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly TicketImageService $ticketImageService,
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

        $date = $olympiad->start_date?->format('d.m.Y H:i') ?? "Noma'lum";

        $caption = "✅ To'lov muvaffaqiyatli amalga oshirildi!\n\n"
            . "🎟 Ticket: <b>{$registration->ticket_number}</b>\n"
            . "📍 Manzil: {$location}\n"
            . "📅 Sana: {$date}";

        $this->sendTicketImage($registration, $user->telegram_id, $caption);

        if ($olympiad->latitude !== null && $olympiad->longitude !== null) {
            $this->telegramService->sendLocation(
                $user->telegram_id,
                (float) $olympiad->latitude,
                (float) $olympiad->longitude,
            );
        }
    }

    private function sendTicketImage(Registration $registration, string|int $chatId, string $caption): void
    {
        try {
            $pngData = $this->ticketImageService->render($registration);

            $this->telegramService->sendPhotoFromBinary(
                $chatId,
                $pngData,
                "ticket-{$registration->ticket_number}.png",
                $caption,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to render/send ticket image, falling back to text', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);

            $this->telegramService->sendMessage($chatId, $caption);
        }
    }
}
