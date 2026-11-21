<?php

namespace App\Http\Controllers;

use App\Http\Requests\EarningTypeRequest;
use App\Models\EarningType;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EarningTypeController extends Controller
{
    public function index(): View
    {
        $earningTypes = EarningType::query()->orderBy('earning_name')->paginate(15);

        return view('earning-types.index', compact('earningTypes'));
    }

    public function create(): View
    {
        return view('earning-types.create', [
            'earningType' => new EarningType([
                'computation_type' => 'MANUAL',
                'is_taxable' => false,
                'is_recurring' => true,
                'status' => 'ACTIVE',
            ]),
        ]);
    }

    public function store(EarningTypeRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new EarningType, $request->validated(), $request->user());

        return redirect()->route('earning-types.index')->with('status', 'Earning type created.');
    }

    public function edit(EarningType $earningType): View
    {
        return view('earning-types.edit', compact('earningType'));
    }

    public function update(EarningTypeRequest $request, EarningType $earningType, MasterDataMutationService $mutations): RedirectResponse
    {
        $earningType = $mutations->update($earningType, $request->validated(), $request->user());

        return redirect()->route('earning-types.index')->with('status', 'Earning type updated.');
    }

    public function destroy(EarningType $earningType, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($earningType, auth()->user());

        return redirect()->route('earning-types.index')->with('status', 'Earning type deleted.');
    }
}
