@extends('admin.layout')

@section('title', 'Olimpiadalar')
@section('page-title', 'Olimpiadalar')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex flex-1 gap-3">
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Olimpiada bo'yicha qidirish…"
                   class="admin-input block w-full max-w-sm">
            <select name="status" class="admin-input w-auto" onchange="this.form.submit()">
                <option value="">Barchasi</option>
                @foreach(['draft' => 'Qoralama', 'active' => 'Faol', 'closed' => 'Yopilgan'] as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.olympiads.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Olimpiada qo'shish
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sarlavha</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Fan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Narx</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Boshlanish sanasi</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Holat</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Qatnashchilar</th>
                    <th class="relative px-4 py-3 sm:px-6"><span class="sr-only">Amallar</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @php
                    $statusLabels = ['draft' => 'Qoralama', 'active' => 'Faol', 'closed' => 'Yopilgan'];
                    $statusStyles = ['draft' => 'bg-slate-100 text-slate-700', 'active' => 'bg-emerald-100 text-emerald-700', 'closed' => 'bg-slate-200 text-slate-600'];
                @endphp
                @forelse($olympiads as $olympiad)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ $olympiad->title }}</td>
                        <td class="px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $olympiad->subjects->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ number_format($olympiad->price) }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $olympiad->start_date?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusStyles[$olympiad->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $statusLabels[$olympiad->status] ?? $olympiad->status }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $olympiad->registrations_count }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                            <a href="{{ route('admin.olympiads.edit', $olympiad) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Tahrirlash</a>
                            <span class="mx-1 text-slate-300">·</span>
                            <form action="{{ route('admin.olympiads.destroy', $olympiad) }}" method="POST" class="inline" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:text-red-500">O'chirish</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">Olimpiadalar topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($olympiads->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            {{ $olympiads->links() }}
        </div>
    @endif
</div>
@endsection
