<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public static function login(Request $request){
         $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->plainTextToken;

        // Set expiration to 2 hours from now
        $tokenResult->accessToken->expires_at = now()->addHours(2);
        $tokenResult->accessToken->save();

        return response()->json([
            'token' => $token,
            'expires_at' => $tokenResult->accessToken->expires_at,
        ]);
    }
}
