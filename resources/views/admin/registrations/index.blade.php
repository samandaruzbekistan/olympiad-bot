@extends('admin.layout')

@section('title', "Ro'yxatlar")
@section('page-title', "Ro'yxatlar")

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex flex-1 gap-3">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Ism, telefon, chipta bo'yicha qidirish…"
                   class="admin-input block w-full max-w-sm">
            <select name="payment_status" class="admin-input w-auto" onchange="this.form.submit()">
                <option value="">Barchasi</option>
                @foreach(['paid' => "To'langan", 'pending' => 'Kutilmoqda', 'failed' => 'Muvaffaqiyatsiz'] as $val => $label)
                    <option value="{{ $val }}" {{ request('payment_status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <span class="text-sm text-slate-500">Jami: {{ $registrations->total() }} ta</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Foydalanuvchi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Olimpiada</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">To'lov holati</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">To'lov tizimi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Chipta</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sana</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @php
                    $payStyles = ['paid' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', 'failed' => 'bg-red-100 text-red-700'];
                    $payLabels = ['paid' => "To'langan", 'pending' => 'Kutilmoqda', 'failed' => 'Muvaffaqiyatsiz'];
                @endphp
                @forelse($registrations as $registration)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <div class="text-sm font-medium text-slate-900">{{ $registration->user?->first_name }} {{ $registration->user?->last_name }}</div>
                            <div class="text-xs text-slate-500">{{ $registration->user?->phone }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $registration->olympiad?->title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $payStyles[$registration->payment_status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $payLabels[$registration->payment_status] ?? $registration->payment_status }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            {{ $registration->payment_system ? strtoupper($registration->payment_system) : '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $registration->ticket_number ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">{{ $registration->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">Ro'yxatlar topilmadi.</td>
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
