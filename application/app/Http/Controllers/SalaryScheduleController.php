<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\SaveSalaryScheduleRequest;
use App\Models\SalaryRate;
use App\Models\SalarySchedule;
use App\Rules\Utf8Text;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalaryScheduleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalarySchedule::class);
        $query = SalarySchedule::query();
        if ($request->user()->role !== Role::Administrator) {
            $query->whereNotNull('published_at');
        }

        return view('salary-schedules.index', ['schedules' => $query->orderByDesc('effective_from')->orderByDesc('id')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalarySchedule::class);

        return view('salary-schedules.form', ['schedule' => new SalarySchedule, 'classifications' => PersonnelClassification::cases()]);
    }

    public function store(SaveSalaryScheduleRequest $request, SalaryService $service): RedirectResponse
    {
        return redirect()->route('salary-schedules.show', $service->saveSchedule($request, $request->validated()))->with('status', 'Draft salary schedule created.');
    }

    public function show(SalarySchedule $schedule): View
    {
        Gate::authorize('view', $schedule);

        return view('salary-schedules.show', ['schedule' => $schedule, 'rates' => $schedule->rates()->orderBy('grade')->orderBy('step')->paginate(30)]);
    }

    public function edit(SalarySchedule $schedule): View
    {
        Gate::authorize('update', $schedule);
        abort_if($schedule->published_at !== null, 409, 'Published schedules are locked.');

        return view('salary-schedules.form', ['schedule' => $schedule, 'classifications' => PersonnelClassification::cases()]);
    }

    public function update(SaveSalaryScheduleRequest $request, SalarySchedule $schedule, SalaryService $service): RedirectResponse
    {
        $service->saveSchedule($request, $request->validated(), $schedule);

        return redirect()->route('salary-schedules.show', $schedule)->with('status', 'Draft salary schedule updated.');
    }

    private function rateData(Request $request): array
    {
        return $request->validate([
            'grade' => ['required', 'integer', 'between:1,65535'], 'step' => ['required', 'integer', 'between:1,65535'],
            'monthly_salary' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', 'gt:0'],
        ]);
    }

    public function storeRate(Request $request, SalarySchedule $schedule, SalaryService $service): RedirectResponse
    {
        Gate::authorize('update', $schedule);
        $service->saveRate($request, $schedule, $this->rateData($request));

        return redirect()->route('salary-schedules.show', $schedule)->with('status', 'Grade/step rate saved.');
    }

    public function updateRate(Request $request, SalarySchedule $schedule, SalaryRate $rate, SalaryService $service): RedirectResponse
    {
        Gate::authorize('update', $schedule);
        $service->saveRate($request, $schedule, $this->rateData($request), $rate);

        return redirect()->route('salary-schedules.show', $schedule)->with('status', 'Draft rate updated.');
    }

    public function publish(Request $request, SalarySchedule $schedule, SalaryService $service): RedirectResponse
    {
        Gate::authorize('update', $schedule);
        $data = $request->validate(['authority_reference' => ['required', 'string', new Utf8Text, 'max:255'], 'authorization_confirmed' => ['required', 'accepted']]);
        $service->publish($request, $schedule, $data['authority_reference']);

        return redirect()->route('salary-schedules.show', $schedule)->with('status', 'Salary schedule published and locked.');
    }
}
