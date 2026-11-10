<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View { return view('auth.login'); }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (!Auth::attempt($data + ['is_active'=>true])) {
            return back()->withErrors(['email'=>'Invalid credentials or inactive account.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at'=>now()])->saveQuietly();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
