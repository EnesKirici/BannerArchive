<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ip = $request->ip();
        $lockoutKey = 'login_lockout_'.$ip;
        $attemptsKey = 'login_attempts_'.$ip;

        if (Cache::has($lockoutKey)) {
            $seconds = Cache::get($lockoutKey) - time();

            throw ValidationException::withMessages([
                'email' => ["Çok fazla başarısız deneme. Lütfen {$seconds} saniye bekleyin."],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            if ($user) {
                LoginHistory::create([
                    'user_id' => $user->id,
                    'ip_address' => $ip,
                    'user_agent' => $request->userAgent(),
                    'success' => false,
                ]);
            }

            $attempts = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            if ($attempts >= 5) {
                $lockoutSeconds = min(60 * $attempts, 900);
                Cache::put($lockoutKey, time() + $lockoutSeconds, $lockoutSeconds);
                Cache::forget($attemptsKey);

                throw ValidationException::withMessages([
                    'email' => ["Çok fazla başarısız deneme. {$lockoutSeconds} saniye boyunca kilitlendiniz."],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['Giriş bilgileri hatalı.'],
            ]);
        }

        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);

        Auth::login($user, $request->boolean('remember'));

        $user->update([
            'last_login_at' => now(),
            'last_ip_address' => $ip,
            'login_count' => $user->login_count + 1,
        ]);

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
            'success' => true,
        ]);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
