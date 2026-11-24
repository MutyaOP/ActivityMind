<x-app-layout>
    <x-slot name="header">Редактирование привычки</x-slot>

    <form method="POST" action="{{ route('habits.update', $habit) }}" class="am-card max-w-3xl">
        @csrf
        @method('PUT')
        @include('habits._form')
    </form>
</x-app-layout>
