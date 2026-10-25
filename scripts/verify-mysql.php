<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    fwrite(STDERR, "This smoke check requires the configured MySQL database.\n");
    exit(1);
}

$username = 'foundation-'.bin2hex(random_bytes(8));
$connection->beginTransaction();
try {
    $user = User::factory()->create(['name' => 'Foundation Test', 'username' => $username]);
    if ($user->fresh()?->username !== $username) {
        throw new RuntimeException('MySQL persistence check failed.');
    }
} finally {
    $connection->rollBack();
}

if (User::where('username', $username)->exists()) {
    throw new RuntimeException('MySQL rollback check failed.');
}
echo "MySQL write, read, and rollback checks passed; no test account retained.\n";
