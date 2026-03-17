<?php

namespace App\Services;

use App\Models\User;

class StartHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected BotSessionService $sessions,
        protected RegistrationHandler $registrationHandler,
    ) {
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $telegramId = $message['from']['id'] ?? $chatId;

        if ($chatId === null || $telegramId === null) {
            return;
        }

        $this->sessions->getSession($telegramId);

        if (User::where('telegram_id', $telegramId)->exists()) {
            $this->telegram->sendMessage($chatId, "Assalomu alaykum! Asosiy menyu:");
            $this->registrationHandler->showMainMenu($chatId);
            return;
        }

        $text = "Assalomu alaykum!\nOlimpiadalar platformasiga xush kelibsiz.\n\nRo‘yxatdan o‘ting:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => "🚀 Ro'yxatdan o'tish",
                        'callback_data' => 'register_start',
                    ],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }
}

