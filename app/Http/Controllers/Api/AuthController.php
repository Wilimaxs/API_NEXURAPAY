<?php

namespace App\Http\Controllers\Api;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{


    /**
     * 
     * register funcition reseller
     * 
     */
    public function register(Request $request)
    {
        // Validation input 
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:10',
            'name' => 'required|string|max:20',
            'address' => 'required',
            'hp' => 'required',
            'no_rekening' => 'required',
            'ktp_image' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'selfi_image' => 'required|image|mimes:jpeg,jpg,png|max:2048',
        ]);
        // check validator if fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            //upload KTP image to storage public
            if ($request->hasFile('ktp_image')) {
                $ktpPath = $request->file('ktp_image')->store('auth/register/ktp', 'public');
            }

            // upload selfi image to storage public
            if ($request->hasFile('selfi_image')) {
                $selfiPath = $request->file('selfi_image')->store('auth/register/selfi', 'public');
            }

            $plainKey = Str::random(50);

            // Create user
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'hp' => $request->hp,
                'key_reseller' => $plainKey,
                'address' => $request->address,
                'no_rekening' => $request->no_rekening,
                'ktp_image' => $ktpPath,
                'selfi_image' => $selfiPath,

            ]);

            // create token
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken;
            // set expires_at manually
            $tokenResult->accessToken->expires_at = now()->addMinutes(config('sanctum.expiration'));
            $tokenResult->accessToken->save();

            // return response or resouce
            return new AuthResource(true, 'Data Akun Berhasil DItambahkan', $token, $user);
        } catch (\Exception $e) {
            if (isset($ktpPath)) Storage::disk('public')->delete($ktpPath);
            if (isset($selfiePath)) Storage::disk('public')->delete($selfiePath);

            // return respose or resouce
            return new AuthResource(true, 'Registrasi Gagal', null, $e->getMessage());
        }
    }



    /**
     * 
     * Login reseller
     * 
     */
    public function login(Request $request)
    {
        // validation input 
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // check validation if fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // cek credentials login
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Credential',
            ], 422);
        }

        // take email login
        $user = User::where('email', $request->email)->firstOrFail();

        // Hapus semua token lama 
        $user->tokens()->delete();

        // Buat token baru dengan tanggal kedaluwarsa 
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken;

        // Perbarui nilai expires_at secara manual 
        $tokenResult->accessToken->expires_at = now()->addMinutes(config('sanctum.expiration'));
        $tokenResult->accessToken->save();

        return new AuthResource(true, 'login Sukses', $token, $user);
    }


    /**
     * 
     * register funcition member
     * 
     */
    public function registerMember(Request $request)
    {
        // Validation input 
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:members,email,',
            'password' => 'required|min:10',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string'
        ]);
        // check validator if fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {

            // Create user
            $user = Member::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
            ]);

            // create token
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken;
            // set expires_at manually
            $tokenResult->accessToken->expires_at = now()->addMinutes(config('sanctum.expiration'));
            $tokenResult->accessToken->save();

            // return response or resouce
            return new AuthResource(true, 'Data Akun Berhasil DItambahkan', $token, $user);
        } catch (\Exception $e) {
            if (isset($ktpPath)) Storage::disk('public')->delete($ktpPath);
            if (isset($selfiePath)) Storage::disk('public')->delete($selfiePath);

            // return respose or resouce
            return new AuthResource(true, 'Registrasi Gagal', null, $e->getMessage());
        }
    }


    /**
     * 
     * Login member
     * 
     */
    public function loginMember(Request $request)
    {
        // validation input 
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // check validation if fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // cek credentials login
        if (!Auth::guard('members')->attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Credential',
            ], 422);
        }

        // take email login
        $user = Member::where('email', $request->email)->firstOrFail();

        // Hapus semua token lama 
        $user->tokens()->delete();

        // Buat token baru dengan tanggal kedaluwarsa 
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken;

        // Perbarui nilai expires_at secara manual 
        $tokenResult->accessToken->expires_at = now()->addMinutes(config('sanctum.expiration'));
        $tokenResult->accessToken->save();

        return new AuthResource(true, 'login Sukses', $token, $user);
    }

    /**
     * Log Out user
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Hapus token pengguna yang sedang login
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out',
        ], 200);
    }
}
