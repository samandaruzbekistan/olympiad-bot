<?php

namespace App\Http\Controllers;

use App\Services\BotManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected BotManager $botManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $update = $request->json()->all();
            $this->botManager->handle($update);
        } catch (\Throwable $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
