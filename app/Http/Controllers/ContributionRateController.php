<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContributionRateRequest;
use App\Models\ContributionRate;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContributionRateController extends Controller
{
    public function index(): View
    {
        $rates = ContributionRate::query()
            ->orderBy('contribution_type')
            ->orderByDesc('effective_from')
            ->paginate(30);

        return view('contribution-rates.index', compact('rates'));
    }

    public function create(): View
    {
        return view('contribution-rates.create', [
            'rate' => new ContributionRate([
                'minimum_compensation' => 0,
                'employee_rate' => 0,
                'employer_rate' => 0,
                'effective_from' => now()->startOfYear()->toDateString(),
            ]),
        ]);
    }

    public function store(ContributionRateRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new ContributionRate, $request->validated(), $request->user());

        return redirect()->route('contribution-rates.index')->with('status', 'Contribution rate created.');
    }

    public function edit(ContributionRate $contributionRate): View
    {
        return view('contribution-rates.edit', ['rate' => $contributionRate]);
    }

    public function update(ContributionRateRequest $request, ContributionRate $contributionRate, MasterDataMutationService $mutations): RedirectResponse
    {
        $contributionRate = $mutations->update($contributionRate, $request->validated(), $request->user());

        return redirect()->route('contribution-rates.index')->with('status', 'Contribution rate updated.');
    }

    public function destroy(ContributionRate $contributionRate, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($contributionRate, auth()->user());

        return redirect()->route('contribution-rates.index')->with('status', 'Contribution rate deleted.');
    }
}
