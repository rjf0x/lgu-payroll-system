<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if ($user !== null && ! $user->isActive()) {
            $this->auditLogger->log(
                'LOGIN_BLOCKED',
                'Authentication',
                $user->user_id,
                newValues: ['username' => $credentials['username']],
            );

            return $this->failedLogin();
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return $this->failedLogin();
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Return a deliberately generic error to avoid revealing which usernames exist.
     */
    private function failedLogin(): RedirectResponse
    {
        return back()
            ->withErrors(['username' => 'These credentials do not match our records.'])
            ->onlyInput('username');
    }
}
