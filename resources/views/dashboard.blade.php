<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | LGU Payroll</title>
</head>

<body>

    <h1>Welcome to LGU Payroll System!</h1>

    <p>You are successfully authenticated.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit">Logout</button>
    </form>

</body>
</html>