<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollRuleVersion;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorkflowEvent;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\UnavailableBlockchainSubmitter;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use App\Services\PayrollRuleVersionService;
use App\Services\PayrollWorkflowService;
use App\Services\StatutoryPreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Payroll workflow verification requires MySQL.');
}

function workflowCheckRequest(User $actor): Request
{
    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $actor);
    $session = new Store('workflow-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);

    return $request;
}

function workflowCheckData(PayrollBatch $batch, string $action): array
{
    $service = app(PayrollWorkflowService::class);

    return [
        'record_version' => $batch->fresh()->record_version,
        'submission_id' => $service->latestSubmission($batch)?->id,
        'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
        'reason' => 'FICTIONAL MySQL '.$action, 'review_confirmed' => 1,
    ];
}

function workflowCheckConfiguration(User $admin, string $category, string $date, string $rate): PayrollRuleVersion
{
    $template = config('payroll_statutory');
    foreach ($template['contributions'] as &$component) {
        unset($component['method']);
        $component['source'] = 'FICTIONAL WORKFLOW SOURCE';
        $component['scope'] = 'Rollback-only fictional arithmetic';
    }
    unset($component);
    $template['contributions']['gsis']['rate'] = $rate;
    $request = workflowCheckRequest($admin);
    $service = app(PayrollRuleVersionService::class);
    $version = $service->save($request, [
        'submission_key' => (string) Str::uuid(), 'record_version' => 0,
        'name' => 'FICTIONAL WORKFLOW CONFIGURATION', 'personnel_classification' => $category,
        'is_demonstration' => 1, 'effective_from' => $date, 'change_reason' => 'Fictional workflow configuration',
        'parameters' => ['contributions' => $template['contributions'], 'tax' => $template['tax']],
    ]);

    return $service->activate($request, $version, [
        'record_version' => 1, 'approval_reference' => 'FICTIONAL APPROVAL', 'validation_reference' => 'FICTIONAL EXAMPLES',
        'activation_reason' => 'Fictional workflow activation', 'authorization_confirmed' => 1,
    ]);
}

