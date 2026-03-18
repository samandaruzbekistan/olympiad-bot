@extends('admin.layout')

@section('title', 'Chiptalar')
@section('page-title', 'Chiptalar')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex-1">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Chipta raqami, ism…"
                   class="admin-input block w-full max-w-sm">
        </form>
        <span class="text-sm text-slate-500">Jami: {{ $tickets->total() }} ta</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Chipta raqami</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Foydalanuvchi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Olimpiada</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Kirish holati</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Yaratilgan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($tickets as $ticket)
                    @php
                        $user = $ticket->registration?->user;
                        $olympiad = $ticket->registration?->olympiad;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ $ticket->ticket_number }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <div class="text-sm font-medium text-slate-900">{{ $user?->first_name }} {{ $user?->last_name }}</div>
                            <div class="text-xs text-slate-500">{{ $user?->phone }}</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $olympiad?->title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            @if($ticket->checked_in)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Kirgan {{ $ticket->checked_at?->format('H:i') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Kirmagan</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">{{ $ticket->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">Chiptalar topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tickets->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            {{ $tickets->links() }}
        </div>
    @endif
</div>
@endsection
