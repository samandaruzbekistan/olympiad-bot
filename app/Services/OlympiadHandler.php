<?php

namespace App\Services;

use App\Models\Olympiad;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class OlympiadHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected ClickPaymentService $clickPayments,
        protected PaymePaymentService $paymePayments,
        protected PaymentService $paymentService,
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

        $text = "🏆 {$olympiad->title}\n\n";
        if ($olympiad->description) {
            $text .= "📝 {$olympiad->description}\n\n";
        }
        $date = $olympiad->start_date?->format('Y-m-d H:i') ?? '—';
        $text .= "📅 Sana: {$date}\n";
        $text .= "📍 Manzil: " . ($olympiad->location_name ?? '—') . "\n";
        $text .= "💰 Narxi: " . number_format((int) $olympiad->price, 0, '.', ' ') . " so'm";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Ishtirok etish', 'callback_data' => 'participate_' . $olympiad->id],
                ],
                [
                    ['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu'],
                ],
            ],
        ];

        $logoUrl = $this->buildLogoUrl($olympiad->logo);

        if ($logoUrl !== null) {
            $this->telegram->sendPhoto($chatId, $logoUrl, $text . "\n\n");
            $this->telegram->sendMessage($chatId, "Quyidagi tugmalardan foydalaning:", $keyboard);
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
                "✅ Siz allaqachon ushbu olimpiadaga to'lov qilgansiz.",
                [['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu']],
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

    private function buildLogoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $base = (string) Config::get('app.url', URL::to('/'));
        if (str_contains($base, '127.0.0.1') || str_contains($base, 'localhost')) {
            return storage_path('app/public/' . ltrim($path, '/'));
        }

        $base = rtrim($base, '/');

        return $base . '/storage/' . ltrim($path, '/');
    }
}
