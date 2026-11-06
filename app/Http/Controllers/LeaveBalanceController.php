<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveBalanceRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $employeeId = $request->integer('employee_id') ?: null;

        $balances = LeaveBalance::query()
            ->with(['employee', 'leaveType'])
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderBy('employee_id')
            ->orderByDesc('leave_year')
            ->paginate(20)
            ->withQueryString();

        return view('leave-balances.index', [
            'balances' => $balances,
            'employees' => Employee::orderBy('last_name')->get(),
            'employeeId' => $employeeId,
        ]);
    }

    public function create(): View
    {
        return view('leave-balances.create', [
            'balance' => new LeaveBalance([
                'leave_year' => now()->year,
                'opening_balance' => 0,
                'total_credits' => 0,
            ]),
            'employees' => Employee::orderBy('last_name')->get(),
            'leaveTypes' => LeaveType::orderBy('leave_name')->get(),
        ]);
    }

    public function store(LeaveBalanceRequest $request): RedirectResponse
    {
        LeaveBalance::create($this->payload($request, used: 0));

        return redirect()->route('leave-balances.index')->with('status', 'Leave balance created.');
    }

    public function edit(LeaveBalance $leaveBalance): View
    {
        return view('leave-balances.edit', [
            'balance' => $leaveBalance,
            'employees' => Employee::orderBy('last_name')->get(),
            'leaveTypes' => LeaveType::orderBy('leave_name')->get(),
        ]);
    }

    public function update(LeaveBalanceRequest $request, LeaveBalance $leaveBalance): RedirectResponse
    {
        $leaveBalance->update($this->payload($request, used: (float) $leaveBalance->total_used));

        return redirect()->route('leave-balances.index')->with('status', 'Leave balance updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveBalanceRequest $request, float $used): array
    {
        $opening = (float) $request->input('opening_balance');
        $credits = (float) $request->input('total_credits');

        return [
            'employee_id' => $request->integer('employee_id'),
            'leave_type_id' => $request->integer('leave_type_id'),
            'leave_year' => $request->integer('leave_year'),
            'opening_balance' => $opening,
            'total_credits' => $credits,
            'total_used' => $used,
            'available_balance' => $opening + $credits - $used,
        ];
    }
}
