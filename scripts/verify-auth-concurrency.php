<?php

use App\Enums\Role;
use App\Http\Controllers\UserController;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('This concurrency check requires the configured MySQL database.');
}
config(['hashing.bcrypt.rounds' => 4]);

function waitForFile(string $path): void
{
    $deadline = microtime(true) + 15;
    while (! is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for the concurrency-check worker.');
        }
        usleep(10000);
    }
}

function editRequest(User $actor, User $target, string $change): SaveUserRequest
{
    $request = SaveUserRequest::create('/users/'.$target->id, 'PUT', [
        'name' => $target->name, 'username' => $target->username,
        'role' => $change === 'demote' ? 'viewer' : 'administrator',
        'is_active' => $change === 'deactivate' ? 0 : 1,
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $actor);
    $session = new Store('concurrency-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    $route = new Route('PUT', '/users/{user}', fn () => null);
    $route->bind($request);
    $route->setParameter('user', $target);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request;
}

if (($argv[1] ?? '') === '--worker') {
    [$directory, $actorId, $targetId, $slot, $change] = array_slice($argv, 2);
    $result = 'error';
    try {
        $actor = User::findOrFail((int) $actorId);
        $target = User::findOrFail((int) $targetId);
        $request = editRequest($actor, $target, $change);
        file_put_contents($directory.'/'.$slot.'.ready', 'ready');
        waitForFile($directory.'/'.$slot.'.go');

        DB::connection()->beforeExecuting(function ($sql) use ($directory, $slot) {
            if (str_contains(strtolower($sql), 'for update')) {
                file_put_contents($directory.'/'.$slot.'.attempt', 'attempt');
            }
        });
        $held = false;
        DB::listen(function ($query) use ($directory, $slot, &$held) {
            if ($slot === 'first' && ! $held && str_contains(strtolower($query->sql), 'for update')) {
                $held = true;
                file_put_contents($directory.'/first.locked', 'locked');
                waitForFile($directory.'/release');
            }
        });
        app(UserController::class)->update($request, $target);
        $result = 'saved';
    } catch (AuthorizationException) {
        $result = 'denied';
    } catch (HttpException $exception) {
        $result = $exception->getStatusCode() === 403 ? 'denied' : 'error';
    } finally {
        file_put_contents($directory.'/'.$slot.'.result', $result);
    }
    exit($result === 'error' ? 1 : 0);
}

$fixtureIds = [];
$processes = [];
$directories = [];
try {
    foreach (['deactivate', 'demote'] as $change) {
        $directory = sys_get_temp_dir().'/lgu-access-check-'.bin2hex(random_bytes(12));
        mkdir($directory, 0700);
        $directories[] = $directory;
        $accounts = [];
        for ($index = 0; $index < 2; $index++) {
            $account = User::factory()->create([
                'name' => 'Fictional Concurrent Access Check',
                'username' => 'concurrency-'.bin2hex(random_bytes(12)),
                'role' => Role::Administrator,
            ]);
            $fixtureIds[] = $account->id;
            $accounts[] = $account;
        }
        foreach (['first', 'second'] as $index => $slot) {
            $process = proc_open([
                PHP_BINARY, __FILE__, '--worker', $directory,
                (string) $accounts[$index]->id, (string) $accounts[1 - $index]->id,
                $slot, $change,
            ], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/'.$slot.'.log', 'a'], 2 => ['file', $directory.'/'.$slot.'.log', 'a']], $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Could not start the concurrency-check worker.');
            }
            fclose($pipes[0]);
            $processes[] = $process;
        }
        waitForFile($directory.'/first.ready');
        waitForFile($directory.'/second.ready');
        file_put_contents($directory.'/first.go', 'go');
        waitForFile($directory.'/first.locked');
        file_put_contents($directory.'/second.go', 'go');
        waitForFile($directory.'/second.attempt');
        usleep(200000); // Observe a blocked writer while the first still holds both rows.
        if (is_file($directory.'/second.result')) {
            throw new RuntimeException('The second write did not wait for the first transaction.');
        }
        file_put_contents($directory.'/release', 'release');
        waitForFile($directory.'/first.result');
        waitForFile($directory.'/second.result');
        if (file_get_contents($directory.'/first.result') !== 'saved'
            || file_get_contents($directory.'/second.result') !== 'denied'
            || User::whereIn('id', array_column($accounts, 'id'))->where('role', Role::Administrator)->where('is_active', true)->count() !== 1) {
            throw new RuntimeException('Reciprocal administrator edits did not preserve one active administrator.');
        }
        echo "MySQL reciprocal $change check passed: second writer waited and was denied after revocation.\n";
    }
} finally {
    foreach ($directories as $directory) {
        file_put_contents($directory.'/release', 'release');
    }
    foreach ($processes as $process) {
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }
        proc_close($process);
    }
    // Only IDs allocated by this run; no existing account or schema is removed.
    User::whereIn('id', $fixtureIds)->delete();
    foreach ($directories as $directory) {
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
echo "No concurrency-check accounts retained.\n";
