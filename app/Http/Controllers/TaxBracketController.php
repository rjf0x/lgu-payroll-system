<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaxBracketRequest;
use App\Models\TaxBracket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxBracketController extends Controller
{
    public function index(): View
    {
        $brackets = TaxBracket::query()
            ->orderBy('tax_type')
            ->orderBy('effective_from')
            ->orderBy('minimum_income')
            ->paginate(30);

        return view('tax-brackets.index', compact('brackets'));
    }

    public function create(): View
    {
        return view('tax-brackets.create', [
            'bracket' => new TaxBracket([
                'tax_type' => 'ANNUAL',
                'pay_frequency' => 'ANNUAL',
                'minimum_income' => 0,
                'base_tax' => 0,
                'marginal_tax_rate' => 0,
                'effective_from' => now()->startOfYear()->toDateString(),
            ]),
        ]);
    }

    public function store(TaxBracketRequest $request): RedirectResponse
    {
        TaxBracket::create($request->validated());

        return redirect()->route('tax-brackets.index')->with('status', 'Tax bracket created.');
    }

    public function edit(TaxBracket $taxBracket): View
    {
        return view('tax-brackets.edit', ['bracket' => $taxBracket]);
    }

    public function update(TaxBracketRequest $request, TaxBracket $taxBracket): RedirectResponse
    {
        $taxBracket->update($request->validated());

        return redirect()->route('tax-brackets.index')->with('status', 'Tax bracket updated.');
    }

    public function destroy(TaxBracket $taxBracket): RedirectResponse
    {
        $taxBracket->delete();

        return redirect()->route('tax-brackets.index')->with('status', 'Tax bracket deleted.');
    }
}
