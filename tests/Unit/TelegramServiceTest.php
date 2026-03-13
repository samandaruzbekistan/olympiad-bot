<?php

namespace Tests\Unit;

use App\Services\TelegramService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_it_sends_requests_to_telegram_bot_api(): void
    {
        config()->set('telegram.bot_token', 'test-token');

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $service = new TelegramService();

        $service->sendMessage(123456, 'Hello', ['inline_keyboard' => [[['text' => 'Tap', 'callback_data' => 'tap']]]]);
        $service->sendPhoto(123456, 'https://example.com/photo.jpg', 'Caption');
        $service->answerCallback('callback-id', 'Done');
        $service->sendInvoice(
            123456,
            'Premium',
            'Premium subscription',
            'invoice-payload',
            'provider-token',
            'USD',
            [['label' => 'Premium', 'amount' => 999]],
            'premium-start',
            ['need_email' => true],
        );

        Http::assertSentCount(4);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && $request['chat_id'] === 123456
                && $request['text'] === 'Hello'
                && $request['reply_markup'] === ['inline_keyboard' => [[['text' => 'Tap', 'callback_data' => 'tap']]]];
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendPhoto'
                && $request['chat_id'] === 123456
                && $request['photo'] === 'https://example.com/photo.jpg'
                && $request['caption'] === 'Caption';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.telegram.org/bottest-token/answerCallbackQuery'
                && $request['callback_query_id'] === 'callback-id'
                && $request['text'] === 'Done';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendInvoice'
                && $request['chat_id'] === 123456
                && $request['title'] === 'Premium'
                && $request['provider_token'] === 'provider-token'
                && $request['currency'] === 'USD'
                && $request['prices'] === [['label' => 'Premium', 'amount' => 999]]
                && $request['start_parameter'] === 'premium-start'
                && $request['need_email'] === true;
        });
    }
}
