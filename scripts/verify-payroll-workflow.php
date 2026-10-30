<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollCorrectionDecision;
use App\Models\PayrollCorrectionRequest;
use App\Models\PayrollFinalization;
use App\Models\PayrollRuleVersion;
use App\Models\PayrollSnapshotArchive;
use App\Models\PayrollSubmission;
use App\Models\PayrollVerification;
use App\Models\PayrollWorkflowEvent;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\UnavailableBlockchainSubmitter;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollCorrectionService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use App\Services\PayrollRuleVersionService;
use App\Services\PayrollSnapshotService;
use App\Services\PayrollVerificationService;
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

function workflowCheckConfiguration(User $admin, string $category, string $date, string $rate, string $department): PayrollRuleVersion
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
        'department_scope' => 'department:'.$department,
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
        $department = 'Fictional workflow '.$nonce.'-'.$category->value;
        $employee = Employee::factory()->create(['employee_number' => 'WF-'.$nonce.'-'.$category->value, 'personnel_classification' => $category,
            'office' => $department, 'employment_start_date' => '2020-01-01']);
        workflowCheckConfiguration($admin, $category->value, $date->startOfYear()->format('Y-m-d'), '0.09', $department);
        $schedule = SalarySchedule::forceCreate(['name' => 'FICTIONAL WORKFLOW SOURCE', 'department' => $department,
            'coverage_key' => SalarySchedule::coverageKey($department), 'is_demonstration' => true, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $officer->id]);
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
    $saveReviews = function (?PayrollBatch $target = null) use ($request, $batch, $core, $statutory) {
        $batch = $target ?? $batch;
        $run = $core->calculate($request, $batch, $batch->fresh()->record_version);
        foreach ($run->items as $item) {
            $profile = app(PayrollRuleVersionService::class)->profile($item->entry_snapshot['personnel_classification'], $batch->period_start->format('Y-m-d'), true, false, $item->entry_snapshot['office']);
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
    $snapshotService = app(PayrollSnapshotService::class);
    $archive = PayrollSnapshotArchive::where('payroll_finalization_id', $final->id)->sole();
    $archivedBefore = $archive->getAttributes();
    if ($archive->canonical_bytes !== $snapshotService->currentBytes($batch->fresh(), $final)
        || $archive->sha256 !== hash('sha256', $archive->canonical_bytes)
        || $snapshotService->archive($final)->id !== $archive->id) {
        throw new RuntimeException('MySQL exact canonical bytes/hash or archive idempotency failed.');
    }
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
    workflowCheckConfiguration($admin, PersonnelClassification::Permanent->value, $date->format('Y-m-d'), '0.10', 'Fictional workflow '.$nonce.'-permanent');
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
    $verification = app(PayrollVerificationService::class);
    $checkData = ['request_key' => (string) Str::uuid()];
    $check = $verification->check($reviewRequest, $batch, $final, $checkData);
    $checkAgain = $verification->check($reviewRequest, $batch, $final, $checkData);
    $isolatedCopy = clone $final;
    $isolatedSnapshot = $final->snapshot;
    $isolatedSnapshot['workflow'][0]['reason'] = 'ISOLATED FICTIONAL HASH CHANGE';
    $isolatedCopy->snapshot = $isolatedSnapshot;
    if ($check->id !== $checkAgain->id || $check->fresh()->current_hash !== $digest
        || $check->status !== 'not_anchored' || $check->local_status !== 'match' || $check->network_hash !== null
        || $verification->inspect($batch->fresh(), $isolatedCopy)['status'] !== 'mismatched'
        || $final->fresh()->getAttributes() !== $finalBefore) {
        throw new RuntimeException('Verification history/JSON digest, idempotency, isolated mismatch or preservation failed.');
    }
    $corrections = app(PayrollCorrectionService::class);
    $proposalData = [
        'request_key' => (string) Str::uuid(), 'source_hash' => $digest,
        'reason' => 'FICTIONAL replacement review', 'authority_reference' => 'FICTIONAL CORRECTION', 'review_confirmed' => 1,
    ];
    $proposal = $corrections->act($request, $batch, $final, 'request', $proposalData);
    $decisionData = ['source_hash' => $digest, 'reason' => 'FICTIONAL independent correction authority', 'authority_reference' => 'FICTIONAL APPROVAL', 'review_confirmed' => 1];
    $decision = $corrections->act($reviewRequest, $batch, $final, 'authorize', $decisionData, $proposal);
    $replacement = PayrollBatch::findOrFail($decision->correction_batch_id);
    if ($replacement->payroll_version !== 2 || $replacement->status !== 'draft' || $replacement->entries()->exists()
        || $replacement->root_payroll_batch_id !== $batch->id || $replacement->correction_of_finalization_id !== $final->id
        || $corrections->act($request, $batch, $final, 'request', $proposalData)->id !== $proposal->id
        || $corrections->act($reviewRequest, $batch, $final, 'authorize', $decisionData, $proposal)->id !== $decision->id) {
        throw new RuntimeException('Correction authorization, empty version lineage or retry idempotency failed.');
    }
    foreach ($batch->entries as $oldEntry) {
        $preparation->add($request, $replacement, ['record_version' => $replacement->fresh()->record_version, 'employee_ids' => [$oldEntry->employee_id]]);
        $newEntry = $replacement->entries()->where('employee_id', $oldEntry->employee_id)->sole();
        $inputs->save($request, $replacement, $newEntry, [
            'record_version' => $replacement->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL replacement loan', 'amount' => '200.02']],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional full replacement preparation',
        ]);
    }
    $saveReviews($replacement);
    $workflow->transition($request, $replacement, 'submit', workflowCheckData($replacement, 'submit'));
    $workflow->transition($reviewRequest, $replacement, 'approve', workflowCheckData($replacement, 'approve'));
    $workflow->transition($reviewRequest, $replacement, 'finalize', workflowCheckData($replacement, 'finalize'));
    $replacementFinal = PayrollFinalization::where('payroll_batch_id', $replacement->id)->sole();
    $replacementArchive = PayrollSnapshotArchive::where('payroll_finalization_id', $replacementFinal->id)->sole();
    if ($replacementArchive->id === $archive->id || $replacementArchive->sha256 === $archive->sha256
        || $replacementArchive->canonical_bytes !== $snapshotService->currentBytes($replacement->fresh(), $replacementFinal)
        || $archive->fresh()->getAttributes() !== $archivedBefore) {
        throw new RuntimeException('MySQL correction archives did not preserve independent version bytes.');
    }
    $replacementHash = $anchoring->digest($replacement->fresh(), $replacementFinal);
    $replacementCommitment = $anchoring->act($reviewRequest, $replacement, $replacementFinal, 'prepare', ['sha256' => $replacementHash, 'demonstration_confirmed' => 1]);
    $replacementCheck = $verification->check($reviewRequest, $replacement, $replacementFinal, ['request_key' => (string) Str::uuid()]);
    if ($replacementFinal->version !== 2 || $replacementFinal->snapshot['version'] !== 2
        || $replacementFinal->snapshot['payroll']['correction']['source_sha256'] !== $digest
        || $replacementFinal->snapshot['payroll']['correction']['authorized_by'] !== $approver->id
        || $replacementHash === $digest || $replacementCheck->local_status !== 'match'
        || $replacementCommitment->sha256 !== $replacementHash
        || $final->fresh()->getAttributes() !== $finalBefore || $first->fresh()->getAttributes() !== $before
        || $documents->csv($documents->prepare($batch->fresh(), $final->fresh()), $final) !== $csvBefore
        || $documents->csv($documents->prepare($replacement->fresh(), $replacementFinal), $replacementFinal) === $csvBefore) {
        throw new RuntimeException('Corrected JSON finalization/hash/check/documents or original version preservation failed.');
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
    // Storage-only probe on our rollback-only synthetic row; no model/API permits this rewrite.
    $probeBytes = json_encode(['unicode' => "FICTIONAL Peña/測試\r\nline", 'padding' => str_repeat('x', 70000)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    DB::table('payroll_snapshot_archives')->where('id', $archive->id)->update(['canonical_bytes' => $probeBytes]);
    if ($archive->fresh()->canonical_bytes !== $probeBytes) {
        throw new RuntimeException('MySQL UTF-8/line-ending/large canonical text round-trip failed.');
    }
    try {
        $snapshotService->assertPreserved($batch->fresh(), $final->fresh());
        throw new RuntimeException('Changed archived bytes were accepted.');
    } catch (HttpException $error) {
        if ($error->getStatusCode() !== 409) {
            throw $error;
        }
    }
    try {
        $documents->prepare($batch->fresh(), $final->fresh());
        throw new RuntimeException('Documents accepted corrupt archived bytes.');
    } catch (HttpException $error) {
        if ($error->getStatusCode() !== 409) {
            throw $error;
        }
    }
    DB::table('payroll_snapshot_archives')->where('id', $archive->id)->update(['canonical_bytes' => $archive->canonical_bytes]);
    $snapshotService->assertPreserved($batch->fresh(), $final->fresh());
    $changedSnapshot = $final->snapshot;
    $changedSnapshot['payroll']['employees'][0]['employee']['employee_name'] = 'FICTIONAL ISOLATED DOCUMENT MISMATCH';
    DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($changedSnapshot, JSON_THROW_ON_ERROR)]);
    try {
        $documents->prepare($batch->fresh(), $final->fresh());
        throw new RuntimeException('Documents accepted changed source JSON despite an intact archive.');
    } catch (HttpException $error) {
        if ($error->getStatusCode() !== 409) {
            throw $error;
        }
    }
    DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($final->snapshot, JSON_THROW_ON_ERROR)]);
    if ($documents->csv($documents->prepare($batch->fresh(), $final->fresh()), $final) !== $csvBefore) {
        throw new RuntimeException('Unchanged documents did not resume after restoring the isolated source.');
    }
} finally {
    $connection->rollBack();
}
if (User::where('username', 'like', 'workflow-%'.$nonce)->exists()
    || Employee::where('employee_number', 'like', 'WF-'.$nonce.'%')->exists()
    || PayrollFinalization::where('payroll_batch_id', $batch->id)->exists()
    || PayrollSnapshotArchive::whereKey($archive->id)->exists()
    || PayrollSnapshotArchive::whereKey($replacementArchive->id)->exists()
    || PayrollCommitment::whereKey($commitment->id)->exists()
    || PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->exists()
    || PayrollVerification::whereKey($check->id)->exists()
    || PayrollCorrectionRequest::whereKey($proposal->id)->exists()
    || PayrollCorrectionDecision::whereKey($decision->id)->exists()
    || PayrollBatch::whereKey($replacement->id)->exists()
    || PayrollFinalization::whereKey($replacementFinal->id)->exists()
    || PayrollCommitment::whereKey($replacementCommitment->id)->exists()
    || PayrollVerification::whereKey($replacementCheck->id)->exists()) {
    throw new RuntimeException('Payroll workflow check retained fictional fixtures.');
}
echo "MySQL payroll workflow passed: three categories, exact employee/batch totals, JSON review tokens, independent approval, return/correction/resubmission, retained versions/events, locked finalization, register/CSV/payslip PDF generation, duplicate prevention, later configuration/document preservation, local commitment idempotency/JSON round-trip, outer-transaction submission rejection without attempt, verification history/idempotency/isolated mismatch, authorized empty correction version and retries, full replacement preparation/approval/finalization/JSON/hash/documents/check with original preservation, exact canonical snapshot archives, independent correction bytes, large UTF-8 text round-trip and tamper rejection, real block and fixture rollback.\n";
