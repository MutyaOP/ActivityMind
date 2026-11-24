<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="am-label" for="title">Название *</label>
        <input id="title" name="title" type="text" required maxlength="255"
               value="{{ old('title', $task->title) }}" class="am-input" placeholder="Например: подготовить главу диплома">
        @error('title') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="am-label" for="description">Описание</label>
        <textarea id="description" name="description" rows="4" class="am-input">{{ old('description', $task->description) }}</textarea>
        @error('description') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="category_id">Категория</label>
        <select id="category_id" name="category_id" class="am-input">
            <option value="">— без категории —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $task->category_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error('category_id') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="due_date">Срок</label>
        <input id="due_date" name="due_date" type="date"
               value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" class="am-input">
        @error('due_date') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="priority">Приоритет</label>
        <select id="priority" name="priority" class="am-input">
            @foreach (['low' => 'Низкий', 'medium' => 'Средний', 'high' => 'Высокий'] as $v => $l)
                <option value="{{ $v }}" @selected(old('priority', $task->priority) === $v)>{{ $l }}</option>
            @endforeach
        </select>
        @error('priority') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="status">Статус</label>
        <select id="status" name="status" class="am-input">
            @foreach (['todo' => 'К выполнению', 'in_progress' => 'В работе', 'done' => 'Выполнено'] as $v => $l)
                <option value="{{ $v }}" @selected(old('status', $task->status) === $v)>{{ $l }}</option>
            @endforeach
        </select>
        @error('status') <p class="am-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="am-label" for="estimated_min">Оценка времени, мин</label>
        <input id="estimated_min" name="estimated_min" type="number" min="1" max="10000"
               value="{{ old('estimated_min', $task->estimated_min) }}" class="am-input">
        @error('estimated_min') <p class="am-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="am-btn">Сохранить</button>
    <a href="{{ route('tasks.index') }}" class="text-sm text-slate-400 hover:text-white">Отмена</a>
</div>
