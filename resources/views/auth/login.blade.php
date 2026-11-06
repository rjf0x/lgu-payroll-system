<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | LGU Payroll System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f5f3;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        h2 {
            text-align: center;
            color: #174d35;
        }

        label {
            display: block;
            margin: 15px 0 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            border: none;
            border-radius: 6px;
            background: #174d35;
            color: white;
            cursor: pointer;
        }

        .error {
            color: #b42318;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="login-card">

    <h2>LGU Payroll System</h2>

    <p>Sign in to your account.</p>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">

        @csrf

        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <label>
            <input
                type="checkbox"
                name="remember"
                value="1"
                style="width: auto;"
            >
            Remember me
        </label>

        <button type="submit">Sign In</button>

    </form>

</div>

</body>
</html>