<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | {{ $systemSettings['system_name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page min-h-dvh bg-slate-50 font-sans antialiased">
    <x-page-loader class="mx-auto max-w-6xl px-4 pt-8 sm:px-8" />
    <main data-page-content class="flex min-h-dvh items-center justify-center px-4 py-8 sm:px-8">
        <div class="w-full max-w-6xl">
            <div class="grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5 lg:grid-cols-[0.95fr_1.05fr]">
                <aside aria-label="Payroll workspace" class="relative hidden flex-col justify-between overflow-hidden bg-brand-strong p-10 text-white lg:flex xl:p-12">
                    <svg class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 text-blue-200/10" viewBox="0 0 400 400" fill="none" aria-hidden="true">
                        <circle cx="200" cy="200" r="190" stroke="currentColor" />
                        <circle cx="200" cy="200" r="140" stroke="currentColor" />
                        <circle cx="200" cy="200" r="90" stroke="currentColor" />
                    </svg>
                    <div class="relative flex items-start gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-white/25 bg-white/10 text-sm font-bold tracking-wide">LGU</span>
                        <div class="min-w-0 pt-0.5">
                            <p class="break-words text-base font-semibold">{{ $systemSettings['system_name'] }}</p>
                            <p class="mt-1 break-words text-sm text-brand-on-dark">{{ $systemSettings['organization_name'] }}</p>
                        </div>
                    </div>

                    <div class="relative py-14">
                        <div class="mb-8 flex h-16 w-16 items-center justify-center rounded-2xl border border-white/20 bg-white/10">
                            <svg class="h-8 w-8" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 4h10l6 6v17a1 1 0 0 1-1 1H9a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                                <path d="M19 4v7h6M12 15h8M12 20h3M12 24h8M19 19l2 2 4-4" />
                            </svg>
                        </div>
                        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-blue-200">One connected workspace</p>
                        <p class="max-w-sm text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">Your people.<br>Your payroll.<br>All in view.</p>
                        <p class="mt-6 max-w-sm text-base leading-7 text-brand-on-dark">Manage employee records, prepare payroll, and keep every approval in view.</p>
                    </div>

                    <div class="relative border-t border-white/20 pt-6">
                        <ol class="flex items-center justify-between gap-3 text-sm text-brand-on-dark" aria-label="Payroll workflow">
                            <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-blue-300" aria-hidden="true"></span>Prepare</li>
                            <li aria-hidden="true" class="h-px flex-1 bg-white/20"></li>
                            <li>Review</li>
                            <li aria-hidden="true" class="h-px flex-1 bg-white/20"></li>
                            <li>Finalize</li>
                        </ol>
                    </div>
                </aside>

                <section aria-labelledby="login-heading" class="flex min-w-0 items-center px-6 py-10 sm:px-12 sm:py-14 lg:px-12 xl:px-16">
                    <div class="mx-auto w-full max-w-sm">
                        <div class="mb-10 flex items-center gap-3 lg:hidden">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-strong text-sm font-bold text-white">LGU</span>
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold">{{ $systemSettings['system_name'] }}</p>
                                <p class="break-words text-sm text-muted">{{ $systemSettings['organization_name'] }}</p>
                            </div>
                        </div>

                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand">Account access</p>
                        <h1 id="login-heading" class="mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Welcome back</h1>
                        <p class="mt-3 text-base leading-7 text-muted">Sign in with your assigned username and password.</p>

                        @if ($errors->any())
                            <div id="error-summary" role="alert" tabindex="-1" aria-labelledby="error-summary-title" data-error-summary class="mt-6 rounded-xl border-2 border-rose-700 bg-rose-50 p-4 text-rose-900">
                                <h2 id="error-summary-title" class="text-base font-bold">Unable to sign in</h2>
                                <p class="mt-1 text-sm">Review the errors below. Select an error to go to its field.</p>
                                <ul class="mt-2 space-y-2 text-sm">
                                    @foreach ($errors->messages() as $field => $messages)
                                        <li><a href="#{{ $field }}" data-error-field="{{ $field }}" class="font-semibold underline underline-offset-4">{{ $messages[0] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" novalidate class="mt-8 space-y-5" data-submit-feedback>
                            @csrf
                            <x-form.input name="username" label="Username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Enter your username" class="h-12" required />

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password <span class="text-rose-700" aria-hidden="true">*</span><span class="sr-only"> (required)</span></label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif class="ui-field h-12 pr-24">
                                    <button id="toggle-password" type="button" hidden aria-label="Show password" aria-controls="password" aria-pressed="false" class="ui-button ui-button-ghost absolute right-1 top-1 min-w-16">
                                        <x-action-icon name="view" />
                                        <span data-button-label>Show</span>
                                    </button>
                                </div>
                                <x-form.feedback name="password" id="password" />
                            </div>

                            <label for="remember" class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-muted">
                                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-5 w-5 shrink-0 rounded border-slate-500 accent-brand">
                                Remember me on this device
                            </label>

                            <x-button icon="login" type="submit" variant="primary" class="w-full" data-submitting-label="Signing in...">
                                Sign in
                            </x-button>
                            <p id="submission-status" role="status" class="sr-only"></p>
                        </form>

                        <div class="mt-8 border-t border-slate-200 pt-6 text-sm leading-6 text-muted">
                            <p class="font-medium text-ink">Need help signing in?</p>
                            @if ($systemSettings['support_email'])
                                <a href="mailto:{{ $systemSettings['support_email'] }}" class="inline-flex min-h-11 items-center break-all font-semibold text-brand underline underline-offset-4">Contact support</a>
                            @else
                                <p class="mt-1">Contact your system administrator for account access or a password reset.</p>
                            @endif
                        </div>
                    </div>
                </section>
            </div>
            <p class="mt-6 text-center text-xs text-muted">{{ $systemSettings['system_name'] }} &middot; Staff workspace</p>
        </div>
    </main>
</body>
</html>
