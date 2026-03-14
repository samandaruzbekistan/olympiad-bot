@extends('admin.layout')

@section('title', 'Create Olympiad')
@section('page-title', 'Create Olympiad')

@section('content')
<div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <form action="{{ route('admin.olympiads.store') }}" method="POST" class="space-y-6">
        @csrf
        <div>
            <label for="title" class="block text-base font-medium text-slate-700">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title') }}" required
                   class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('title') border-red-500 @enderror">
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="block text-base font-medium text-slate-700">Description</label>
            <textarea name="description" id="description" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="subject_id" class="block text-base font-medium text-slate-700">Subject</label>
            <select name="subject_id" id="subject_id" class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— None —</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="price" class="block text-base font-medium text-slate-700">Price</label>
                <input type="number" name="price" id="price" value="{{ old('price', 0) }}" min="0" required
                       class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="start_date" class="block text-base font-medium text-slate-700">Start date</label>
                <input type="datetime-local" name="start_date" id="start_date" value="{{ old('start_date') }}" required
                       class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="location_name" class="block text-base font-medium text-slate-700">Location name</label>
            <input type="text" name="location_name" id="location_name" value="{{ old('location_name') }}" required
                   class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('location_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="location_address" class="block text-base font-medium text-slate-700">Location address</label>
            <input type="text" name="location_address" id="location_address" value="{{ old('location_address') }}"
                   class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('location_address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="block text-base font-medium text-slate-700">Status</label>
            <select name="status" id="status" class="mt-2 block w-full rounded-lg border-slate-300 py-3.5 px-4 text-base shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="draft" {{ old('status', 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Create Olympiad
            </button>
            <a href="{{ route('admin.olympiads.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-3 text-base font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
