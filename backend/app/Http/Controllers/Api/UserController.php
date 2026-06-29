<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        return response()->json(
            User::where('company_id', $cid)->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $cid = $request->user()->company_id;
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|min:6',
            'role'     => ['required', Rule::in(['admin', 'accountant', 'cashier', 'viewer'])],
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role'       => $data['role'],
            'company_id' => $cid,
            'is_active'  => true,
        ]);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']), 201);
    }

    public function update(Request $request, User $user)
    {
        $cid = $request->user()->company_id;
        if ($user->company_id !== $cid) abort(403);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'role'      => ['sometimes', Rule::in(['admin', 'accountant', 'cashier', 'viewer'])],
            'is_active' => 'sometimes|boolean',
            'password'  => 'sometimes|min:6',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']));
    }

    public function destroy(Request $request, User $user)
    {
        $cid = $request->user()->company_id;
        if ($user->company_id !== $cid) abort(403);
        if ($user->id === $request->user()->id) return response()->json(['message' => 'Cannot delete your own account.'], 422);
        $user->delete();
        return response()->json(['message' => 'User deleted.']);
    }
}
