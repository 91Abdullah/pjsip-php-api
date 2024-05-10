<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function newLogin(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = DB::table('ps_auths')->where('id', $credentials['username'])->first();

        if ($user) {
            if ($credentials['password'] == $user->password) {
                Cookie::queue('username', $credentials['username'], 60);
                return response()->json(['message' => 'Welcome, user!']);
            }
        } elseif ($credentials['username'] == 'admin') {
            if ($credentials['password'] == env('ADMIN_PASS')) {
                Cookie::queue('username', 'admin', 60);
                return response()->json(['message' => 'Welcome, admin!']);
            }
        }

        throw ValidationException::withMessages([
            'username' => ['The provided credentials are incorrect.'],
        ]);
    }
}
