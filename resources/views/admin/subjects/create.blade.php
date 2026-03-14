@extends('admin.layout')

@section('title', 'Create Subject')
@section('page-title', 'Create Subject')

@section('content')
<div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <form action="{{ route('admin.subjects.store') }}" method="POST" class="space-y-6">
        @csrf
        <div>
            <label for="name" class="block text-base font-medium text-slate-700">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Create Subject
            </button>
            <a href="{{ route('admin.subjects.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
