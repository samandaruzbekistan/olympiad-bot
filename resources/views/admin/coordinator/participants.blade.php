@extends('admin.layout')

@section('title', 'Qatnashuvchilar')
@section('page-title', 'Qatnashuvchilar')

@section('content')
<div class="mb-4 grid gap-4 sm:grid-cols-4">
    <div class="rounded-xl border border-slate-200 bg-white p-4">Jami: <b>{{ $counts['total'] }}</b></div>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">To'langan: <b>{{ $counts['paid'] }}</b></div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">Jarayonda: <b>{{ $counts['pending'] }}</b></div>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4">Muvaffaqiyatsiz: <b>{{ $counts['failed'] }}</b></div>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 sm:p-6">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Ism, telefon..." class="admin-input">
            <select name="payment_status" class="admin-input" onchange="this.form.submit()">
                <option value="">Barcha holat</option>
                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>To'langan</option>
                <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>Jarayonda</option>
                <option value="failed" {{ request('payment_status') === 'failed' ? 'selected' : '' }}>Muvaffaqiyatsiz</option>
            </select>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Qidirish</button>
        </form>
        <a href="{{ route('admin.coordinator.olympiads.export', $olympiad) }}?{{ http_build_query(request()->query()) }}"
           class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">
            Excel export
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">F.I.Sh</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Telefon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Hudud</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Sinf</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">To'lov</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Tizim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Chipta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($registrations as $reg)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm">{{ $reg->user?->last_name }} {{ $reg->user?->first_name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->user?->phone }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->user?->region?->name_uz }} / {{ $reg->user?->district?->name_uz }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->user?->grade ? $reg->user->grade.'-sinf' : '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->payment_status }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->payment_system ? strtoupper($reg->payment_system) : '—' }}</td>
                        <td class="px-6 py-4 text-sm">{{ $reg->ticket_number ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">Qatnashuvchilar topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-6 py-3">
        {{ $registrations->links() }}
    </div>
</div>
@endsection

