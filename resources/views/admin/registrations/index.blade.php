@extends('admin.layout')

@section('title', 'Registrations')
@section('page-title', 'Registrations')

@section('content')
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <form method="GET" class="flex-1">
            <label for="search" class="sr-only">Search</label>
            <input type="search" name="search" id="search" value="{{ request('search') }}"
                   placeholder="Search by name, phone, ticket…"
                   class="block w-full max-w-sm rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">User</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Olympiad</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 sm:px-6">Ticket</th>
                    <th scope="col" class="relative px-4 py-3 sm:px-6"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($registrations as $registration)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-900 sm:px-6">
                            {{ $registration->user?->first_name }} {{ $registration->user?->last_name }}
                            <span class="block text-xs text-slate-500">{{ $registration->user?->phone }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $registration->olympiad?->title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $registration->status }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600 sm:px-6">{{ $registration->ticket_number ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                            <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                            <span class="mx-1 text-slate-300">·</span>
                            <button type="button" class="font-medium text-red-600 hover:text-red-500">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500 sm:px-6">No registrations found.</td>
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
