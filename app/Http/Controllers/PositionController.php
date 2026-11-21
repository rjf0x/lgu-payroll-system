<?php

namespace App\Http\Controllers;

use App\Http\Requests\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        $positions = Position::query()
            ->with('department')
            ->orderBy('position_title')
            ->paginate(15);

        return view('positions.index', compact('positions'));
    }

    public function create(): View
    {
        return view('positions.create', [
            'position' => new Position(['status' => 'ACTIVE']),
            'departments' => Department::orderBy('department_name')->get(),
        ]);
    }

    public function store(PositionRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new Position, $request->validated(), $request->user());

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position created.');
    }

    public function show(Position $position): View
    {
        $position->load('department');

        return view('positions.show', compact('position'));
    }

    public function edit(Position $position): View
    {
        return view('positions.edit', [
            'position' => $position,
            'departments' => Department::orderBy('department_name')->get(),
        ]);
    }

    public function update(PositionRequest $request, Position $position, MasterDataMutationService $mutations): RedirectResponse
    {
        $position = $mutations->update($position, $request->validated(), $request->user());

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position updated.');
    }

    public function destroy(Position $position, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($position, auth()->user());

        return redirect()
            ->route('positions.index')
            ->with('status', 'Position deleted.');
    }
}
