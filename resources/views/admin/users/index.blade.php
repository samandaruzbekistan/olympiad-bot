@extends('admin.layout')

@section('title', 'Foydalanuvchilar')
@section('page-title', 'Foydalanuvchilar')

@section('content')
<div class="space-y-4">
    {{-- Filter panel --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <form method="GET" id="filterForm" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-600 mb-1">Qidirish</label>
                    <input type="search" name="search" id="search" value="{{ request('search') }}"
                           placeholder="Ism, telefon, ID…"
                           class="admin-input block w-full">
                </div>
                <div>
                    <label for="region_id" class="block text-sm font-medium text-slate-600 mb-1">Viloyat</label>
                    <select name="region_id" id="region_id" class="admin-input block w-full" onchange="document.getElementById('district_id').value=''; this.form.submit()">
                        <option value="">Barchasi</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}" {{ request('region_id') == $region->id ? 'selected' : '' }}>{{ $region->name_uz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="district_id" class="block text-sm font-medium text-slate-600 mb-1">Tuman</label>
                    <select name="district_id" id="district_id" class="admin-input block w-full" {{ $districts instanceof \Illuminate\Support\Collection && $districts->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Barchasi</option>
                        @foreach($districts as $district)
                            <option value="{{ $district->id }}" {{ request('district_id') == $district->id ? 'selected' : '' }}>{{ $district->name_uz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="grade" class="block text-sm font-medium text-slate-600 mb-1">Sinf</label>
                    <select name="grade" id="grade" class="admin-input block w-full">
                        <option value="">Barchasi</option>
                        @for($i = 1; $i <= 11; $i++)
                            <option value="{{ $i }}" {{ request('grade') == $i ? 'selected' : '' }}>{{ $i }}-sinf</option>
                        @endfor
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Qidirish
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:bg-slate-50">
                        Tozalash
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Results --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <span class="text-sm text-slate-500">Topildi: <b>{{ $users->total() }}</b> ta foydalanuvchi</span>
            <a href="{{ route('admin.users.export', request()->query()) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel export
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Ism / Familiya</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Telefon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Viloyat / Tuman</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Maktab</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sinf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Fanlar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Sana</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                            <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                <div class="text-sm font-medium text-slate-900">{{ $user->first_name }} {{ $user->last_name }}</div>
                                <div class="text-xs text-slate-500">ID: {{ $user->telegram_id }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $user->phone }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600 sm:px-6">
                                {{ $user->region?->name_uz ?? '—' }}
                                @if($user->district)
                                    <span class="block text-xs text-slate-500">{{ $user->district->name_uz }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $user->school ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $user->grade ? $user->grade . '-sinf' : '—' }}</td>
                            <td class="px-4 py-4 text-sm text-slate-600 sm:px-6">
                                @if($user->subjects->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($user->subjects as $subj)
                                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $subj->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500 sm:px-6">{{ $user->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">Foydalanuvchilar topilmadi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

@endsection
