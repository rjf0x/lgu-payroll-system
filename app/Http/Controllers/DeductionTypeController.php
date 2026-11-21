<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeductionTypeRequest;
use App\Models\DeductionType;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeductionTypeController extends Controller
{
    public function index(): View
    {
        $deductionTypes = DeductionType::query()
            ->orderBy('deduction_category')
            ->orderBy('deduction_name')
            ->paginate(25);

        return view('deduction-types.index', compact('deductionTypes'));
    }

    public function create(): View
    {
        return view('deduction-types.create', [
            'deductionType' => new DeductionType([
                'computation_type' => 'MANUAL',
                'is_statutory' => false,
                'is_pre_tax' => false,
                'status' => 'ACTIVE',
            ]),
        ]);
    }

    public function store(DeductionTypeRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new DeductionType, $request->validated(), $request->user());

        return redirect()->route('deduction-types.index')->with('status', 'Deduction type created.');
    }

    public function edit(DeductionType $deductionType): View
    {
        return view('deduction-types.edit', compact('deductionType'));
    }

    public function update(DeductionTypeRequest $request, DeductionType $deductionType, MasterDataMutationService $mutations): RedirectResponse
    {
        $deductionType = $mutations->update($deductionType, $request->validated(), $request->user());

        return redirect()->route('deduction-types.index')->with('status', 'Deduction type updated.');
    }

    public function destroy(DeductionType $deductionType, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($deductionType, auth()->user());

        return redirect()->route('deduction-types.index')->with('status', 'Deduction type deleted.');
    }
}
