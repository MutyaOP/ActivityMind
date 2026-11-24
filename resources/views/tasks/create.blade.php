<x-app-layout>
    <x-slot name="header">{{ __('Новая задача') }}</x-slot>

    <form method="POST" action="{{ route('tasks.store') }}" class="am-card max-w-3xl">
        @csrf
        @include('tasks._form')
    </form>
</x-app-layout>
