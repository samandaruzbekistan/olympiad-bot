@extends('admin.layout')

@section('title', 'Koordinator paneli')
@section('page-title', 'Koordinator paneli')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
        <h3 class="text-base font-semibold text-slate-800">Olimpiadalar (hududingiz bo'yicha)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Olimpiada</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Jami</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">To'langan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Jarayonda</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Muvaffaqiyatsiz</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Amallar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($olympiads as $olympiad)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $olympiad->title }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ $olympiad->total_participants }}</td>
                        <td class="px-6 py-4 text-sm text-emerald-700">{{ $olympiad->paid_count }}</td>
                        <td class="px-6 py-4 text-sm text-amber-700">{{ $olympiad->pending_count }}</td>
                        <td class="px-6 py-4 text-sm text-red-700">{{ $olympiad->failed_count }}</td>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('admin.coordinator.olympiads.participants', $olympiad) }}" class="text-indigo-600 hover:text-indigo-800">Qatnashuvchilar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">Ma'lumot topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-6 py-3">
        {{ $olympiads->links() }}
    </div>
</div>
@endsection

