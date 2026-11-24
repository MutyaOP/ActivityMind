<x-app-layout>
    <x-slot name="header">{{ __('Редактирование задачи') }}</x-slot>

    <form method="POST" action="{{ route('tasks.update', $task) }}" class="am-card max-w-3xl">
        @csrf
        @method('PUT')
        @include('tasks._form')
    </form>
</x-app-layout>
