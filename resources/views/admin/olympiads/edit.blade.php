@extends('admin.layout')

@section('title', 'Olimpiadani tahrirlash')
@section('page-title', 'Olimpiadani tahrirlash')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
@endpush

@section('content')
<div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <form action="{{ route('admin.olympiads.update', $olympiad) }}" method="POST" class="space-y-6" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div>
            <label for="title" class="block text-base font-medium text-slate-700">Sarlavha</label>
            <input type="text" name="title" id="title" value="{{ old('title', $olympiad->title) }}" required
                   class="admin-input mt-2 @error('title') border-red-400 @enderror">
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="block text-base font-medium text-slate-700">Tavsif</label>
            <textarea name="description" id="description" rows="3" class="admin-input mt-2 block w-full resize-y rounded-xl border border-slate-200 py-3.5 px-4 text-base shadow-sm transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">{{ old('description', $olympiad->description) }}</textarea>
            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="logo" class="block text-base font-medium text-slate-700">Logotip (ixtiyoriy)</label>
            @if($olympiad->logo)
                <div class="mt-2 mb-2">
                    <img src="{{ asset('storage/' . $olympiad->logo) }}" alt="Logo" class="h-20 rounded-lg">
                </div>
            @endif
            <input type="file" name="logo" id="logo" accept="image/*"
                   class="mt-2 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
            @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="block text-base font-medium text-slate-700 mb-2">Fanlar</span>
            <div class="mt-2 space-y-2 rounded-xl border border-slate-200 bg-white p-4">
                @php $selectedSubjects = old('subject_ids', $olympiad->subjects->pluck('id')->toArray()); @endphp
                @foreach($subjects as $subject)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg py-2 hover:bg-slate-50">
                        <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"
                               {{ in_array($subject->id, $selectedSubjects) ? 'checked' : '' }}
                               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-base text-slate-700">{{ $subject->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('subject_ids')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="price" class="block text-base font-medium text-slate-700">Narx</label>
                <input type="number" name="price" id="price" value="{{ old('price', $olympiad->price) }}" min="0" required
                       class="admin-input mt-2">
                @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="capacity" class="block text-base font-medium text-slate-700">Sig'im</label>
                <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $olympiad->capacity) }}" min="0"
                       class="admin-input mt-2" placeholder="Cheksiz">
                @error('capacity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="start_date" class="block text-base font-medium text-slate-700">Boshlanish sanasi</label>
            <input type="datetime-local" name="start_date" id="start_date"
                   value="{{ old('start_date', $olympiad->start_date?->format('Y-m-d\TH:i')) }}" required
                   class="admin-input mt-2">
            @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="location_name" class="block text-base font-medium text-slate-700">Manzil nomi</label>
            <input type="text" name="location_name" id="location_name" value="{{ old('location_name', $olympiad->location_name) }}" required
                   class="admin-input mt-2">
            @error('location_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="location_address" class="block text-base font-medium text-slate-700">Mo'ljal</label>
            <input type="text" name="location_address" id="location_address" value="{{ old('location_address', $olympiad->location_address) }}"
                   class="admin-input mt-2">
            @error('location_address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-base font-medium text-slate-700 mb-2">Xaritada joylashuvni tanlang</label>
            <div id="map" class="h-72 w-full rounded-xl border border-slate-200 overflow-hidden bg-slate-100"></div>
            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $olympiad->latitude) }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $olympiad->longitude) }}">
        </div>
        <div>
            <label for="status" class="block text-base font-medium text-slate-700">Holat</label>
            <select name="status" id="status" class="admin-input mt-2">
                @foreach(['draft' => 'Qoralama', 'active' => 'Faol', 'closed' => 'Yopilgan'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $olympiad->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Saqlash
            </button>
            <a href="{{ route('admin.olympiads.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                Bekor qilish
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var defaultLat = {{ json_encode((float)(old('latitude', $olympiad->latitude) ?: 41.2995)) }};
    var defaultLng = {{ json_encode((float)(old('longitude', $olympiad->longitude) ?: 69.2401)) }};
    var map = L.map('map').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    var marker = null;
    function placeMarker(lat, lng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng]).addTo(map);
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;
    }
    if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
        placeMarker(defaultLat, defaultLng);
    }
    map.on('click', function(e) {
        placeMarker(e.latlng.lat, e.latlng.lng);
    });
});
</script>
@endpush
@endsection
