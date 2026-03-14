<?php

namespace App\Services;

class BotManager
{
    public function __construct(
        protected TelegramService $telegram,
        protected StartHandler $startHandler,
        protected RegistrationHandler $registrationHandler,
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

        if ($callbackId === null) {
            return;
        }

        $this->telegram->answerCallback($callbackId);
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
     * Handle non-command text messages.
     */
    protected function handlePlainMessage(int|string $chatId, string $text, array $message): void
    {
        $this->registrationHandler->handleMessage($message);
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

