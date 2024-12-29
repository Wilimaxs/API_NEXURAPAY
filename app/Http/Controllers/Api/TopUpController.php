<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\callback_midtran;

class TopUpController extends Controller
{
    private $serverKey;
    private $isProduction;
    private $baseUrl;

    public function __construct()
    {
        $this->serverKey = env('MIDTRANS_SERVER_KEY');
        $this->isProduction = env('MIDTRANS_IS_PRODUCTION');
        $this->baseUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function createTransaction(Request $request)
    {
        try {
            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|string',
                'gross_amount' => 'required|numeric|min:1',
                'first_name' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare transaction details for Midtrans
            $transactionDetails = [
                'transaction_details' => [
                    'order_id' => $request->order_id,
                    'gross_amount' => $request->gross_amount,
                ],
                'customer_details' => [
                    'first_name' => $request->first_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                ],
                'custom_field1' => $request->phone,
                'item_details' => [
                    [
                        'id' => 'ITEM01',
                        'price' => $request->gross_amount,
                        'quantity' => 1,
                        'name' => 'Top Up'
                    ]
                ],
            ];

            $user = $request->user();

            // Make request to Midtrans
            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl, $transactionDetails);

            // Check if the request was successful
            if ($response->successful()) {
                // Store transaction in database
                $transaction = Topup::create([
                    'hp' => $request->phone,
                    'order_id' => $request->order_id,
                    'amount' => $request->gross_amount,
                    'status' => 'pending',
                    'payment_type' => 'midtrans',
                    'midtrans_response' => $response->json(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaction created successfully',
                    'data' => [
                        'token' => $response->json('token'),
                        'redirect_url' => $response->json('redirect_url'),
                        'transaction_id' => $transaction->id,
                    ]
                ]);
            }

            // Handle error response from Midtrans
            Log::error('Midtrans Error', [
                'request' => $transactionDetails,
                'response' => $response->json()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction',
                'errors' => $response->json()
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Transaction Creation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the transaction',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }



    public function handle(Request $request)
    {
        try {
            $notification = $request->all();

            // Verify signature key
            $signatureKey = $notification['signature_key'] ?? '';
            $orderId = $notification['order_id'] ?? '';
            $statusCode = $notification['status_code'] ?? '';
            $grossAmount = $notification['gross_amount'] ?? '';
            $serverKey = config('midtrans.server_key');

            $mySignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

            if ($signatureKey !== $mySignatureKey) {
                throw new \Exception('Invalid signature key');
            }

            // Create callback record
            callback_midtran::create([
                'order_id' => $orderId,
                'transaction_status' => $notification['transaction_status'] ?? null,
                'status_code' => $statusCode,
                'gross_amount' => $grossAmount,
                'payment_type' => $notification['payment_type'] ?? null,
                'transaction_id' => $notification['transaction_id'] ?? null,
                'fraud_status' => $notification['fraud_status'] ?? null,
                'raw_response' => $notification
            ]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Midtrans Callback Error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
