@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null])
<div {{ $attributes->only('class') }}>
    <label class="label" for="{{ $name }}">{{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
        @if ($placeholder !== false)<option value="">{{ $placeholder ?? __('— Select —') }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
