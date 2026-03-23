@extends('admin.layout')

@section('title', "Ro'yxatlar")
@section('page-title', "Ro'yxatlar")

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

    {{-- Filters --}}
    <div class="border-b border-slate-200 p-4 sm:p-6">
        <form method="GET" id="filter-form" class="flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Ism, telefon, chipta…"
                   class="admin-input block w-full max-w-xs">

            <select name="olympiad_id" class="admin-input w-auto" onchange="this.form.submit()">
                <option value="">Barcha olimpiadalar</option>
                @foreach($olympiads as $ol)
                    <option value="{{ $ol->id }}" {{ request('olympiad_id') == $ol->id ? 'selected' : '' }}>
                        {{ $ol->title }}
                    </option>
                @endforeach
            </select>

            <select name="payment_status" class="admin-input w-auto" onchange="this.form.submit()">
                <option value="">Barcha holat</option>
                @foreach(['paid' => "To'langan", 'pending' => 'Kutilmoqda', 'failed' => 'Bekor qilindi'] as $val => $label)
                    <option value="{{ $val }}" {{ request('payment_status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                🔍 Qidirish
            </button>

            <a href="{{ route('admin.registrations.export', request()->query()) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                📥 Excel
            </a>

            <span class="ml-auto self-center text-sm text-slate-500">
                Jami: <b>{{ $registrations->total() }}</b> ta
            </span>
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Foydalanuvchi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Olimpiada</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Viloyat / Tuman</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Maktab / Sinf</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">To'lov holati</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">To'lov tizimi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Chipta</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sana</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @php
                    $payStyles = [
                        'paid'    => 'bg-emerald-100 text-emerald-700',
                        'pending' => 'bg-amber-100 text-amber-700',
                        'failed'  => 'bg-red-100 text-red-700',
                    ];
                    $payLabels = [
                        'paid'    => "To'langan",
                        'pending' => 'Kutilmoqda',
                        'failed'  => 'Bekor qilindi',
                    ];
                @endphp
                @forelse($registrations as $i => $registration)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-400 sm:px-6">
                            {{ $registrations->firstItem() + $i }}
                        </td>
                        <td class="px-4 py-4 sm:px-6">
                            <div class="text-sm font-medium text-slate-900">
                                {{ $registration->user?->last_name }} {{ $registration->user?->first_name }}
                            </div>
                            <div class="text-xs text-slate-500">{{ $registration->user?->phone }}</div>
                            @if($registration->user?->birth_date)
                                <div class="text-xs text-slate-400">{{ $registration->user->birth_date->format('d.m.Y') }}</div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            {{ $registration->olympiad?->title ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            <div>{{ $registration->user?->region?->name_uz ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $registration->user?->district?->name_uz ?? '' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            <div>{{ $registration->user?->school ?? '—' }}</div>
                            <div class="text-xs text-slate-400">
                                {{ $registration->user?->grade ? $registration->user->grade . '-sinf' : '' }}
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $payStyles[$registration->payment_status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $payLabels[$registration->payment_status] ?? $registration->payment_status }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            {{ $registration->payment_system ? strtoupper($registration->payment_system) : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            {{ $registration->ticket_number ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">
                            {{ $registration->created_at->format('d.m.Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">
                            Ro'yxatlar topilmadi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($registrations->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            {{ $registrations->links() }}
        </div>
    @endif
</div>
@endsection
