<?php

namespace App\Services;

use App\Models\BotSession;

class BotSessionService
{
    public const STATE_IDLE = 'IDLE';

    public function getSession(int|string $telegramId): BotSession
    {
        return BotSession::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'state' => self::STATE_IDLE,
                'data' => [],
            ],
        );
    }

    public function setState(int|string $telegramId, string $state): BotSession
    {
        $session = $this->getSession($telegramId);
        $session->state = $state;
        $session->save();

        return $session;
    }

    public function setData(int|string $telegramId, string $key, mixed $value): BotSession
    {
        $session = $this->getSession($telegramId);

        $data = $session->data ?? [];
        $data[$key] = $value;

        $session->data = $data;
        $session->save();

        return $session;
    }

    public function getData(int|string $telegramId): array
    {
        $session = BotSession::where('telegram_id', $telegramId)->first();

        return $session?->data ?? [];
    }

    public function clear(int|string $telegramId): void
    {
        BotSession::where('telegram_id', $telegramId)->delete();
    }
}

