@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm text-[#4cc38a]']) }}>
        {{ $status }}
    </div>
@endif
