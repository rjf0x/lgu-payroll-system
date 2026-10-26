@extends('layouts.app')
@section('title', 'Sign in · LGU Payroll')
@section('content')
    <section class="login-panel">
        <p class="eyebrow">Your payroll workspace</p>
        <h1>Welcome back.</h1>
        <p class="login-description">Sign in with the account provided by your administrator.</p>
        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="{{ is_string(old('username')) ? old('username') : '' }}" required minlength="3" maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus placeholder="Enter your username" @if ($errors->has('username')) aria-invalid="true" @endif>
                <x-ui.field-error field="username" />
            </div>
            <div>
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required maxlength="72" autocomplete="current-password" placeholder="Enter your password" @if ($errors->has('password')) aria-invalid="true" @endif>
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-controls="password" aria-label="Show password" title="Show password" aria-pressed="false" hidden>
                        <x-ui.icon name="eye" data-password-show />
                        <x-ui.icon name="eye-off" data-password-hide hidden />
                    </button>
                </div>
                <x-ui.field-error field="password" />
            </div>
            <button class="button button-primary login-submit">Sign in<x-ui.icon name="arrow" /></button>
        </form>
        <p class="login-help">Need an account or a password reset?<br><strong>Contact your system administrator.</strong></p>
    </section>
@endsection
