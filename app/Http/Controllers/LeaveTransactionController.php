<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $employeeId = $request->integer('employee_id') ?: null;

        $transactions = LeaveTransaction::query()
            ->with(['leaveBalance.employee', 'leaveBalance.leaveType', 'createdBy', 'leaveApplication'])
            ->when($employeeId !== null, fn ($query) => $query->whereHas(
                'leaveBalance',
                fn ($query) => $query->where('employee_id', $employeeId),
            ))
            ->orderByDesc('transaction_date')
            ->orderByDesc('leave_transaction_id')
            ->paginate(25)
            ->withQueryString();

        return view('leave-transactions.index', [
            'transactions' => $transactions,
            'employees' => Employee::orderBy('last_name')->get(),
            'employeeId' => $employeeId,
        ]);
    }
}
