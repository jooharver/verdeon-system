<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'issuer' // FORCE ISSUER
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Issuer registered successfully',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        // delete old tokens (optional)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login success',
            'user' => $user,
            'token' => $token
        ]);
    }
    public function updateWallet(Request $request)
    {
        // Validasi ketat format Wallet Ethereum/Polygon
        $request->validate([
            'wallet_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/i']
        ], [
            'wallet_address.regex' => 'Format Wallet Address tidak valid! Harus berformat Web3 (0x...).',
            'wallet_address.required' => 'Wallet Address tidak boleh kosong.'
        ]);

        $user = Auth::user();

        // Cek apakah wallet sudah dipakai user lain
        $existingUser = \App\Models\User::where('wallet_address', $request->wallet_address)
                                        ->where('id', '!=', $user->id)
                                        ->first();
        
        if ($existingUser) {
            return response()->json([
                'message' => 'Wallet Address ini sudah tertaut dengan akun Verdeon lain.'
            ], 422);
        }

        // Simpan ke database
        $user->wallet_address = $request->wallet_address;
        $user->save();

        return response()->json([
            'message' => 'Wallet Address berhasil ditautkan.',
            'user' => $user // Mengembalikan object user agar sinkron dengan struktur AuthContext.js
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // hanya issuer & auditor
        if (!in_array($user->role, ['issuer','auditor'])) {
            return response()->json([
                'message' => 'Only issuer or auditor can update profile'
            ],403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'password' => 'nullable|min:6|confirmed'
        ]);

        // update name
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        // update password
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // EMAIL TIDAK DIUPDATE
        // sengaja tidak kita ambil dari request

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout success'
        ]);
    }

    /**
     * CURRENT USER
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}