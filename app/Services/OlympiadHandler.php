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
    ) {
    }

    public function showOlympiads(int|string $chatId, int|string $telegramId): void
    {
        // Reply keyboardni olib tashlaymiz
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
        $text .= "💰 Narxi: " . number_format((int) $olympiad->price, 0, '.', ' ') . " so‘m";

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
            // Inline tugmalarni alohida xabar bilan yuboramiz
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

        // To'lov havolalari
        $clickUrl = $this->clickPayments->generatePaymentLink($registration);
        $paymeUrl = $this->paymePayments->generatePaymentLink($registration);

        $rows = [
            [
                [
                    'text' => 'Click',
                    'url' => $clickUrl,
                ],
                [
                    'text' => 'Payme',
                    'url' => $paymeUrl,
                ],
            ],
            [
                ['text' => '❌ Bekor qilish', 'callback_data' => 'main_menu'],
            ],
        ];

        // Eski olimpiada tafsilotlari xabarini edit qilib, to'lov tanlashni ko'rsatamiz
        $this->telegram->editMessageText(
            $chatId,
            (int) $messageId,
            "💳 To‘lov turini tanlang:",
            $rows,
        );
    }

    public function handlePaymentCallback(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;

        if ($chatId === null || $telegramId === null || ! is_string($data)) {
            return;
        }

        $registrationId = (int) preg_replace('/\D+/', '', $data);
        $registration = Registration::with('olympiad')->find($registrationId);
        if ($registration === null) {
            $this->telegram->sendMessage($chatId, "❌ Ro'yxatdan o'tish topilmadi.");

            return;
        }

        if (str_starts_with($data, 'payment_click_')) {
            $url = $this->clickPayments->generatePaymentLink($registration);
        } else {
            $url = $this->paymePayments->generatePaymentLink($registration);
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'To‘lov qilish', 'url' => $url],
                ],
                [
                    ['text' => '⬅️ Bosh menu', 'callback_data' => 'main_menu'],
                ],
            ],
        ];

        $this->telegram->sendMessage(
            $chatId,
            "To‘lov qilish uchun quyidagi havoladan foydalaning:",
            $keyboard,
        );
    }

    private function buildLogoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Lokal rivojlantirishda (APP_URL = http://127.0.0.1 yoki http://localhost)
        // Telegram URL orqali rasmga kira olmaydi, shuning uchun faylni bevosita yuklaymiz.
        $base = (string) Config::get('app.url', URL::to('/'));
        if (str_contains($base, '127.0.0.1') || str_contains($base, 'localhost')) {
            return storage_path('app/public/' . ltrim($path, '/'));
        }

        // Productionda esa to'liq URL dan foydalanamiz.
        $base = rtrim($base, '/');

        return $base . '/storage/' . ltrim($path, '/');
    }
}

