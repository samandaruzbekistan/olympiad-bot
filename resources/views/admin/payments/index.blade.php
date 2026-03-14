@extends('admin.layout')

@section('title', 'To\'lovlar')
@section('page-title', 'To\'lovlar')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex-1">
            <label for="search" class="sr-only">Qidirish</label>
            <input type="search" name="search" id="search" value="{{ request('search') }}"
                   placeholder="Tranzaksiya ID bo‘yicha qidirish…"
                   class="admin-input block w-full max-w-sm">
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Summa</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Tizim</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Tranzaksiya ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Holat</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">To‘langan sana</th>
                    <th scope="col" class="relative px-4 py-3 sm:px-6"><span class="sr-only">Amallar</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @php
                    $statusLabels = ['pending' => 'Kutilmoqda', 'success' => 'Muvaffaqiyatli', 'failed' => 'Muvaffaqiyatsiz'];
                @endphp
                @forelse($payments as $payment)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">#{{ $payment->id }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ number_format($payment->amount) }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $payment->payment_system }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $payment->transaction_id ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            @php
                                $statusStyles = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'success' => 'bg-emerald-100 text-emerald-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                                $style = $statusStyles[$payment->status] ?? 'bg-slate-100 text-slate-600';
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $style }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $payment->paid_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                            <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Ko‘rish</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">To‘lovlar topilmadi.</td>
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
