<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClickPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClickController extends Controller
{
    public function __construct(
        private readonly ClickPaymentService $clickService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $result = $this->clickService->handleRequest($request);

        return response()->json($result);
    }
}
