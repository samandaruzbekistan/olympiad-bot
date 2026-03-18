@extends('admin.layout')

@section('title', 'Xabar yuborish')
@section('page-title', 'Barcha obunachilarga xabar yuborish')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500">Jami obunachilarga yuboriladi</p>
                <p class="text-2xl font-semibold text-slate-900">{{ number_format($totalUsers) }} ta</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <form action="{{ route('admin.broadcast.send') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
              onsubmit="return confirm('Rostdan ham barcha foydalanuvchilarga xabar yuborishni xohlaysizmi?')">
            @csrf

            {{-- Media fayl --}}
            <div>
                <label class="block text-base font-medium text-slate-700">Rasm yoki video (ixtiyoriy)</label>
                <p class="mt-1 text-sm text-slate-500">JPG, PNG, GIF, MP4, MOV — maks 50 MB</p>

                <div class="mt-3" id="dropZone">
                    <label for="media" class="group relative flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 transition hover:border-indigo-400 hover:bg-indigo-50/50">
                        <div id="uploadPlaceholder" class="text-center">
                            <svg class="mx-auto h-10 w-10 text-slate-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-slate-600 group-hover:text-indigo-600">Fayl tanlash yoki bu yerga tashlash</p>
                            <p class="mt-1 text-xs text-slate-400">Rasm yoki video</p>
                        </div>
                        <div id="mediaPreview" class="hidden w-full"></div>
                        <input type="file" name="media" id="media" accept="image/*,video/*" class="sr-only">
                    </label>
                </div>

                <div id="mediaInfo" class="mt-2 hidden items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                    <span id="mediaFileName" class="text-sm text-slate-600"></span>
                    <button type="button" id="removeMedia" class="text-sm font-medium text-red-600 hover:text-red-500">O'chirish</button>
                </div>
                @error('media')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Xabar matni --}}
            <div>
                <label for="message" class="block text-base font-medium text-slate-700">Xabar matni</label>
                <p class="mt-1 text-sm text-slate-500">
                    HTML teglar: &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;code&gt;.
                    Media bilan birga yuborilsa, caption sifatida ko'rinadi.
                </p>
                <textarea name="message" id="message" rows="6" maxlength="4096"
                          class="mt-2 block w-full resize-y rounded-xl border border-slate-200 py-3.5 px-4 text-base shadow-sm transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 @error('message') border-red-400 @enderror"
                          placeholder="Xabar matnini kiriting…">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400"><span id="charCount">0</span> / 4096</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-medium text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Yuborish
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.getElementById('message');
    var counter = document.getElementById('charCount');
    var mediaInput = document.getElementById('media');
    var preview = document.getElementById('mediaPreview');
    var placeholder = document.getElementById('uploadPlaceholder');
    var mediaInfo = document.getElementById('mediaInfo');
    var mediaFileName = document.getElementById('mediaFileName');
    var removeBtn = document.getElementById('removeMedia');

    function updateCount() { counter.textContent = textarea.value.length; }
    textarea.addEventListener('input', updateCount);
    updateCount();

    mediaInput.addEventListener('change', function() {
        var file = this.files[0];
        if (!file) { resetPreview(); return; }

        mediaFileName.textContent = file.name + ' (' + formatSize(file.size) + ')';
        mediaInfo.classList.remove('hidden');
        mediaInfo.classList.add('flex');

        preview.innerHTML = '';
        placeholder.classList.add('hidden');
        preview.classList.remove('hidden');

        if (file.type.startsWith('image/')) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.className = 'mx-auto max-h-64 rounded-lg object-contain';
            img.onload = function() { URL.revokeObjectURL(this.src); };
            preview.appendChild(img);
        } else if (file.type.startsWith('video/')) {
            var vid = document.createElement('video');
            vid.src = URL.createObjectURL(file);
            vid.className = 'mx-auto max-h-64 rounded-lg';
            vid.controls = true;
            vid.muted = true;
            preview.appendChild(vid);
        }
    });

    removeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        mediaInput.value = '';
        resetPreview();
    });

    function resetPreview() {
        preview.innerHTML = '';
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        mediaInfo.classList.add('hidden');
        mediaInfo.classList.remove('flex');
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Drag & drop
    var zone = document.getElementById('dropZone');
    ['dragenter', 'dragover'].forEach(function(ev) {
        zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.add('ring-2', 'ring-indigo-400'); });
    });
    ['dragleave', 'drop'].forEach(function(ev) {
        zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.remove('ring-2', 'ring-indigo-400'); });
    });
    zone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            mediaInput.files = files;
            mediaInput.dispatchEvent(new Event('change'));
        }
    });
});
</script>
@endpush
@endsection
