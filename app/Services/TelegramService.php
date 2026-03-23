<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SplFileInfo;

class TelegramService
{
    public function __construct(
        protected ?string $botToken = null,
    ) {
        $this->botToken ??= config('telegram.bot_token');

        if (blank($this->botToken)) {
            throw new InvalidArgumentException('Telegram bot token is not configured.');
        }
    }

    public function sendLocation(string|int $chatId, float $latitude, float $longitude): Response
    {
        $payload = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        return $this->post('sendLocation', $payload);
    }

    public function sendMessage(string|int $chatId, string $text, ?array $keyboard = null): Response
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard !== null) {
            $payload['reply_markup'] = $keyboard;
        }

        return $this->post('sendMessage', $payload);
    }

    public function sendPhoto(
        string|int $chatId,
        string|SplFileInfo|File|UploadedFile $photo,
        ?string $caption = null,
    ): Response {
        $payload = [
            'chat_id' => $chatId,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        if ($this->shouldUploadFile($photo)) {
            return $this->client()
                ->attach(
                    'photo',
                    fopen($this->photoPath($photo), 'r'),
                    $this->photoName($photo),
                )
                ->post('sendPhoto', $payload)
                ->throw();
        }

        $payload['photo'] = $photo;

        return $this->post('sendPhoto', $payload);
    }

    /**
     * Send a photo from raw binary data (e.g. in-memory PNG) without saving to disk.
     */
    public function sendPhotoFromBinary(
        string|int $chatId,
        string $binaryData,
        string $filename = 'ticket.png',
        ?string $caption = null,
    ): Response {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $binaryData);
        rewind($stream);

        $request = $this->client()->attach('photo', $stream, $filename);

        $payload = ['chat_id' => $chatId];
        if ($caption !== null) {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        return $request->post('sendPhoto', $payload)->throw();
    }

    public function sendVideo(
        string|int $chatId,
        string|SplFileInfo|File|UploadedFile $video,
        ?string $caption = null,
    ): Response {
        $payload = ['chat_id' => $chatId];

        if ($caption !== null) {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        $path = is_string($video) ? $video : ($video->getRealPath() ?: $video->getPathname());
        $name = is_string($video) ? basename($video) : $video->getFilename();

        return $this->client()
            ->attach('video', fopen($path, 'r'), $name)
            ->post('sendVideo', $payload)
            ->throw();
    }

    public function sendDocument(
        string|int $chatId,
        string $filePath,
        ?string $caption = null,
    ): Response {
        $payload = ['chat_id' => $chatId];

        if ($caption !== null) {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        return $this->client()
            ->attach('document', fopen($filePath, 'r'), basename($filePath))
            ->post('sendDocument', $payload)
            ->throw();
    }

    public function editMessageText(
        string|int $chatId,
        int $messageId,
        string $text,
        ?array $inlineKeyboard = null,
    ): Response {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }

        return $this->post('editMessageText', $payload);
    }

    public function answerCallback(string $callbackId, ?string $text = null): Response
    {
        $payload = [
            'callback_query_id' => $callbackId,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->post('answerCallbackQuery', $payload);
    }

    public function deleteMessage(string|int $chatId, int $messageId): Response
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return $this->post('deleteMessage', $payload);
    }

    public function sendInvoice(
        string|int $chatId,
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices,
        ?string $startParameter = null,
        array $options = [],
    ): Response {
        $requestPayload = array_merge($options, [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => $prices,
        ]);

        if ($startParameter !== null) {
            $requestPayload['start_parameter'] = $startParameter;
        }

        return $this->post('sendInvoice', $requestPayload);
    }

    protected function post(string $method, array $payload): Response
    {
        return $this->client()
            ->post($method, $payload)
            ->throw();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl("https://api.telegram.org/bot{$this->botToken}")
            ->acceptJson();
    }

    protected function shouldUploadFile(string|SplFileInfo|File|UploadedFile $photo): bool
    {
        return ! is_string($photo) || is_file($photo);
    }

    protected function photoPath(string|SplFileInfo|File|UploadedFile $photo): string
    {
        if (is_string($photo)) {
            return $photo;
        }

        return $photo->getRealPath() ?: $photo->getPathname();
    }

    protected function photoName(string|SplFileInfo|File|UploadedFile $photo): string
    {
        if (is_string($photo)) {
            return basename($photo);
        }

        return $photo->getFilename();
    }
}
