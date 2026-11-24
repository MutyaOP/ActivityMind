<div class="grid gap-5 md:grid-cols-2" x-data="{ freq: '{{ old('frequency', $habit->frequency) }}' }">
    <div class="md:col-span-2">
        <label class="am-label" for="title">{{ __('Название *') }}</label>
        <input id="title" name="title" type="text" required maxlength="255"
               value="{{ old('title', $habit->title) }}" class="am-input" placeholder="{{ __('Например: чтение 20 минут') }}">
        @error('title') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="frequency">{{ __('Периодичность') }}</label>
        <select id="frequency" name="frequency" x-model="freq" class="am-input ">
            <option value="daily">{{ __('Каждый день') }}</option>
            <option value="weekly">{{ __('Несколько раз в неделю') }}</option>
        </select>
        @error('frequency') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div x-show="freq === 'weekly'" x-cloak>
        <label class="am-label" for="target_per_period">{{ __('Раз в неделю') }}</label>
        <input id="target_per_period" name="target_per_period" type="number" min="1" max="7"
               value="{{ old('target_per_period', $habit->target_per_period ?: 3) }}" class="am-input">
        @error('target_per_period') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <input type="hidden" name="color" value="{{ old('color', $habit->color ?: '#ffffff') }}">

    <div class="flex items-end">
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $habit->is_active ?? true))
                   class="am-check">
            {{ __('Привычка активна') }}
        </label>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="am-btn">{{ __('Сохранить') }}</button>
    <a href="{{ route('habits.index') }}" class="text-sm text-slate-400 hover:text-white">{{ __('Отмена') }}</a>
</div>
