<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * 
     * Profile User reseller
     * 
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            return new PostResource(true, 'Data User Ditemukan', $user);
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal Mengambil Data User', $e->getMessage());
        }
    }



    /**
     * 
     * update Data User reseller
     * 
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            // validalidation input
            $validator = Validator::make($request->all(), [
                'current_password' => 'required_with:new_password|current_password',
                'new_password' => 'sometimes|min:10',
                'name' => 'sometimes|string|max:20',
                'address' => 'sometimes|',
                'hp' => 'sometimes',
                'no_rekening' => 'sometimes',
                'ktp_image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'selfi_image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // check validator id fails
            if ($validator->fails()) {
                return Response()->json($validator->errors(), 422);
            }

            // update fields
            $updateData = $validator->validated();

            // handle ktp image
            if ($request->hasFile('ktp_image')) {
                Storage::disk('public')->delete($user->ktp_image);
                $updateData['ktp_image'] = $request->file('ktp_image')->store('auth/register/ktp', 'public');
            }

            // handle selfi image
            if ($request->hasFile('selfi_image')) {
                Storage::disk('public')->delete($user->selfi_image);
                $updateData['selfi_image'] = $request->file('selfi_image')->store('auth/register/selfi', 'public');
            }

            // handle password if there are new_password
            if (isset($updateData['new_password'])) {
                $updateData['password'] = Hash::make($updateData['new_password']);
                unset($updateData['new_password'], $updateData['current_password']);
            }

            $user->update($updateData);
            return new PostResource(true, 'Update Data Berhasil', $user);
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal Update Data', $e->getMessage());
        }
    }



    /**
     * 
     * check balance reseller
     * 
     */
    public function checkBalance(Request $request)
    {
        try {
            // take balance from user relationship
            $balance = $request->user()->balance;
            // check balance
            if (!$balance) {
                return response()->json([
                    'status' => false,
                    'message' => 'Saldo Tidak Ditemukan',
                ], 404);
            }
            // return balance 
            return new PostResource(true, 'Saldo Ditemukan', ['balance' => $balance->amount]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal Cek Saldo', $e->getMessage());
        }
    }

    /**
     * 
     * Update Data User Member
     * 
     */
    public function updateMember(Request $request)
    {
        try {
            $user = $request->user();
            // validalidation input
            $validator = Validator::make($request->all(), [
                'current_password' => 'required_with:new_password|current_password',
                'new_password' => 'sometimes|min:10',
                'name' => 'sometimes|string|max:20',
                'address' => 'sometimes|',
                'phone' => 'sometimes',
            ]);

            // check validator id fails
            if ($validator->fails()) {
                return Response()->json($validator->errors(), 422);
            }

            // update fields
            $updateData = $validator->validated();

            // handle password if there are new_password
            if (isset($updateData['new_password'])) {
                $updateData['password'] = Hash::make($updateData['new_password']);
                unset($updateData['new_password'], $updateData['current_password']);
            }

            $user->update($updateData);
            return new PostResource(true, 'Update Data Berhasil', $user);
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal Update Data', $e->getMessage());
        }
    }


    public function deleteAccount(Request $request)
    {

        DB::beginTransaction();
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            // Hapus personal access tokens
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', get_class($user))
                ->delete();

            // hapus user
            DB::table('users')
                ->where('id', $user->id)
                ->delete();

            // hapus money 
            DB::table('balances')
                ->where('user_id', $user->id)
                ->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Akun berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus akun',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
