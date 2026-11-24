@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm text-slate-400']) }}>
    {{ $value ?? $slot }}
</label>