$nonce = strtolower(bin2hex(random_bytes(10)));
$connection->beginTransaction();
try {
    $officer = User::factory()->create(['username' => 'workflow-officer-'.$nonce, 'role' => Role::PayrollOfficer]);
    $approver = User::factory()->create(['username' => 'workflow-approver-'.$nonce, 'role' => Role::Approver]);
    $admin = User::factory()->create(['username' => 'workflow-admin-'.$nonce, 'role' => Role::Administrator]);
    $request = workflowCheckRequest($officer);
    $reviewRequest = workflowCheckRequest($approver);
    $date = CarbonImmutable::create(random_int(8000, 8990), 10, 1);
    while (PayrollBatch::whereYear('period_start', $date->year)->exists()
        || SalarySchedule::whereYear('effective_from', $date->year)->exists()
        || PayrollRuleVersion::whereYear('effective_from', $date->year)->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated workflow verification year available.');
        }
    }
    $preparation = app(PayrollPreparationService::class);
    $inputs = app(PayrollInputService::class);
    $core = app(PayrollCalculationService::class);
    $statutory = app(StatutoryPreviewService::class);
    $workflow = app(PayrollWorkflowService::class);
    $batch = $preparation->create($request, ['period' => $date->format('Y-m'), 'is_demonstration' => 1]);
    foreach (PersonnelClassification::cases() as $category) {
        workflowCheckConfiguration($admin, $category->value, $date->startOfYear()->format('Y-m-d'), '0.09');
        $employee = Employee::factory()->create(['employee_number' => 'WF-'.$nonce.'-'.$category->value, 'personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::create(['name' => 'FICTIONAL WORKFLOW SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $officer->id]);
        $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
        $schedule->forceFill(['published_at' => now(), 'authority_reference' => 'FICTIONAL AUTHORITY'])->save();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => $date->format('Y-m-d'),
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Review Clerk',
            'monthly_salary' => '30000.00', 'is_demonstration' => true, 'step_basis_date' => $date->format('Y-m-d'),
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Rollback-only workflow', 'created_by' => $officer->id,
        ]);
        $preparation->add($request, $batch, ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]]);
        $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
        $inputs->save($request, $batch, $entry, [
            'record_version' => $batch->fresh()->record_version, 'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional zero attendance',
        ]);
    }
    $saveReviews = function () use ($request, $batch, $core, $statutory) {
        $run = $core->calculate($request, $batch, $batch->fresh()->record_version);
        foreach ($run->items as $item) {
            $profile = app(PayrollRuleVersionService::class)->profile($item->entry_snapshot['personnel_classification'], $batch->period_start->format('Y-m-d'), true);
            $statutory->save($request, $batch, $run, $item, [
                'record_version' => $batch->fresh()->record_version, 'base_revision' => 0, 'configuration_version_id' => $profile['_configuration']['version_id'],
                'gsis_coverage' => 'applicable', 'gsis_basis' => '30000.00', 'pagibig_coverage' => 'applicable', 'pagibig_basis' => '30000.00',
                'philhealth_coverage' => 'applicable', 'philhealth_basis' => '30000.00', 'tax_method' => 'monthly_regular',
                'taxable_regular' => '26350.00', 'taxable_other' => '0.00', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00',
                'elapsed_periods' => null, 'reference' => 'FICTIONAL BASES', 'reason' => 'Rollback-only fictional review', 'demonstration_confirmed' => 1,
            ]);
        }
    };
    $saveReviews();
    $submit = workflowCheckData($batch, 'submit');
    $workflow->transition($request, $batch, 'submit', $submit);
    $first = $workflow->latestSubmission($batch);
    $before = $first->getAttributes();
    if ($first->snapshot['totals']['net_pay'] !== '76567.35') {
        throw new RuntimeException('Three-employee submitted total differs from prepared arithmetic.');
    }
    // A role change cannot let a preparer approve the same payroll.
    $officer->forceFill(['role' => Role::Approver])->save();
    try {
        $workflow->transition($request, $batch, 'approve', workflowCheckData($batch, 'approve'));
        throw new RuntimeException('A former preparer approved their own payroll.');
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 403) {
            throw $exception;
        }
    }
    $officer->forceFill(['role' => Role::PayrollOfficer])->save();
    $workflow->transition($reviewRequest, $batch, 'return', workflowCheckData($batch, 'return'));
    $entry = $batch->entries()->orderBy('employee_id')->first();
    $inputs->save($request, $batch, $entry, [
        'record_version' => $batch->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL CORRECTED LOAN', 'amount' => '100.01']],
        'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0], 'authority_reference' => 'FICTIONAL CORRECTION', 'change_reason' => 'Fictional corrected deduction',
    ]);
    $saveReviews();
    $workflow->transition($request, $batch, 'submit', workflowCheckData($batch, 'submit'));
    $workflow->transition($reviewRequest, $batch, 'approve', workflowCheckData($batch, 'approve'));
    $finalize = workflowCheckData($batch, 'finalize');
    $workflow->transition($reviewRequest, $batch, 'finalize', $finalize);
    $final = PayrollFinalization::where('payroll_batch_id', $batch->id)->sole();
    $finalBefore = $final->getAttributes();
    $documents = app(PayrollDocumentService::class);
    $document = $documents->prepare($batch->fresh(), $final);
    $csvBefore = $documents->csv($document, $final);
    if ($document['totals']['net_pay'] !== '76467.34' || count($document['rows']) !== 3
        || ! str_starts_with($csvBefore, "\xEF\xBB\xBF") || ! str_contains($csvBefore, 'DEMONSTRATION ONLY')) {
        throw new RuntimeException('Finalized document totals or CSV metadata differ from the preserved payroll.');
    }
    $pdfData = ['batch' => $batch->fresh(), 'finalization' => $final, 'document' => $document];
    $registerPdf = $documents->pdf('payroll-documents.register', $pdfData, true);
    $row = $documents->employee($document, (string) $entry->employee_id);
    $payslipPdf = $documents->pdf('payroll-documents.payslip', [...$pdfData, 'row' => $row], false);
    if (! str_starts_with($registerPdf, '%PDF-') || ! str_starts_with($payslipPdf, '%PDF-')) {
        throw new RuntimeException('Finalized register or payslip did not generate a PDF.');
    }
    if ($final->snapshot['payroll']['totals'] != ['total_earnings' => '90000.00', 'total_deductions' => '13532.66', 'net_pay' => '76467.34']
        || $first->fresh()->getAttributes() !== $before
        || PayrollSubmission::where('payroll_batch_id', $batch->id)->count() !== 2
        || PayrollWorkflowEvent::where('payroll_batch_id', $batch->id)->count() !== 5) {
        throw new RuntimeException('Finalized totals, prior submission or workflow history were not preserved.');
    }
    try {
        $workflow->transition($reviewRequest, $batch, 'finalize', $finalize);
        throw new RuntimeException('Duplicate finalization was accepted.');
    } catch (AuthorizationException) {
        // Finalized status disallows every further transition.
    }
    workflowCheckConfiguration($admin, PersonnelClassification::Permanent->value, $date->format('Y-m-d'), '0.10');
    if ($final->fresh()->getAttributes() !== $finalBefore) {
        throw new RuntimeException('Configuration activation changed finalized payroll.');
    }
    if ($documents->csv($documents->prepare($batch->fresh(), $final->fresh()), $final) !== $csvBefore) {
        throw new RuntimeException('Configuration activation changed retrieved payroll documents.');
    }
    // This rollback-only check must never use an adapter capable of broadcasting.
    if (! app(BlockchainSubmitter::class) instanceof UnavailableBlockchainSubmitter) {
        throw new RuntimeException('Rollback-only verification requires the unavailable blockchain interface.');
    }
    $anchoring = app(PayrollAnchoringService::class);
    $digest = $anchoring->digest($batch->fresh(), $final->fresh());
    $anchorData = ['sha256' => $digest, 'demonstration_confirmed' => 1];
    $commitment = $anchoring->act($reviewRequest, $batch, $final, 'prepare', $anchorData);
    $repeated = $anchoring->act($reviewRequest, $batch, $final, 'prepare', $anchorData);
    try {
        $anchoring->act($reviewRequest, $batch, $final, 'submit', $anchorData);
        throw new RuntimeException('Submission was allowed inside the rollback-only outer transaction.');
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 409) {
            throw $exception;
        }
    }
    if ($commitment->id !== $repeated->id || $commitment->sha256 !== $digest
        || $commitment->fresh()->sha256 !== $anchoring->digest($batch->fresh(), $final->fresh())
        || PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->exists()
        || $final->fresh()->getAttributes() !== $finalBefore) {
        throw new RuntimeException('Local commitment retry, JSON round-trip, outer-transaction rejection or finalization preservation failed.');
    }
    $real = $preparation->create($request, ['period' => $date->format('Y-m'), 'is_demonstration' => 0]);
    try {
        $workflow->transition($request, $real, 'submit', ['record_version' => 1, 'review_token' => str_repeat('a', 64), 'reason' => 'FICTIONAL real block', 'review_confirmed' => 1]);
        throw new RuntimeException('Real payroll submission was accepted.');
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['review'])) {
            throw $exception;
        }
    }
} finally {
    $connection->rollBack();
}
if (User::where('username', 'like', 'workflow-%'.$nonce)->exists()
    || Employee::where('employee_number', 'like', 'WF-'.$nonce.'%')->exists()
    || PayrollFinalization::where('payroll_batch_id', $batch->id)->exists()
    || PayrollCommitment::whereKey($commitment->id)->exists()
    || PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->exists()) {
    throw new RuntimeException('Payroll workflow check retained fictional fixtures.');
}
echo "MySQL payroll workflow passed: three categories, exact employee/batch totals, JSON review tokens, independent approval, return/correction/resubmission, retained versions/events, locked finalization, register/CSV/payslip PDF generation, duplicate prevention, later configuration/document preservation, local commitment idempotency/JSON round-trip, outer-transaction submission rejection without attempt, real block and fixture rollback.\n";
