<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\PayrollFinalization;
use App\Services\PayrollDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PayrollDocumentController extends Controller
{
    private function document(PayrollBatch $batch, PayrollFinalization $finalization, PayrollDocumentService $service): array
    {
        Gate::authorize('view', $batch);

        return ['batch' => $batch, 'finalization' => $finalization, 'document' => $service->prepare($batch, $finalization), 'pdf' => false];
    }

    private function privateResponse(string $content, string $type = 'text/html; charset=UTF-8', ?string $filename = null): Response
    {
        $response = response($content, 200, [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'Expires' => '0', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
        ]);
        if ($filename !== null) {
            $response->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        }

        return $response;
    }

    private function filename(array $data, string $kind, string $extension): string
    {
        return $kind.'-'.$data['document']['period'].'-batch-'.$data['batch']->id.'-v'.$data['finalization']->version.'.'.$extension;
    }

    public function index(Request $request, PayrollBatch $batch, PayrollDocumentService $service): Response
    {
        Gate::authorize('view', $batch);
        $finalization = PayrollFinalization::where('payroll_batch_id', $batch->id)->firstOrFail();
        $data = $this->document($batch, $finalization, $service);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = $data['document']['rows'];
        // Reject unavailable pages before multiplying an untrusted page number.
        abort_if($page > (int) ceil(count($rows) / 20), 404);
        $employees = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 20, 20), count($rows), 20, $page, ['path' => $request->url()]);

        return $this->privateResponse(view('payroll-documents.index', [...$data, 'employees' => $employees])->render());
    }

    public function register(PayrollBatch $batch, PayrollFinalization $finalization, PayrollDocumentService $service): Response
    {
        return $this->privateResponse(view('payroll-documents.register', $this->document($batch, $finalization, $service))->render());
    }

    public function registerPdf(PayrollBatch $batch, PayrollFinalization $finalization, PayrollDocumentService $service): Response
    {
        $data = $this->document($batch, $finalization, $service);

        return $this->privateResponse($service->pdf('payroll-documents.register', $data, true), 'application/pdf', $this->filename($data, 'payroll-register', 'pdf'));
    }

    public function registerCsv(PayrollBatch $batch, PayrollFinalization $finalization, PayrollDocumentService $service): Response
    {
        $data = $this->document($batch, $finalization, $service);

        return $this->privateResponse($service->csv($data['document'], $finalization), 'text/csv; charset=UTF-8', $this->filename($data, 'payroll-register', 'csv'));
    }

    public function payslip(PayrollBatch $batch, PayrollFinalization $finalization, string $employee, PayrollDocumentService $service): Response
    {
        $data = $this->document($batch, $finalization, $service);
        $data['row'] = $service->employee($data['document'], $employee);

        return $this->privateResponse(view('payroll-documents.payslip', $data)->render());
    }

    public function payslipPdf(PayrollBatch $batch, PayrollFinalization $finalization, string $employee, PayrollDocumentService $service): Response
    {
        $data = $this->document($batch, $finalization, $service);
        $data['row'] = $service->employee($data['document'], $employee);

        return $this->privateResponse($service->pdf('payroll-documents.payslip', $data, false), 'application/pdf', $this->filename($data, 'payslip-employee-'.$employee, 'pdf'));
    }
}
