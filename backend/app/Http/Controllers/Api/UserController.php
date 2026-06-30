<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function userFields(): array
    {
        return ['id', 'name', 'email', 'role', 'is_active', 'avatar', 'created_at'];
    }

    private function avatarUrl(?string $path): ?string
    {
        return $path ? url('uploads/avatars/' . basename($path)) : null;
    }

    private function formatUser(User $u): array
    {
        return array_merge($u->only($this->userFields()), [
            'avatar_url' => $this->avatarUrl($u->avatar),
        ]);
    }

    public function index(Request $request)
    {
        $cid = $request->user()->company_id;
        return response()->json(
            User::where('company_id', $cid)->orderBy('name')->get($this->userFields())
                ->map(fn ($u) => $this->formatUser($u))
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

        return response()->json($this->formatUser($user), 201);
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
        return response()->json($this->formatUser($user));
    }

    public function uploadAvatar(Request $request, User $user)
    {
        if ($user->company_id !== $request->user()->company_id) abort(403);
        $request->validate(['avatar' => 'required|image|max:2048']);

        $dir = public_path('uploads/avatars');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Remove old file
        if ($user->avatar) {
            $old = public_path('uploads/avatars/' . basename($user->avatar));
            if (file_exists($old)) unlink($old);
        }

        $file     = $request->file('avatar');
        $filename = $user->id . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        $user->update(['avatar' => $filename]);

        return response()->json(['avatar_url' => $this->avatarUrl($filename)]);
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
