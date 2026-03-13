<?php

namespace App\Http\Controllers;

use App\Services\BotManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected BotManager $botManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $update = $request->json()->all();

        $this->botManager->handle($update);

        return response()->json(['ok' => true]);
    }
}
