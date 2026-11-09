<?php

namespace App\Http\Controllers;

use App\Models\PayrollRegistration;
use App\Services\PayrollVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PayrollRegistrationController extends Controller
{
    public function show(PayrollRegistration $payrollRegistration): View
    {
        $payrollRegistration->load(['payrollRun', 'latestVerification.verifiedBy']);
        $checks = $payrollRegistration->verificationLogs()->with('verifiedBy')
            ->orderByDesc('verified_at')->orderByDesc('verification_id')->paginate(15)->withQueryString();

        return view('blockchain.registry-show', ['registration' => $payrollRegistration, 'checks' => $checks]);
    }

    public function verify(PayrollRegistration $payrollRegistration, PayrollVerificationService $verification): RedirectResponse
    {
        $verification->verify($payrollRegistration, auth()->user());

        return redirect()->route('payroll-registrations.show', $payrollRegistration);
    }
}
