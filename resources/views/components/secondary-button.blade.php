<button {{ $attributes->merge(['type' => 'button', 'class' => 'am-btn-outline disabled:opacity-25']) }}>
    {{ $slot }}
</button>
