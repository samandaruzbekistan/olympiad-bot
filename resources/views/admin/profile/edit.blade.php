@extends('admin.layout')

@section('title', 'Profil')
@section('page-title', 'Profil')

@section('content')
<div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-slate-700">Ism</label>
            <input name="name" value="{{ old('name', $admin->name) }}" class="admin-input mt-1" required>
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $admin->email) }}" class="admin-input mt-1" required>
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <hr class="border-slate-200">
        <p class="text-sm text-slate-500">Parolni o'zgartirmoqchi bo'lsangiz to'ldiring.</p>

        <div>
            <label class="block text-sm font-medium text-slate-700">Yangi parol</label>
            <input type="password" name="password" class="admin-input mt-1">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Parolni tasdiqlang</label>
            <input type="password" name="password_confirmation" class="admin-input mt-1">
        </div>

        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Saqlash
        </button>
    </form>
</div>
@endsection

