@extends('admin.layout')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex-1">
            <label for="search" class="sr-only">Search</label>
            <input type="search" name="search" id="search" value="{{ request('search') }}"
                   placeholder="Search by transaction ID…"
                   class="block w-full max-w-sm rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Amount</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">System</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Transaction ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Paid at</th>
                    <th scope="col" class="relative px-4 py-3 sm:px-6"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
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
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $style }}">{{ $payment->status }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $payment->paid_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                            <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">No payments found.</td>
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
