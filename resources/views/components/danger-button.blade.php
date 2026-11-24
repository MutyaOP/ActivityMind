<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-[#e5484d] px-4 text-sm font-medium text-[#fff] transition-colors hover:bg-[#ec5d62]']) }}>
    {{ $slot }}
</button>
