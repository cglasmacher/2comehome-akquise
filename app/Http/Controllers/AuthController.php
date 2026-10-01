<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function create()
    {
        return Inertia::render('Login');
    }

    public function store(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Zu viele Versuche. Bitte in einer Minute erneut versuchen.']);
        }
        if (! Auth::attempt([...$data, 'active' => true], $r->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Die Anmeldedaten stimmen nicht.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
