@props([
    'label' => null,
    'name' => '',
    'type' => 'text',
    'value' => '',
])

@php
    $id = $name ?: 'input-' . uniqid();
    $hasError = $name && $errors->has($name);
    $baseClass = 'block w-full rounded-lg border-gray-300 py-3.5 px-4 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $inputClass = ($label ? 'mt-2 ' : '') . $baseClass . ($hasError ? ' border-red-500' : '');
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
        <label for="{{ $id }}" class="block text-base font-medium text-slate-700">
            {{ $label }}
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ old($name, $value) }}"
        {!! $attributes->except('class')->merge(['class' => trim($inputClass)]) !!}
    />
    @if($name)
        @error($name)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
