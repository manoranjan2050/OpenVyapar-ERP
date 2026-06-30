<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        activity('auth')
            ->causedBy($user)
            ->withProperties(['company_id' => $user->company_id, 'ip' => $request->ip()])
            ->event('login')
            ->log('User logged in');

        return response()->json([
            'token'               => $token,
            'user'                => $user->load('company', 'branch')->append([]),
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('company', 'branch');
        return response()->json([
            'user'                => $user,
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password'            => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        activity('auth')
            ->causedBy($user)
            ->event('password_changed')
            ->log('User changed password');

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
