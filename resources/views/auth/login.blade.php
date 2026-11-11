@extends('layouts.app')
@section('title', 'Log in — Aadi Marketing OS')
@section('content')
<div class="card"><h1>Sign in</h1><p class="muted">Accounts are provisioned by an administrator. Public registration is disabled.</p>
<form method="post" action="{{ route('login.attempt') }}">@csrf
<label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required>
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<p><button type="submit">Sign in</button></p></form></div>
@endsection
