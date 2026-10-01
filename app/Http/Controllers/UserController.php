<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);

        return Inertia::render('Users', ['users' => User::orderBy('name')->get(['id', 'name', 'email', 'role', 'active'])]);
    }

    public function save(Request $r, ?User $user = null)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)], 'role' => 'required|in:admin,agent', 'active' => 'required|boolean', 'password' => [$user ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()]]);
        if ($user?->id === $r->user()->id && (! $data['active'] || $data['role'] !== 'admin')) {
            return back()->withErrors(['role' => 'Den eigenen Administratorzugang kannst du hier nicht deaktivieren.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user) {
            $user->update($data);
        } else {
            User::create($data);
        }

        return back()->with('success', 'Bearbeiter gespeichert.');
    }
}
