<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use App\Models\User;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        // FIX: Ganti dari 'admin.auth.forgot-password' ke 'auth.forgot-password'
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        // Check if user has admin role
        $user = User::where('email', $request->email)->first();
        
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Receptionist', 'Cashier'])) {
            return back()->withErrors(['email' => 'This email is not associated with an admin account.']);
        }

        // We will send the password reset link to this user
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', 'Password reset link has been sent to your email!')
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }
}