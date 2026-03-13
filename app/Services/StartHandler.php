<?php

namespace App\Services;

class StartHandler
{
    public function __construct(
        protected TelegramService $telegram,
        protected BotSessionService $sessions,
    ) {
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $telegramId = $message['from']['id'] ?? $chatId;

        if ($chatId === null || $telegramId === null) {
            return;
        }

        $text = "Assalomu alaykum!\nImel Olympiads platformasiga xush kelibsiz.";

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

        // Ensure session exists; actual state transitions will be handled later.
        $this->sessions->getSession($telegramId);
    }
}

