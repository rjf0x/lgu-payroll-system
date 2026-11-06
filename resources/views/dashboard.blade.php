<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | LGU Payroll</title>
</head>

<body>

    <h1>Welcome to LGU Payroll System!</h1>

    <p>Signed in as <strong>{{ auth()->user()->username }}</strong>.</p>

    <p>
        Role(s):
        @forelse (auth()->user()->roles as $role)
            <span>{{ $role->role_name }}</span>@if (! $loop->last), @endif
        @empty
            <em>none assigned</em>
        @endforelse
    </p>

    <p>
        Permissions: {{ auth()->user()->permissionNames()->implode(', ') ?: 'none' }}
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit">Logout</button>
    </form>

</body>
</html>
