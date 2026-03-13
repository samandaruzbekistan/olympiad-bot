<?php

namespace App\Services;

use App\Models\BotSession;

class RegistrationHandler
{
    public const STATE_WAITING_FIRST_NAME = 'WAITING_FIRST_NAME';
    public const STATE_WAITING_LAST_NAME = 'WAITING_LAST_NAME';
    public const STATE_WAITING_PHONE = 'WAITING_PHONE';
    public const STATE_WAITING_REGION = 'WAITING_REGION';

    public function __construct(
        protected TelegramService $telegram,
        protected BotSessionService $sessions,
    ) {
    }

    public function handleCallback(array $callback): void
    {
        $data = $callback['data'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? null;
        $telegramId = $callback['from']['id'] ?? $chatId;

        if ($data !== 'register_start' || $chatId === null || $telegramId === null) {
            return;
        }

        $this->sessions->setState($telegramId, self::STATE_WAITING_FIRST_NAME);

        $this->askFirstName($chatId);
    }

    public function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $telegramId = $message['from']['id'] ?? $chatId;

        if ($chatId === null || $telegramId === null) {
            return;
        }

        /** @var BotSession $session */
        $session = $this->sessions->getSession($telegramId);
        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            return;
        }

        switch ($session->state) {
            case self::STATE_WAITING_FIRST_NAME:
                $this->sessions->setData($telegramId, 'first_name', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_LAST_NAME);

                $this->askLastName($chatId);

                break;

            case self::STATE_WAITING_LAST_NAME:
                $this->sessions->setData($telegramId, 'last_name', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_PHONE);

                $this->askPhone($chatId);

                break;

            case self::STATE_WAITING_PHONE:
                $this->sessions->setData($telegramId, 'phone', $text);
                $this->sessions->setState($telegramId, self::STATE_WAITING_REGION);

                $this->askRegion($chatId);

                break;

            default:
                // Not in registration flow; ignore for now.
                break;
        }
    }

    protected function askFirstName(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Ismingizni kiriting.");
    }

    protected function askLastName(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Familiyangizni kiriting.");
    }

    protected function askPhone(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Telefon raqamingizni kiriting.");
    }

    protected function askRegion(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Viloyatingizni tanlang.");
    }
}

