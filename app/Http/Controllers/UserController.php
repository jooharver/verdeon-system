<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    // ==========================
    // ADMIN LIST USERS
    // ==========================
    public function index()
    {
        return response()->json(
            User::latest()->get()
        );
    }

    // ==========================
    // ADMIN CREATE USER
    // ==========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:4',
            'role' => 'required|in:issuer,auditor,admin'
        ]);

        $user = User::create([
            'name'=>$request->name,
            'email'=>$request->email,
            'password'=>Hash::make($request->password),
            'role'=>$request->role
        ]);

        return response()->json([
            'message'=>'User created by admin',
            'user'=>$user
        ]);
    }

    // ==========================
    // ADMIN DELETE USER
    // ==========================
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            'message'=>'User deleted'
        ]);
    }
}