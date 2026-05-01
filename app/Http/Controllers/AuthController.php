<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(): RedirectResponse
    {
        $user = Socialite::driver('google')->stateless()->user();

        if ($user->getEmail() !== config('services.google.allowed_email')) {
            abort(403, 'Unauthorized');
        }

        session([
            'auth.user' => [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatar(),
            ],
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(): RedirectResponse
    {
        session()->forget('auth.user');

        return redirect()->route('login');
    }
}
