@extends('admin.layout')

@section('title', 'Koordinator yaratish')
@section('page-title', 'Koordinator yaratish')

@section('content')
<div class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <form action="{{ route('admin.coordinators.store') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Ism</label>
            <input name="name" value="{{ old('name') }}" class="admin-input mt-1" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Email (login)</label>
            <input type="email" name="email" value="{{ old('email') }}" class="admin-input mt-1" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Parol</label>
            <input type="password" name="password" class="admin-input mt-1" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Parolni tasdiqlang</label>
            <input type="password" name="password_confirmation" class="admin-input mt-1" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Biriktirilgan viloyat</label>
            <select name="region_id" class="admin-input mt-1" required>
                <option value="">Tanlang</option>
                @foreach($regions as $region)
                    <option value="{{ $region->id }}" @selected(old('region_id') == $region->id)>{{ $region->name_uz }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Saqlash</button>
    </form>
</div>
@endsection

