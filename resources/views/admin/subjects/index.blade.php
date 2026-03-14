@extends('admin.layout')

@section('title', 'Fanlar')
@section('page-title', 'Fanlar')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex-1">
            <label for="search" class="sr-only">Qidirish</label>
            <input type="search" name="search" id="search" value="{{ request('search') }}"
                   placeholder="Fan bo‘yicha qidirish…"
                   class="admin-input block w-full max-w-sm">
        </form>
        <a href="{{ route('admin.subjects.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Fan qo‘shish
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Nomi</th>
                    <th scope="col" class="relative px-4 py-3 sm:px-6"><span class="sr-only">Amallar</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($subjects as $subject)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">{{ $subject->name }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                            <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Tahrirlash</a>
                            <span class="mx-1 text-slate-300">·</span>
                            <button type="button" class="font-medium text-red-600 hover:text-red-500">O‘chirish</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">Fanlar topilmadi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($subjects->hasPages())
        <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
            {{ $subjects->links() }}
        </div>
    @endif
</div>
@endsection
