<?php

namespace App\Services;

class BotManager
{
    public function __construct(
        protected TelegramService $telegram,
        protected StartHandler $startHandler,
        protected RegistrationHandler $registrationHandler,
        protected OlympiadHandler $olympiadHandler,
    ) {
    }

    /**
     * Entry point for processing a Telegram update.
     */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    /**
     * Handle an incoming message update.
     *
     * Routes between commands (e.g. /start) and regular messages.
     */
    public function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text   = $message['text'] ?? '';

        if ($chatId === null) {
            return;
        }

        if (is_string($text) && str_starts_with($text, '/')) {
            $this->handleCommand($chatId, $text, $message);

            return;
        }

        // Matn yoki kontakt (telefon tugmasi) — registration handler ga yuboramiz
        if (isset($message['contact']) || $text !== '') {
            $this->handlePlainMessage($chatId, $text, $message);
        }
    }

    /**
     * Handle an incoming callback query.
     */
    public function handleCallback(array $callback): void
    {
        $callbackId = $callback['id'] ?? null;
        $data = $callback['data'] ?? null;

        if ($data === 'register_start') {
            $this->registrationHandler->handleCallback($callback);

            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }

            return;
        }

        if (is_string($data) && (str_starts_with($data, 'region_') || str_starts_with($data, 'district_'))) {
            if ($this->registrationHandler->handleRegionDistrictCallback($callback) && $callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && (str_starts_with($data, 'grade_') || str_starts_with($data, 'subject_toggle_') || $data === 'subjects_confirm')) {
            if ($this->registrationHandler->handleGradeSubjectCallback($callback) && $callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'olympiad_')) {
            $this->olympiadHandler->showOlympiadDetails($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'participate_')) {
            $this->olympiadHandler->handleParticipation($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        // payment callbacklar o'chirildi (Click/Payme integratsiyasi tozalandi)

        if ($data === 'main_menu') {
            $chatId = $callback['message']['chat']['id'] ?? null;
            $messageId = $callback['message']['message_id'] ?? null;
            if ($chatId !== null && $messageId !== null) {
                // Hozirgi inline xabarni o'chirib, bosh menyuni qayta ko'rsatamiz
                $this->telegram->deleteMessage($chatId, $messageId);
                $this->registrationHandler->showMainMenu($chatId);
            } elseif ($chatId !== null) {
                $this->registrationHandler->showMainMenu($chatId);
            }
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'menu_')) {
            $this->handleMenuCallback($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if (is_string($data) && str_starts_with($data, 'profile_edit_')) {
            $this->registrationHandler->handleProfileEditCallback($callback);
            if ($callbackId !== null) {
                $this->telegram->answerCallback($callbackId);
            }
            return;
        }

        if ($callbackId === null) {
            return;
        }

        $this->telegram->answerCallback($callbackId);
    }

    protected function handleMenuCallback(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? null;
        if ($chatId === null || $telegramId === null) {
            return;
        }
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();
        if ($data === 'menu_olympiads') {
            $this->telegram->sendMessage($chatId, "Olimpiadalar bo‘limi. Tez orada bu yerda ro‘yxat ko‘rinadi.");
        } elseif ($data === 'menu_profile') {
            $this->sendProfileContent($chatId, $telegramId);
        } elseif ($data === 'menu_payments') {
            $this->telegram->sendMessage($chatId, "To‘lovlarim. Tez orada bu yerda to‘lovlar ro‘yxati ko‘rinadi.");
        } elseif ($data === 'menu_tickets') {
            $this->telegram->sendMessage($chatId, "Biletlarim. Tez orada bu yerda chiptalar ro‘yxati ko‘rinadi.");
        }
    }

    /**
     * Route Telegram commands such as /start.
     */
    protected function handleCommand(int|string $chatId, string $text, array $message): void
    {
        $command = trim(strtok($text, ' '));

        if ($command === '/start') {
            $this->handleStartCommand($chatId, $message);

            return;
        }

        $this->handleUnknownCommand($chatId, $command, $message);
    }

    /**
     * Handle non-command text messages (yozilgan matn yoki reply keyboard tugmasi).
     */
    protected function handlePlainMessage(int|string $chatId, string $text, array $message): void
    {
        $telegramId = $message['from']['id'] ?? $chatId;
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();

        if ($user !== null && $text !== '') {
            if ($text === '🏆 Olimpiadalar') {
                $this->olympiadHandler->showOlympiads($chatId, $telegramId);
                return;
            }
            if ($text === '📊 Natijlarim') {
                $this->telegram->sendMessage($chatId, "📊 Natijlarim. Tez orada bu yerda natijalar ro'yxati ko'rinadi.");
                return;
            }
            if ($text === '💳 To\'lovlar') {
                $this->telegram->sendMessage($chatId, "💳 To'lovlar. Tez orada bu yerda to'lovlar ro'yxati ko'rinadi.");
                return;
            }
            if ($text === '🎫 Biletlar') {
                $this->telegram->sendMessage($chatId, "🎫 Biletlar. Tez orada bu yerda chiptalar ro'yxati ko'rinadi.");
                return;
            }
            if ($text === '👤 Profil') {
                $this->sendProfileContent($chatId, $telegramId);
                return;
            }
            if ($text === 'ℹ️ Tashkilot haqida') {
                $this->telegram->sendMessage($chatId, "ℹ️ Tashkilot haqida ma'lumot. Tez orada bu yerda batafsil ma'lumot bo'ladi.");
                return;
            }
        }

        $this->registrationHandler->handleMessage($message);
    }

    protected function sendProfileContent(int|string $chatId, int|string $telegramId): void
    {
        $user = \App\Models\User::where('telegram_id', $telegramId)->first();
        if ($user === null) {
            $this->telegram->sendMessage($chatId, "Profil topilmadi. Avval ro'yxatdan o'ting.");
            return;
        }
        $region = $user->region;
        $district = $user->district;
        $subjects = $user->subjects->pluck('name')->join(', ') ?: '—';
        $text = "👤 <b>Profil</b>\n\n";
        $text .= "Ism: {$user->first_name}\n";
        $text .= "Familiya: " . ($user->last_name ?? '—') . "\n";
        $text .= "Telefon: {$user->phone}\n";
        $text .= "Viloyat: " . ($region?->name_uz ?? '—') . "\n";
        $text .= "Tuman: " . ($district?->name_uz ?? '—') . "\n";
        $text .= "Maktab: " . ($user->school ?? '—') . "\n";
        $text .= "Sinf: " . ($user->grade ? $user->grade . '-sinf' : '—') . "\n";
        $text .= "Fanlar: {$subjects}";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "✏️ Hamma ma'lumotlarni yangilash", 'callback_data' => 'profile_edit_all']],
            ],
        ];
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Handle the /start command.
     */
    protected function handleStartCommand(int|string $chatId, array $message): void
    {
        $this->startHandler->handle($message);
    }

    /**
     * Handle unknown commands.
     */
    protected function handleUnknownCommand(int|string $chatId, string $command, array $message): void
    {
        $this->telegram->sendMessage($chatId, "Unknown command: {$command}");
    }
}

