@extends('admin.layout')

@section('title', 'Koordinatorlar')
@section('page-title', 'Koordinatorlar')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
        <form method="GET">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Ism yoki email..."
                   class="admin-input w-72">
        </form>
        <a href="{{ route('admin.coordinators.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Koordinator qo'shish
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Ism</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Viloyat</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-slate-500">Amallar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($coordinators as $coordinator)
                    <tr>
                        <td class="px-6 py-4 text-sm">{{ $coordinator->name }}</td>
                        <td class="px-6 py-4 text-sm">{{ $coordinator->email }}</td>
                        <td class="px-6 py-4 text-sm">{{ $coordinator->region?->name_uz ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('admin.coordinators.edit', $coordinator) }}" class="text-indigo-600 hover:text-indigo-800">Tahrirlash</a>
                            <form action="{{ route('admin.coordinators.destroy', $coordinator) }}" method="POST" class="inline ml-3" onsubmit="return confirm('Rostdan o‘chirasizmi?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:text-red-800">O'chirish</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">Koordinatorlar topilmadi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-6 py-3">{{ $coordinators->links() }}</div>
</div>
@endsection

