<?php

namespace App\Http\Controllers\Api;

use App\Models\balance;
use App\Models\transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Callback;
use Exception;


class TrxController extends Controller
{
    /**
     * Summary of transaction
     * @param \Illuminate\Http\Request $request
     * @return mixed|PostResource|\Illuminate\Http\JsonResponse
     */
    public function transaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_hp' => 'required|string',
            'reff' => 'required|string',
            'custno' => 'required|string',
            'product_code' => 'required|string',
            'hjual' => 'required',
            'adm' => 'sometimes',
        ]);

        // check validator id fails
        if ($validator->fails()) {
            return Response()->json($validator->errors(), 422);
        }

        try {
            // Memeriksa apakah pengguna sudah terautentikasi
            if (!Auth::check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: Invalid Token, Khusus Reseller'
                ], 401);
            }

            $user = Auth::user();

            // Pengecekan jika no_hp tidak sama
            if ($user->hp !== $request->no_hp) {
                return response()->json([
                    'status' => false,
                    'message' => 'No HP tidak cocok dengan akun yang terautentikasi'
                ], 403); // 403 Forbidden
            }
            $balance = Balance::where('user_id', $user->id)->first();

            if (!$balance) {
                return new PostResource(false, 'Balance not found', null);
            }

            $amount = $balance->amount;

            if (intval($amount)  < intval($request->hjual)) {
                return new PostResource(false, 'Saldo Tidak Cukup', $amount);
            }

            // for last_balancejual
            $last_balancejual = $amount - floatval($request->hjual);

            // Create transaction
            $transaction = Transaction::create([
                'no_hp' => $request->no_hp,
                'reff' => $request->reff,
                'custno' => $request->custno,
                'product_code' => $request->product_code,
                'hjual' => $request->hjual,
                'adm' => $request->adm,
                'fr_balancejual' => $amount, // Gunakan langsung $amount
                'last_balancejual' => $last_balancejual,
            ]);

            return new PostResource(true, 'Harap Menunggu Pembelian', $transaction);
        } catch (Exception $e) {
            return new PostResource(false, 'Gagal Transaksi', $e->getMessage());
        }
    }

    /**
     * Summary of transactionMember
     * @param \Illuminate\Http\Request $request
     * @return mixed|PostResource|\Illuminate\Http\JsonResponse
     */
    public function transactionMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_hp' => 'required|string',
            'reff' => 'required|string',
            'custno' => 'required|string',
            'product_code' => 'required|string',
            'hjual' => 'required',
            'adm' => 'sometimes',
            'fr_balancejual' => 'required', //current amount
            'last_balancejual' => 'required', //result amount after change with hjual
        ]);

        // check validator id fails
        if ($validator->fails()) {
            return Response()->json($validator->errors(), 422);
        }

        try {

            $user = Auth::user();

            // Pengecekan jika no_hp tidak sama
            if ($user->phone != $request->no_hp) {
                return response()->json([
                    'status' => false,
                    'message' => 'No HP tidak cocok dengan akun yang terautentikasi'
                ], 403); // 403 Forbidden
            }

            // Create transaction
            $transaction = Transaction::create([
                'no_hp' => $request->no_hp,
                'reff' => $request->reff,
                'custno' => $request->custno,
                'product_code' => $request->product_code,
                'hjual' => $request->hjual,
                'adm' => $request->adm,
                'fr_balancejual' => $request->fr_balancejual,
                'last_balancejual' => $request->last_balancejual,
            ]);

            return new PostResource(true, 'Harap Menunggu Pembelian', $transaction);
        } catch (Exception $e) {
            return new PostResource(false, 'Gagal Transaksi', $e->getMessage());
        }
    }


    /**
     * Summary of handleCallback
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        try {
            // Validasi secret
            $secret = env('TRIPAY_CALLBACK_SECRET');
            $incomingSecret = $request->server('HTTP_X_CALLBACK_SECRET') ?: '';

            if (!hash_equals($secret, $incomingSecret)) {
                Log::error('Invalid callback secret received from Tripay');
                return response()->json(['error' => 'Invalid secret'], 403);
            }

            // Ambil dan decode data JSON
            $data = $request->input();

            // Validasi data
            if (!isset($data['api_trxid'])) {
                throw new Exception('Invalid callback data received');
            }

            // Simpan callback ke database
            $callback = Callback::create([
                'trx_id' => $data['trxid'],
                'api_trxid' => $data['api_trxid'],
                'via' => $data['via'],
                'code' => $data['code'],
                'produk' => $data['produk'],
                'target' => $data['target'],
                'mtrpln' => $data['mtrpln'],
                'note' => $data['note'],
                'token' => $data['token'],
                'harga' => $data['harga'],
                'status' => $data['status'],
                'saldo_before_trx' => $data['saldo_before_trx'],
                'saldo_after_trx' => $data['saldo_after_trx'],
                'id_user' => $data['id'] ?? ' ',
                'nama' => $data['nama'] ?? ' ',
                'periode' => $data['periode'] ?? ' ',
                'jumlah_tagihan' => isset($data['tagihan']) && $data['tagihan'] !== null ? (int)$data['tagihan'] : 0,
                'admin' => isset($data['admin']) && $data['admin'] !== null ? $data['admin'] : 0.00,
                'jumlah_bayar' => isset($data['jumlah_bayar']) && $data['jumlah_bayar'] !== null ? $data['jumlah_bayar'] : 0.00,
            ]);

            // Update status transaksi
            $transaction = Transaction::where('id', $data->api_trxid)->first();

            if (!$transaction) {
                Log::warning("Transaction not found: {$data->api_trxid}");
                throw new Exception('Transaction not found');
            }

            // Mapping status
            $status = match ($data->status) {
                '0' => 'pending',
                '1' => 'success',
                '2' => 'failed',
                default => 'pending'
            };

            $transaction->status = $status;
            $transaction->save();

            // Log success
            Log::info('Tripay callback processed successfully', [
                'api_trxid' => $data->api_trxid,
                'status' => $status
            ]);

            return response()->json(['success' => true], 200);
        } catch (Exception $e) {
            // Log error
            Log::error('Error processing Tripay callback: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function handle(Request $request)
    {
        try {
            // Buat folder logs jika belum ada
            if (!file_exists(storage_path('logs'))) {
                mkdir(storage_path('logs'), 0777, true);
            }

            // Debug payload
            Log::info('Tripay Request Headers:', $request->headers->all());
            Log::info('Tripay Raw Content:', ['content' => $request->getContent()]);

            // Validate secret
            $secret = env('TRIPAY_CALLBACK_SECRET');
            $incomingSecret = $request->header('X-Callback-Secret');

            Log::info('Secret Check:', [
                'incoming' => $incomingSecret,
                'expected' => $secret
            ]);

            if (empty($incomingSecret) || !hash_equals($secret, $incomingSecret)) {
                Log::warning('Invalid Secret Received');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Secret'
                ], 403);
            }

            // Parse JSON dengan error handling yang lebih detail
            $content = $request->getContent();
            Log::info('Raw Content:', ['content' => $content]);

            try {
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('JSON decode error: ' . json_last_error_msg());
                }
            } catch (Exception $e) {
                Log::error('JSON Parse Error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload: ' . $e->getMessage()
                ], 400);
            }

            Log::info('Parsed Data:', $data);

            // Find transaction
            $transaction = Transaction::where('id', $data['api_trxid'] ?? null)->first();
            if (!$transaction) {
                Log::warning('Transaction not found:', ['api_trxid' => $data['api_trxid'] ?? null]);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Update status
            $status = match ($data['status'] ?? '0') {
                '1' => 'success',
                '2' => 'failed',
                default => 'pending'
            };

            Log::info('Updating transaction:', [
                'id' => $transaction->id,
                'old_status' => $transaction->status,
                'new_status' => $status
            ]);

            $transaction->update(['status' => $status]);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('Tripay Callback Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkTransactionUser(Request $request)
    {
        try {
            // Get authenticated user
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 401);
            }

            $hp = $user->hp;


            // Get transactions using Query Builder with proper error handling
            $transactions = Transaction::selectRaw('
            CASE 
                WHEN product_prabayars.product_code IS NOT NULL THEN product_prabayars.name
                ELSE product_pascabayars.name
            END AS product_name,
            transactions.hjual,
            transactions.status,
            DATE(transactions.created_at) AS created_at
        ')
                ->join('users', 'transactions.no_hp', '=', 'users.hp')
                ->leftJoin('product_prabayars', 'transactions.product_code', '=', 'product_prabayars.product_code')
                ->leftJoin('product_pascabayars', 'transactions.product_code', '=', 'product_pascabayars.product_code')
                ->where('transactions.no_hp', '=', $hp)  // Menggunakan nomor HP dari token
                ->where(function ($query) {
                    $query->whereNotNull('product_prabayars.product_code')
                        ->orWhereNotNull('product_pascabayars.product_code');
                })
                ->get();


            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => 'Belum Terdapat Transaksi'
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $transactions
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan dalam mengambil data transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
