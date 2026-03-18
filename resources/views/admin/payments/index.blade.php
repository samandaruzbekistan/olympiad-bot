@extends('admin.layout')

@section('title', "To'lovlar")
@section('page-title', "To'lovlar")

@section('content')
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Muvaffaqiyatli to'lovlar</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($totalCount) }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Jami daromad</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ number_format($totalAmount) }} so'm</p>
    </div>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex flex-1 gap-3">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Ism, telefon, tranzaksiya ID…"
                   class="admin-input block w-full max-w-sm">
            <select name="status" class="admin-input w-auto" onchange="this.form.submit()">
                <option value="">Barchasi</option>
                @foreach(['pending' => 'Kutilmoqda', 'success' => 'Muvaffaqiyatli', 'failed' => 'Muvaffaqiyatsiz'] as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Foydalanuvchi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Olimpiada</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Summa</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Holat</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Tizim</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sana</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($payments as $payment)
                    @php
                        $user = $payment->registration?->user;
                        $olympiad = $payment->registration?->olympiad;
                        $statusStyles = [
                            'pending' => 'bg-amber-100 text-amber-700',
                            'success' => 'bg-emerald-100 text-emerald-700',
                            'failed' => 'bg-red-100 text-red-700',
                        ];
                        $statusLabels = ['pending' => 'Kutilmoqda', 'success' => 'Muvaffaqiyatli', 'failed' => 'Muvaffaqiyatsiz'];
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <div class="text-sm font-medium text-slate-900">{{ $user?->first_name }} {{ $user?->last_name }}</div>
                            <div class="text-xs text-slate-500">{{ $user?->phone }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $olympiad?->title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ number_format($payment->amount) }} so'm</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles[$payment->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $statusLabels[$payment->status] ?? $payment->status }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">
                            {{ $payment->registration?->payment_system ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">
                            {{ $payment->paid_at?->format('d.m.Y H:i') ?? $payment->created_at->format('d.m.Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">To'lovlar topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            {{ $payments->links() }}
        </div>
    @endif
</div>
@endsection
