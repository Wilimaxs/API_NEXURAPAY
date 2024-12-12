<?php

namespace App\Http\Controllers\Api;

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
            'fr_balancejual' => 'sometimes',
            'last_balancejual' => 'sometimes',
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

            // Create user
            $transaction = transaction::create([
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
}
