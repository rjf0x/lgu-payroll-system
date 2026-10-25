<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('This smoke check requires the configured MySQL database.');
}

$usernames = [];
$connection->beginTransaction();
try {
    foreach (Role::cases() as $role) {
        $username = 'auth-'.bin2hex(random_bytes(12));
        $usernames[] = $username;
        $password = bin2hex(random_bytes(24));
        $account = new User(['name' => 'Fictional Access Check', 'username' => $username, 'password' => $password]);
        $account->role = $role;
        $account->save();
        $account->refresh();

        if ($account->role !== $role || ! Hash::check($password, $account->password)
            || ! Auth::attempt(['username' => $username, 'password' => $password, 'is_active' => true])) {
            throw new RuntimeException('MySQL role persistence or authentication failed.');
        }
        foreach (Role::cases() as $candidate) {
            if (Gate::forUser($account)->allows('access-'.$candidate->workspace()) !== ($candidate === $role)) {
                throw new RuntimeException('MySQL workspace authorization failed.');
            }
        }
        if (Gate::forUser($account)->allows('manage-users') !== ($role === Role::Administrator)) {
            throw new RuntimeException('MySQL account authorization failed.');
        }
        Auth::logout();
        $account->is_active = false;
        $account->save();
        if (Auth::attempt(['username' => $username, 'password' => $password, 'is_active' => true])) {
            throw new RuntimeException('An inactive MySQL account authenticated.');
        }
    }
} finally {
    Auth::logout();
    $connection->rollBack();
}

if (User::whereIn('username', $usernames)->exists()) {
    throw new RuntimeException('MySQL authentication smoke check did not roll back.');
}
echo "MySQL authentication, four-role authorization, inactive-account rejection, and rollback passed; no test accounts retained.\n";
