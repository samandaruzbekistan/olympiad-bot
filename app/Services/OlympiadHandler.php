<?php

namespace App\Services;

use App\Models\Olympiad;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OlympiadHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected ClickPaymentService $clickPayments,
        protected PaymePaymentService $paymePayments,
        protected PaymentService $paymentService,
        protected TicketImageService $ticketImageService,
        protected TicketService $ticketService,
    ) {
    }

    public function showOlympiads(int|string $chatId, int|string $telegramId): void
    {
        $this->telegram->sendMessage($chatId, "🏆 Mavjud olimpiadalar:", ['remove_keyboard' => true]);

        $olympiads = Olympiad::where('status', 'active')
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        if ($olympiads->isEmpty()) {
            $rows = [
                [
                    ['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu'],
                ],
            ];
            $this->telegram->sendMessage($chatId, "🏆 Mavjud olimpiadalar hozircha yo'q.", ['inline_keyboard' => $rows]);

            return;
        }

        $rows = [];
        foreach ($olympiads as $olympiad) {
            $rows[] = [
                [
                    'text' => $olympiad->title,
                    'callback_data' => 'olympiad_' . $olympiad->id,
                ],
            ];
        }

        $rows[] = [
            ['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu'],
        ];

        $this->telegram->sendMessage(
            $chatId,
            "Quyidagilardan birini tanlang:",
            ['inline_keyboard' => $rows],
        );
    }

    public function showOlympiadDetails(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $id = (int) substr($data, strlen('olympiad_'));
        $olympiad = Olympiad::find($id);

        if ($olympiad === null) {
            $this->telegram->sendMessage($chatId, "❌ Olimpiada topilmadi.");

            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        $registration = $user
            ? Registration::where('user_id', $user->id)->where('olympiad_id', $olympiad->id)->first()
            : null;

        $alreadyPaid = $registration !== null && $registration->payment_status === 'paid';

        $text = "🏆 {$olympiad->title}\n\n";
        if ($olympiad->description) {
            $text .= "📝 {$olympiad->description}\n\n";
        }
        $date = $olympiad->start_date?->format('Y-m-d H:i') ?? '—';
        $text .= "📅 Sana: {$date}\n";
        $text .= "📍 Manzil: " . ($olympiad->location_name ?? '—') . "\n";

        if ($alreadyPaid) {
            $text .= "\n✅ <b>Siz ushbu olimpiada ishtirokchisisiz!</b>";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🎫 Biletni ko\'rish', 'callback_data' => 'ticket_' . $registration->id]],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            ];
        } else {
            $text .= "💰 Narxi: " . number_format((int) $olympiad->price, 0, '.', ' ') . " so'm";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '✅ Ishtirok etish', 'callback_data' => 'participate_' . $olympiad->id]],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            ];
        }

        $logoPath = $this->resolveLogoPath($olympiad->logo);

        if ($logoPath !== null) {
            try {
                $this->telegram->sendPhoto($chatId, $logoPath, $text);
                $this->telegram->sendMessage($chatId, "Quyidagi tugmalardan foydalaning:", $keyboard);
            } catch (\Throwable $e) {
                Log::warning('Failed to send olympiad logo', ['error' => $e->getMessage()]);
                $this->telegram->sendMessage($chatId, $text, $keyboard);
            }
        } else {
            $this->telegram->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function handleParticipation(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $messageId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            $this->telegram->sendMessage($chatId, "❌ Avval ro'yxatdan o'ting.");
            return;
        }

        $olympiadId = (int) substr($data, strlen('participate_'));
        $olympiad = Olympiad::find($olympiadId);

        if ($olympiad === null) {
            $this->telegram->sendMessage($chatId, "❌ Olimpiada topilmadi.");
            return;
        }

        $registration = Registration::firstOrCreate(
            [
                'user_id' => $user->id,
                'olympiad_id' => $olympiad->id,
            ],
            [
                'status' => 'pending',
                'payment_status' => 'pending',
            ],
        );

        if ($registration->payment_status === 'paid') {
            $this->telegram->editMessageText(
                $chatId,
                (int) $messageId,
                "✅ Siz ushbu olimpiada ishtirokchisisiz!",
                [
                    [['text' => '🎫 Biletni ko\'rish', 'callback_data' => 'ticket_' . $registration->id]],
                    [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
                ],
            );
            return;
        }

        $this->paymentService->createForRegistration($registration);

        $clickUrl = $this->clickPayments->generatePaymentLink($registration);
        $paymeUrl = $this->paymePayments->generatePaymentLink($registration);

        $price = number_format((int) $olympiad->price, 0, '.', ' ');

        $rows = [
            [['text' => '💳 Click', 'url' => $clickUrl]],
            [['text' => '💳 Payme', 'url' => $paymeUrl]],
            [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
        ];

        $this->telegram->editMessageText(
            $chatId,
            (int) $messageId,
            "💳 To'lov turini tanlang:\n\n🏆 {$olympiad->title}\n💰 Narxi: {$price} so'm",
            $rows,
        );
    }

    public function handleTicketRequest(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $registrationId = (int) substr($data, strlen('ticket_'));
        $registration = Registration::with(['user', 'olympiad'])->find($registrationId);

        if ($registration === null) {
            $this->telegram->sendMessage($chatId, "❌ Ro'yxat topilmadi.");
            return;
        }

        $user = User::where('telegram_id', $telegramId)->first();
        if ($user === null || $registration->user_id !== $user->id) {
            $this->telegram->sendMessage($chatId, "❌ Bu bilet sizga tegishli emas.");
            return;
        }

        if ($registration->payment_status !== 'paid') {
            $this->telegram->sendMessage($chatId, "❌ To'lov hali amalga oshirilmagan.");
            return;
        }

        if ($registration->ticket_number === null) {
            $this->ticketService->createTicket($registration->id);
            $registration->refresh();
        }

        try {
            $pngData = $this->ticketImageService->render($registration);

            $olympiad = $registration->olympiad;
            $location = $olympiad->location_name ?? '—';
            if (! empty($olympiad->location_address)) {
                $location .= ' (' . $olympiad->location_address . ')';
            }
            $date = $olympiad->start_date?->format('d.m.Y H:i') ?? "Noma'lum";

            $caption = "🎫 <b>Sizning biletingiz</b>\n\n"
                . "🏆 {$olympiad->title}\n"
                . "🎟 Ticket: <b>{$registration->ticket_number}</b>\n"
                . "📍 Manzil: {$location}\n"
                . "📅 Sana: {$date}";

            $this->telegram->sendPhotoFromBinary(
                $chatId,
                $pngData,
                "ticket-{$registration->ticket_number}.png",
                $caption,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate ticket image', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
            $this->telegram->sendMessage(
                $chatId,
                "🎫 Bilet: <b>{$registration->ticket_number}</b>\n\nRasm generatsiya qilishda xatolik yuz berdi.",
            );
        }
    }

    private function resolveLogoPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $localPath = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($localPath) ? $localPath : null;
    }
}
