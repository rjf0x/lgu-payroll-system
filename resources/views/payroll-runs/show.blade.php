@extends('layouts.app')

@section('title', 'Payroll Run')
@section('heading', 'Payroll Run · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    @php($confirmationContext = $run->payrollPeriod->period_name.' · Run #'.$run->run_number.' · '.$run->details->count().' employees · Net pay ₱'.number_format($totalNet, 2))
    <x-page-header :description="$run->payrollPeriod->period_name.' · Run #'.$run->run_number.' · '.str_replace('_', ' ', ucfirst(strtolower($run->run_type))).' payroll'">
        <x-status :value="$run->status" />
    </x-page-header>

    <div class="space-y-6">
        @if ($accountingPolicyRequired)
            <x-alert variant="warning">Municipal Accounting Office policy confirmation is required for this run's tax and statutory contribution treatment. Amounts remain provisional; submission, approval, and finalization are blocked.</x-alert>
        @endif
        @if ($run->run_type !== 'REGULAR')
            <x-card heading="Additional compensation and correction references">
                <p>{{ $run->purpose }}</p>
                @foreach ($run->details as $detail)
                    <dl class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-sm">
                        <div><dt class="font-semibold">{{ $detail->employee_name_snapshot }} / {{ $detail->compensation_reference ?? 'Reference required' }}</dt><dd>{{ $detail->coverage_start?->toDateString() ?? 'Coverage required' }} to {{ $detail->coverage_end?->toDateString() ?? 'Coverage required' }}</dd></div>
                        <div><dt>Reason</dt><dd>{{ $detail->compensation_reason }}</dd></div>
                        <div><dt>Accounting authorization</dt><dd>{{ $detail->accounting_basis }}</dd></div>
                        <div><dt>Separate entitlement justification</dt><dd>{{ $detail->overlap_justification }}</dd></div>
                        @if ($detail->original_payroll_detail_id)
                            <div><dt>Original finalized detail</dt><dd>#{{ $detail->original_payroll_detail_id }}</dd></div>
                        @endif
                    </dl>
                @endforeach
            </x-card>
        @endif
        @if ($eligibilityIssues->isNotEmpty())
            <x-alert variant="warning">Employee eligibility needs resolution. Open each flagged payroll detail to retain payable compensation with justification or remove the employee before submission.</x-alert>
        @endif
        @if ($run->isEditable() && $run->details->contains(fn ($detail) => $detail->payable_days === null || $detail->input_confirmed_at === null || $detail->payroll_status !== 'COMPUTED'))
            <x-alert variant="warning">Payroll inputs are incomplete. Check the status on each employee row. Enter missing payable days through Edit, or use Confirm &amp; Save to confirm and recompute existing inputs. Totals are provisional until all inputs are complete.</x-alert>
        @endif
        <x-payroll-progress :run="$run" :can-submit="$canSubmit" :can-review="$canReview" :can-finalize="$canFinalize" :independent="$isIndependentReviewer" />
        <x-card heading="Run overview">
            <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div><dt class="text-sm text-muted">Employees</dt><dd class="mt-1 font-semibold tabular-nums">{{ $run->details->count() }}</dd></div>
                <div><dt class="text-sm text-muted">Processed</dt><dd class="mt-1 text-sm"><x-local-time :value="$run->processed_at" fallback="Not yet processed" /></dd></div>
                <div><dt class="text-sm text-muted">Submitted by</dt><dd class="mt-1 break-words text-sm">{{ $run->submittedBy?->username ?? ($run->submitted_at ? 'Unavailable user' : 'Not yet submitted') }}</dd></div>
                <div><dt class="text-sm text-muted">Finalized by</dt><dd class="mt-1 break-words text-sm">{{ $run->finalizedBy?->username ?? ($run->finalized_at ? 'Unavailable user' : 'Not yet finalized') }}</dd></div>
            </dl>
        </x-card>

        @if ($run->details->isNotEmpty())
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['Total Gross' => $totalGross, 'Total Deductions' => $totalDeductions, 'Total Net Pay' => $totalNet, 'Employer Contributions' => $employerContributions] as $label => $amount)
                    <div class="ui-card">
                        <dt class="text-sm text-muted">{{ $label }}</dt>
                        <dd class="mt-2 break-words text-xl font-semibold tabular-nums text-brand-strong">₱{{ number_format($amount, 2) }}</dd>
                    </div>
                @endforeach
            </dl>

            <section aria-labelledby="breakdown-heading" class="min-w-0">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="breakdown-heading" class="text-lg font-semibold">Employee breakdown</h2>
                    <p class="text-sm text-muted">Amounts in Philippine pesos. Scroll within the table for all columns.</p>
                </div>
                <x-table label="Employee payroll breakdown" :sticky-first="true" :compact="true">
                    <thead>
                        <tr>
                            <th scope="col" class="ui-table-primary">Employee</th>
                            <th scope="col">Payroll inputs</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Basic Pay</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Earnings</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Gross</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Deductions</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Net pay before LBP</th>
                            <th scope="col" class="ui-money text-right tabular-nums">LBP deduction</th>
                            <th scope="col" class="ui-money text-right tabular-nums">Net Pay</th>
                            <th scope="col" class="text-right ui-table-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($run->details as $detail)
                            <tr>
                                <td class="ui-table-primary">
                                    <div class="text-sm font-semibold">{{ $detail->employee_name_snapshot }}</div>
                                    @if ($eligibilityIssues->has($detail->getKey()))
                                        <div class="text-sm text-warning-ink">Eligibility unresolved</div>
                                    @endif
                                    <div class="ui-table-secondary max-w-56 truncate" title="{{ $detail->position_snapshot }}">{{ $detail->position_snapshot }}</div>
                                </td>
                                <td class="w-48">
                                    @if ($run->isEditable())
                                        <x-status :value="match (true) {
                                            $detail->payable_days === null => 'NEEDS_INPUT',
                                            $detail->input_confirmed_at === null => 'NEEDS_CONFIRMATION',
                                            $detail->payroll_status !== 'COMPUTED' => 'NEEDS_RECOMPUTATION',
                                            default => 'CONFIRMED',
                                        }" class="mb-1 whitespace-nowrap px-2 py-0.5 text-xs" />
                                    @endif
                                    @if ($detail->payable_days !== null)
                                        <div class="text-sm tabular-nums"><span class="sr-only">Payable days: </span>{{ $detail->payable_days }} days</div>
                                    @endif
                                    @if ($detail->input_reference)
                                        <div class="ui-table-secondary max-w-48 truncate" title="{{ $detail->input_reference }}">Source: {{ $detail->input_reference }}</div>
                                    @endif
                                </td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->basic_pay, 2) }}</td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->total_earnings, 2) }}</td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->gross_pay, 2) }}</td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->total_deductions, 2) }}</td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->nthp_before_lbp, 2) }}</td>
                                <td class="ui-money text-right tabular-nums">{{ number_format((float) $detail->lbp_loan_deduction, 2) }}</td>
                                <td class="ui-money font-semibold text-right tabular-nums">{{ number_format((float) $detail->net_pay, 2) }}</td>
                                <td class="text-right ui-table-actions">
                                    @can('payroll.prepare')
                                        @if ($run->isEditable())
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @if ($detail->payable_days !== null && ($detail->input_confirmed_at === null || $detail->payroll_status !== 'COMPUTED') && ! $eligibilityIssues->has($detail->getKey()))
                                                    <form method="POST" action="{{ route('payroll-details.confirm', [$run, $detail]) }}" data-confirm data-confirm-title="Confirm employee payroll inputs?" data-confirm-description="I confirm the existing payable days, earnings, deductions, and loan inputs match the approved source for this payroll period. Save these inputs and recompute this employee's payroll." data-confirm-context="{{ $detail->employee_name_snapshot.' / '.$detail->payable_days.' payable days / '.$run->payrollPeriod->period_name }}" onsubmit="return window.payrollConfirmationReady || confirm(this.dataset.confirmDescription + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                                                        @csrf
                                                        <input type="hidden" name="inputs_confirmed" value="1">
                                                        <x-button type="submit" variant="primary" class="ui-button-compact" :aria-label="'Confirm and save payroll inputs for '.$detail->employee_name_snapshot" data-submitting-label="Saving…">Confirm &amp; Save</x-button>
                                                    </form>
                                                @endif
                                                <x-action-menu :label="'Actions for '.$detail->employee_name_snapshot"><x-button icon="edit" variant="secondary" href="{{ route('payroll-details.edit', [$run, $detail]) }}">Edit<span class="sr-only"> {{ $detail->employee_name_snapshot }}</span></x-button></x-action-menu>
                                            </div>
                                        @else
                                            <x-status value="READ_ONLY" class="whitespace-nowrap" />
                                        @endif
                                    @else
                                        <x-status value="READ_ONLY" class="whitespace-nowrap" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </section>
        @else
            <x-card heading="Employee breakdown">
                <p class="text-muted">No payroll details yet. A payroll preparer can compute this run to generate them.</p>
            </x-card>
        @endif

        <x-card heading="Payroll actions">
            @if ($exceptionEmployments->isNotEmpty())
                <details class="mb-4">
                    <summary class="cursor-pointer text-sm font-semibold">{{ $run->run_type === 'REGULAR' ? 'Include archived or inactive employee with payable compensation' : 'Include an employee for explicit additional compensation or correction' }}</summary>
                    <form method="POST" action="{{ route('payroll-details.store', $run) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-form.select name="employment_id" label="Employment eligible by coverage dates" required placeholder="Choose an employment record" :options="$exceptionEmployments->mapWithKeys(fn ($employment) => [$employment->getKey() => $employment->employee?->full_name.' / Employment #'.$employment->getKey()])" />
                        <x-form.textarea name="reason" label="Eligibility justification" required maxlength="2000" hint="Document earned compensation and authorization for this payroll coverage. Current payroll inputs must then be confirmed and recomputed." />
                        <x-button type="submit">Include employee</x-button>
                    </form>
                </details>
            @endif
            <div class="flex flex-wrap items-start gap-3">
                @can('payroll.prepare')
                    @if ($run->isEditable())
                        <form method="POST" action="{{ route('payroll-runs.compute', $run) }}" @if ($run->details->isNotEmpty()) data-confirm data-confirm-title="Recompute payroll?" data-confirm-description="Recomputation refreshes calculated totals using current compensation and statutory settings. Confirmed payable days and manual adjustments are retained. Review the totals afterwards." @endif data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || !this.hasAttribute('data-confirm') || confirm((event.submitter?.dataset.confirmDescription || this.dataset.confirmDescription) + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                            @csrf
                            <x-button icon="calculate" type="submit" :variant="$canSubmit ? 'secondary' : 'primary'" data-submitting-label="Computing payroll…">{{ $run->details->isEmpty() ? 'Compute payroll' : 'Recompute' }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('payroll-runs.destroy', $run) }}" data-confirm data-confirm-title="Delete payroll run?" data-confirm-description="This deletes the editable run and its calculated details. This action cannot be undone." data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || !this.hasAttribute('data-confirm') || confirm((event.submitter?.dataset.confirmDescription || this.dataset.confirmDescription) + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                            @csrf
                            @method('DELETE')
                            <x-button icon="delete" type="submit" variant="danger">Delete</x-button>
                        </form>
                    @endif
                @endcan
                @if ($canSubmit)
                    <form method="POST" action="{{ route('payroll-runs.submit', $run) }}" data-confirm data-confirm-title="Submit for approval?" data-confirm-description="The run will be sent for independent review and will no longer be editable until returned or rejected." data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || confirm(this.dataset.confirmDescription + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                        @csrf
                        <x-button icon="send" type="submit" variant="primary">Submit for approval</x-button>
                    </form>
                @endif
                @if ($canReview)
                    <form method="POST" action="{{ route($canApprove ? 'payroll-runs.approve' : ($canReturn ? 'payroll-runs.return' : 'payroll-runs.reject'), $run) }}" data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || !this.hasAttribute('data-confirm') || confirm((event.submitter?.dataset.confirmDescription || this.dataset.confirmDescription) + '\n' + this.dataset.confirmContext)" data-submit-feedback data-confirm class="w-full space-y-4">
                        @csrf
                        <x-form.textarea name="remarks" label="Review remarks" id="approval-remarks" maxlength="2000" hint="Approval remarks (optional). A reason is required to return or reject this payroll." />
                        <div class="flex flex-wrap gap-3">
                            @if ($canApprove)
                                <x-button icon="approve" type="submit" variant="primary" :formaction="route('payroll-runs.approve', $run)" data-confirm-title="Approve payroll?" data-confirm-description="This records your approval. Finalization remains a separate action.">Approve</x-button>
                            @endif
                            @if ($canReturn)
                                <x-button icon="return" type="submit" variant="warning" :formaction="route('payroll-runs.return', $run)" data-requires-remarks data-confirm-title="Return for correction?" data-confirm-description="This returns the run to preparation. Your remarks explain what must be corrected.">Return</x-button>
                            @endif
                            @if ($canReject)
                                <x-button icon="reject" type="submit" variant="danger" :formaction="route('payroll-runs.reject', $run)" data-requires-remarks data-confirm-title="Reject payroll?" data-confirm-description="This records a rejection and reopens the run for correction. Your reason will be saved in the approval history.">Reject</x-button>
                            @endif
                        </div>
                    </form>
                @endif
                @if ($canFinalize)
                    <form method="POST" action="{{ route('payroll-runs.finalize', $run) }}" data-confirm data-confirm-title="Finalize payroll?" data-confirm-description="Finalization locks this run against editing and recomputation. Confirm that the period and totals are correct." data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || !this.hasAttribute('data-confirm') || confirm((event.submitter?.dataset.confirmDescription || this.dataset.confirmDescription) + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                        @csrf
                        <x-button icon="lock" type="submit" variant="primary">Finalize</x-button>
                    </form>
                @endif
                @if (! (auth()->user()->can('payroll.prepare') && $run->isEditable()) && ! $canSubmit && ! $canReview && ! $canFinalize)
                    <p class="text-sm text-muted">No payroll actions are available to your account for this run.</p>
                @endif
            </div>
        </x-card>

        @if ($run->details->isNotEmpty())
            <x-card heading="Reports and documents">
                <div class="flex flex-wrap gap-3">
                    <x-button icon="document" :href="route('payroll-runs.register', $run)">Register</x-button>
                    <x-button icon="document" :href="route('payroll-runs.payslips', $run)">Payslips</x-button>
                    <x-button icon="document" :href="route('reports.payroll-summary', $run)">Summary</x-button>
                    <x-button icon="list" :href="route('reports.deductions', $run)">Deductions report</x-button>
                </div>
            </x-card>
        @endif

        <section aria-labelledby="approval-history-heading" class="min-w-0">
            <h2 id="approval-history-heading" class="mb-3 text-lg font-semibold">Approval History</h2>
            <x-table label="Payroll approval history">
                <thead><tr><th scope="col" class="ui-table-primary">Action</th><th scope="col">Acted By</th><th scope="col">When</th><th scope="col">Remarks</th></tr></thead>
                <tbody>
                    @forelse ($run->approvals as $approval)
                        <tr>
                            <td class="font-semibold ui-table-primary">{{ str_replace('_', ' ', ucfirst(strtolower($approval->action))) }}</td>
                            <td>{{ $approval->actedBy?->username ?? 'Unavailable user' }}</td>
                            <td><x-local-time :value="$approval->acted_at" /></td>
                            <td class="ui-wrap">{{ $approval->remarks ?: 'No remarks' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-muted">No approval actions yet.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </section>

        @can('blockchain.verify')
            <x-card heading="Payroll integrity">
                @if ($run->registration)
                    <div class="space-y-3 text-sm">
                        <p>Registration: <x-status :value="$run->registration->status" /></p>
                        @if ($run->registration->latestVerification)
                            <p>Last integrity check: <x-status :value="$run->registration->latestVerification->verification_result" /></p>
                            <p class="text-muted">Checked <x-local-time :value="$run->registration->latestVerification->verified_at" show-timezone />. Verify again to check current data.</p>
                        @else
                            <p class="text-muted">No integrity check has been recorded yet.</p>
                        @endif
                        <x-button icon="view" :href="route('payroll-registrations.show', $run->registration)">View registration &amp; verify</x-button>
                    </div>
                @elseif ($run->status === 'FINALIZED')
                    @if ($run->anchor)
                        <div class="space-y-3 text-sm">
                            <p class="break-words">Network: {{ $run->anchor->network_name }} · <x-status :value="$run->anchor->anchor_status" /></p>
                            <p class="text-muted">Anchored: <x-local-time :value="$run->anchor->anchored_at" /></p>
                            <p class="break-all rounded-lg bg-slate-50 p-3 font-mono">{{ $run->anchor->payload_hash }}</p>
                            <x-button icon="view" :href="route('blockchain-anchors.show', $run->anchor)">View &amp; verify anchor</x-button>
                        </div>
                    @else
                        <p class="mb-3 text-muted">This finalized run has not been anchored yet.</p>
                        <form method="POST" action="{{ route('payroll-runs.anchor', $run) }}" data-confirm-context="{{ $confirmationContext }}" onsubmit="return window.payrollConfirmationReady || !this.hasAttribute('data-confirm') || confirm((event.submitter?.dataset.confirmDescription || this.dataset.confirmDescription) + '\n' + this.dataset.confirmContext)" data-submit-feedback>
                            @csrf
                            <x-button icon="verify" type="submit" variant="primary">Anchor finalized payroll</x-button>
                        </form>
                    @endif
                @else
                    <p class="text-muted">Anchoring becomes available once the payroll run is finalized.</p>
                @endif
            </x-card>
        @endcan
    </div>
@endsection
