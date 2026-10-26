@extends('layouts.app')
@section('content')
    <h1 class="text-3xl font-bold">Payroll configuration versions</h1>
    <p class="mt-3">Review dated contribution and withholding settings. Activation locks a version; previous versions and saved payroll results remain unchanged. Real payroll processing is still disabled.</p>
    @can('create', \App\Models\PayrollRuleVersion::class)
        <a href="{{ route('payroll-rule-versions.create') }}" class="mt-5 inline-block rounded bg-teal-800 px-4 py-3 font-semibold text-white">Create configuration draft</a>
    @endcan
    <div class="table-scroll mt-6" tabindex="0" role="region" aria-label="Payroll configuration versions">
        <table class="w-full text-left"><thead><tr><th class="p-4">Version / name</th><th class="p-4">Category</th><th class="p-4">Data use</th><th class="p-4">Effective from</th><th class="p-4">Status</th></tr></thead><tbody>
        @forelse ($versions as $version)
            <tr><td class="p-4"><a href="{{ route('payroll-rule-versions.show', $version) }}" class="font-semibold text-teal-800 underline">V{{ $version->id }} · {{ $version->name }}</a></td><td class="p-4">{{ $version->personnel_classification->label() }}</td><td class="p-4">{{ $version->is_demonstration ? 'Demonstration only' : ($version->activated_at ? 'External approval recorded' : 'External approval required') }}</td><td class="p-4">{{ $version->effective_from->format('Y-m-d') }}</td><td class="p-4">{{ $version->activated_at ? 'Activated / locked' : 'Draft' }}</td></tr>
        @empty
            <tr><td colspan="5" class="p-4">No configuration versions available. Where no activated version applies, new reference previews use the existing unverified built-in profile within its review year.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    <div class="mt-5">{{ $versions->links() }}</div>
@endsection
