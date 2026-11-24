<x-app-layout>
    <x-slot name="header">Новая привычка</x-slot>

    <form method="POST" action="{{ route('habits.store') }}" class="am-card max-w-3xl">
        @csrf
        @include('habits._form')
    </form>
</x-app-layout>
