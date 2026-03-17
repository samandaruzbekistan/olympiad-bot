<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClickPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ClickPaymentService $clickPaymentService,
    ) {
    }

    public function handleClick(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'click_trans_id' => ['required', 'string'],
            'service_id' => ['required'],
            'merchant_trans_id' => ['required_without:transaction_param'],
            'transaction_param' => ['required_without:merchant_trans_id'],
            'amount' => ['required', 'numeric'],
            'action' => ['required', 'integer'],
            'sign_time' => ['required'],
            'sign_string' => ['required_without:signature', 'string'],
            'signature' => ['required_without:sign_string', 'string'],
            'error' => ['nullable', 'integer'],
            'error_note' => ['nullable', 'string'],
            'merchant_prepare_id' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'click_trans_id' => (string) $request->input('click_trans_id'),
                'merchant_trans_id' => (string) $request->input('merchant_trans_id', $request->input('transaction_param')),
                'error' => ClickPaymentService::ERROR_REQUEST_FROM_INSIDE,
                'error_note' => $validator->errors()->first(),
            ]);
        }

        if ((string) $request->input('service_id') !== (string) config('services.click.service_id')) {
            return response()->json([
                'click_trans_id' => (string) $request->input('click_trans_id'),
                'merchant_trans_id' => (string) $request->input('merchant_trans_id', $request->input('transaction_param')),
                'error' => ClickPaymentService::ERROR_REQUEST_FROM_INSIDE,
                'error_note' => 'Invalid service ID.',
            ], 200);
        }

        $response = $this->clickPaymentService->handleRequest($request);

        return response()->json($response);
    }
}
