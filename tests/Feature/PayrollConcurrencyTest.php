<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql')]
class PayrollConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires an isolated lgu_payroll_qa_ MySQL database.');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_parallel_reviewers_cannot_approve_the_same_run_twice(): void
    {
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        $run = PayrollRun::factory()->create([
            'created_by' => $creator->getKey(), 'submitted_by' => $creator->getKey(),
            'status' => PayrollRun::STATUS_PENDING_APPROVAL,
        ]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
        $operation = '$run = App\Models\PayrollRun::findOrFail('.$run->getKey().');'
            .'$user = App\Models\User::findOrFail('.$reviewer->getKey().');'
            .'echo "READY\n"; flush();'
            .'try { app(App\Services\PayrollApprovalService::class)->approve($run, $user); echo "APPROVED\n"; }'
            .'catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { echo "REJECTED:".$e->getStatusCode()."\n"; }';

        DB::beginTransaction();
        PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operation, 2);

        $this->assertSame(1, substr_count(implode('', $outputs), "APPROVED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), 'REJECTED:422'));
        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);
        $this->assertDatabaseCount('payroll_approvals', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_four_parallel_creations_receive_distinct_sequential_run_numbers(): void
    {
        $user = User::factory()->create();
        $period = PayrollPeriod::factory()->create(['status' => 'OPEN']);
        $operation = 'Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail('.$user->getKey().'));'
            .'$request = App\Http\Requests\PayrollRunRequest::create("/payroll-runs", "POST", ["payroll_period_id" => '.$period->getKey().', "run_type" => "REGULAR"]);'
            .'$request->setContainer($app)->setRedirector(app("redirect")); $request->validateResolved();'
            .'echo "READY\n"; flush();'
            .'app(App\Http\Controllers\PayrollRunController::class)->store($request); echo "CREATED\n";';

        DB::beginTransaction();
        PayrollPeriod::whereKey($period->getKey())->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operation, 4);

        $this->assertSame(4, substr_count(implode('', $outputs), "CREATED\n"));
        $this->assertSame([1, 2, 3, 4], $period->runs()->orderBy('run_number')->pluck('run_number')->all());
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_parallel_submissions_of_overlapping_regular_runs_reserve_compensation_once(): void
    {
        $this->seed([DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        $user = User::factory()->create();
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $first = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'created_by' => $user->getKey()]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $first->getKey(), 'manual_adjustments' => true]);
        CompensationProfile::factory()->create(['employment_id' => $detail->employment_id, 'basic_rate' => 45000, 'effective_from' => '2024-01-01']);
        app(PayrollComputationService::class)->computeDetail($first, $detail->employmentRecord, $period);
        $second = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'run_number' => 2, 'created_by' => $user->getKey()]);
        PayrollDetail::factory()->create(['payroll_run_id' => $second->getKey(), 'employment_id' => $detail->employment_id, 'manual_adjustments' => true]);
        app(PayrollComputationService::class)->computeDetail($second, $detail->employmentRecord, $period);
        $operations = [];
        foreach ([$first, $second] as $run) {
            $operations[] = '$run = App\Models\PayrollRun::findOrFail('.$run->getKey().');'
                .'$user = App\Models\User::findOrFail('.$user->getKey().');'
                .'Illuminate\Support\Facades\Auth::setUser($user);'
                .'echo "READY\n"; flush();'
                .'try { app(App\Services\PayrollApprovalService::class)->submit($run, $user); echo "SUBMITTED\n"; }'
                .'catch (Illuminate\Validation\ValidationException $e) { echo "DUPLICATE_BLOCKED\n"; }';
        }

        DB::beginTransaction();
        DB::table('payroll_processing_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operations, 2);

        $this->assertSame(1, substr_count(implode('', $outputs), "SUBMITTED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), "DUPLICATE_BLOCKED\n"));
        $this->assertSame(1, PayrollRun::where('status', 'PENDING_APPROVAL')->count());
        $this->assertSame(1, PayrollRun::where('status', 'DRAFT')->count());
        $this->assertDatabaseCount('payroll_approvals', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_VALIDATION_BLOCKED']);
    }

    public function test_parallel_finalization_of_the_same_run_records_one_liability(): void
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_APPROVED]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
        $operation = '$run = App\Models\PayrollRun::findOrFail('.$run->getKey().');'
            .'$user = App\Models\User::findOrFail('.$user->getKey().');'
            .'echo "READY\n"; flush();'
            .'try { app(App\Services\PayrollApprovalService::class)->finalize($run, $user); echo "FINALIZED\n"; }'
            .'catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { echo "REJECTED:".$e->getStatusCode()."\n"; }';

        DB::beginTransaction();
        DB::table('payroll_processing_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operation, 2);

        $this->assertSame(1, substr_count(implode('', $outputs), "FINALIZED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), 'REJECTED:422'));
        $this->assertDatabaseCount('payroll_approvals', 1);
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->fresh()->status);
    }

    public function test_parallel_first_appointments_cannot_create_overlapping_active_records(): void
    {
        $actor = User::factory()->create();
        $record = EmploymentRecord::factory()->make();
        $operation = $this->appointmentCreationOperation($record, $actor);

        DB::beginTransaction();
        Employee::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operation, 2);

        $this->assertSame(1, substr_count(implode('', $outputs), "CREATED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), 'REJECTED:start_date'));
        $this->assertDatabaseCount('employment_records', 1);
        $this->assertDatabaseCount('employment_history', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_parallel_reactivations_cannot_activate_two_overlapping_appointments(): void
    {
        $actor = User::factory()->create();
        $first = EmploymentRecord::factory()->create(['employment_status' => 'INACTIVE']);
        $second = EmploymentRecord::factory()->create(['employee_id' => $first->employee_id, 'employment_status' => 'INACTIVE']);
        $operations = [];
        foreach ([$first, $second] as $record) {
            $operations[] = '$actor = App\Models\User::findOrFail('.$actor->getKey().'); Illuminate\Support\Facades\Auth::setUser($actor);'
                .'$record = App\Models\EmploymentRecord::findOrFail('.$record->getKey().');'
                .'echo "READY\n"; flush();'
                .'try { app(App\Services\MasterDataMutationService::class)->update($record, ["employment_status" => "ACTIVE"], $actor); echo "ACTIVATED\n"; }'
                .'catch (Illuminate\Validation\ValidationException $e) { echo "REJECTED:".implode(",", array_keys($e->errors()))."\n"; }';
        }

        DB::beginTransaction();
        Employee::whereKey($first->employee_id)->lockForUpdate()->firstOrFail();
        $outputs = $this->contend($operations, 2);

        $this->assertSame(1, substr_count(implode('', $outputs), "ACTIVATED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), 'REJECTED:start_date'));
        $this->assertSame(1, EmploymentRecord::where('employment_status', 'ACTIVE')->count());
        $this->assertSame(1, EmploymentRecord::where('employment_status', 'INACTIVE')->count());
        $this->assertDatabaseCount('employment_history', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_archival_committed_before_a_waiting_appointment_save_blocks_the_save(): void
    {
        $actor = User::factory()->create();
        $record = EmploymentRecord::factory()->make();
        $operation = $this->appointmentCreationOperation($record, $actor);

        DB::beginTransaction();
        $employee = Employee::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
        $employee->delete();
        $outputs = $this->contend($operation, 1);

        $this->assertSame(0, substr_count(implode('', $outputs), "CREATED\n"));
        $this->assertSame(1, substr_count(implode('', $outputs), 'REJECTED:employee_id'));
        $this->assertSoftDeleted($employee);
        $this->assertDatabaseCount('employment_records', 0);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function appointmentCreationOperation(EmploymentRecord $record, User $actor): string
    {
        $encoded = base64_encode(json_encode($record->attributesToArray(), JSON_THROW_ON_ERROR));

        return '$actor = App\Models\User::findOrFail('.$actor->getKey().'); Illuminate\Support\Facades\Auth::setUser($actor);'
            .'$attributes = json_decode(base64_decode("'.$encoded.'"), true, 512, JSON_THROW_ON_ERROR);'
            .'echo "READY\n"; flush();'
            .'try { app(App\Services\MasterDataMutationService::class)->create(new App\Models\EmploymentRecord, $attributes, $actor); echo "CREATED\n"; }'
            .'catch (Illuminate\Validation\ValidationException $e) { echo "REJECTED:".implode(",", array_keys($e->errors()))."\n"; }';
    }

    /**
     * Release the parent lock after every independent process has loaded its inputs.
     *
     * @param  string|list<string>  $operation
     * @return list<string>
     */
    private function contend(string|array $operation, int $workers): array
    {
        $connection = config('database.connections.mysql');
        $environment = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => false,
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'] ?? '', 'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php";'
            .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();';
        $processes = [];

        try {
            for ($index = 0; $index < $workers; $index++) {
                $workerOperation = is_array($operation) ? $operation[$index] : $operation;
                $process = new Process([PHP_BINARY, '-r', $bootstrap.$workerOperation], base_path(), $environment, timeout: 20);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 10;
            do {
                $ready = 0;
                foreach ($processes as $process) {
                    if (str_contains($process->getOutput(), 'READY')) {
                        $ready++;
                    } elseif (! $process->isRunning()) {
                        $this->fail('Worker failed before contention: '.$process->getErrorOutput().$process->getOutput());
                    }
                }
                if ($ready === $workers) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            $this->assertSame($workers, $ready, 'All workers must reach the lock barrier.');
            DB::commit();

            $outputs = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $outputs[] = $process->getOutput();
            }

            return $outputs;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
}
