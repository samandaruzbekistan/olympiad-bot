<?php

namespace App\Services;

class BotManager
{
    public function __construct(
        protected TelegramService $telegram,
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

        $this->handlePlainMessage($chatId, $text, $message);
    }

    /**
     * Handle an incoming callback query.
     */
    public function handleCallback(array $callback): void
    {
        $callbackId = $callback['id'] ?? null;

        if ($callbackId === null) {
            return;
        }

        // Example: acknowledge callback; extend to route based on data.
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
        // Default implementation – customise as needed.
        $this->telegram->sendMessage($chatId, 'Received your message.');
    }

    /**
     * Handle the /start command.
     */
    protected function handleStartCommand(int|string $chatId, array $message): void
    {
        $this->telegram->sendMessage($chatId, 'Welcome! Bot is ready.');
    }

    /**
     * Handle unknown commands.
     */
    protected function handleUnknownCommand(int|string $chatId, string $command, array $message): void
    {
        $this->telegram->sendMessage($chatId, "Unknown command: {$command}");
    }
}

