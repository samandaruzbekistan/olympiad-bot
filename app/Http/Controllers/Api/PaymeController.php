<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymeException;
use App\Services\PaymePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymeController extends Controller
{
    public function __construct(
        private readonly PaymePaymentService $paymePaymentService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        Log::info('Payme callback received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $payload = $request->json()->all();
        $id = $payload['id'] ?? null;

        try {
            $this->paymePaymentService->authorize($request->header('Authorization'));

            $method = $payload['method'] ?? null;
            if (! is_string($method) || $method === '') {
                throw new PaymeException(-32600, 'Invalid Request.');
            }

            $params = $payload['params'] ?? [];
            if (! is_array($params)) {
                throw new PaymeException(-32602, 'Invalid params.');
            }

            $result = $this->paymePaymentService->dispatch($method, $params);

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ]);
        } catch (PaymeException $exception) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Payme callback processing failed', [
                'payload' => $payload,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32400,
                    'message' => 'System error.',
                ],
            ]);
        }
    }
}
