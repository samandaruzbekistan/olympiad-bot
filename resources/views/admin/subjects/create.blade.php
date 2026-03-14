@extends('admin.layout')

@section('title', 'Fan qo‘shish')
@section('page-title', 'Fan qo‘shish')

@section('content')
<div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <form action="{{ route('admin.subjects.store') }}" method="POST" class="space-y-6">
        @csrf
        <div>
            <label for="name" class="block text-base font-medium text-slate-700">Nomi</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                   class="admin-input mt-2 @error('name') border-red-400 @enderror">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Saqlash
            </button>
            <a href="{{ route('admin.subjects.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Bekor qilish
            </a>
        </div>
    </form>
</div>
@endsection
