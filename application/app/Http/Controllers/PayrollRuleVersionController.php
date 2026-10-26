<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\ActivatePayrollRuleVersionRequest;
use App\Http\Requests\SavePayrollRuleVersionRequest;
use App\Models\PayrollRuleEvent;
use App\Models\PayrollRuleVersion;
use App\Services\PayrollRuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PayrollRuleVersionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRuleVersion::class);
        $query = PayrollRuleVersion::query();
        if ($request->user()->role !== Role::Administrator) {
            $query->whereNotNull('activated_at');
        }

        return view('payroll-rule-versions.index', ['versions' => $query->orderByDesc('effective_from')->orderByDesc('id')->paginate(20)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PayrollRuleVersion::class);
        $source = null;
        if ($request->query->has('copy')) {
            $data = $request->validate(['copy' => ['required', 'integer', 'min:1']]);
            $source = PayrollRuleVersion::findOrFail($data['copy']);
        }
        $version = (new PayrollRuleVersion)->forceFill([
            'submission_key' => (string) Str::uuid(), 'record_version' => 0, 'is_demonstration' => true,
            'parameters' => $source?->parameters ?? config('payroll_statutory'),
            'personnel_classification' => $source?->personnel_classification,
        ]);

        return view('payroll-rule-versions.form', ['version' => $version, 'classifications' => PersonnelClassification::cases()]);
    }

    public function store(SavePayrollRuleVersionRequest $request, PayrollRuleVersionService $service): RedirectResponse
    {
        $version = $service->save($request, $request->validated());

        return redirect()->route('payroll-rule-versions.show', $version)->with('status', 'Configuration draft saved. Review it before activation.');
    }

    public function show(PayrollRuleVersion $version): View
    {
        Gate::authorize('view', $version);

        return view('payroll-rule-versions.show', [
            'version' => $version,
            'events' => PayrollRuleEvent::where('payroll_rule_version_id', $version->id)->orderByDesc('id')->paginate(10),
        ]);
    }

    public function edit(PayrollRuleVersion $version): View
    {
        Gate::authorize('update', $version);
        abort_if($version->activated_at !== null, 409, 'Activated versions are locked. Create a new dated draft.');

        return view('payroll-rule-versions.form', ['version' => $version, 'classifications' => PersonnelClassification::cases()]);
    }

    public function update(SavePayrollRuleVersionRequest $request, PayrollRuleVersion $version, PayrollRuleVersionService $service): RedirectResponse
    {
        $version = $service->save($request, $request->validated(), $version);

        return redirect()->route('payroll-rule-versions.show', $version)->with('status', 'Draft updated. Earlier configuration change events remain available.');
    }

    public function activate(ActivatePayrollRuleVersionRequest $request, PayrollRuleVersion $version, PayrollRuleVersionService $service): RedirectResponse
    {
        $version = $service->activate($request, $version, $request->validated());

        return redirect()->route('payroll-rule-versions.show', $version)->with('status', 'Configuration version activated and locked for its category, data use and effective date. Real payroll remains disabled.');
    }
}
