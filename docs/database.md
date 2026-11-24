# ActivityMind — база данных

СУБД: MySQL (XAMPP). Время хранится в UTC, даты дней — в часовом поясе пользователя (`users.timezone`).

## ER-диаграмма

```mermaid
erDiagram
    users ||--o{ categories : "свои категории"
    users ||--o{ tasks : has
    users ||--o{ time_entries : has
    users ||--o{ habits : has
    users ||--o{ devices : has
    users ||--o{ activity_logs : has
    users ||--o{ daily_stats : has
    users ||--o{ recommendations : has
    categories ||--o{ tasks : classifies
    categories ||--o{ time_entries : classifies
    categories ||--o{ activity_logs : classifies
    tasks ||--o{ time_entries : "затраченное время"
    habits ||--o{ habit_logs : "отметки по дням"
    devices ||--o{ activity_logs : "данные агента"

    users {
        bigint id PK
        string name
        string email UK
        string password
        enum role "user | admin"
        string timezone
        smallint daily_focus_goal_min
    }
    categories {
        bigint id PK
        bigint user_id FK "null = системная"
        string name
        string color
        enum type "work | study | rest | other"
    }
    tasks {
        bigint id PK
        bigint user_id FK
        bigint category_id FK
        string title
        text description
        enum priority "low | medium | high"
        enum status "todo | in_progress | done"
        date due_date
        int estimated_min
        timestamp completed_at
    }
    time_entries {
        bigint id PK
        bigint user_id FK
        bigint task_id FK
        bigint category_id FK
        timestamp started_at
        timestamp ended_at "null пока идёт таймер"
        int duration_sec
        string note
    }
    habits {
        bigint id PK
        bigint user_id FK
        string title
        enum frequency "daily | weekly"
        tinyint target_per_period
        string color
        bool is_active
    }
    habit_logs {
        bigint id PK
        bigint habit_id FK
        date log_date "UK с habit_id"
        bool is_done
    }
    devices {
        bigint id PK
        bigint user_id FK
        string name
        string platform
        char token_hash UK "SHA-256 токена"
        timestamp last_sync_at
    }
    activity_logs {
        bigint id PK
        bigint user_id FK
        bigint device_id FK
        bigint category_id FK
        string app_name
        string window_title
        timestamp started_at
        int duration_sec
        bool is_idle
    }
    daily_stats {
        bigint id PK
        bigint user_id FK "UK с stat_date"
        date stat_date
        int focus_sec
        int tracked_sec
        smallint tasks_done
        smallint habits_done
        smallint habits_total
        tinyint productivity_score "0-100"
    }
    recommendations {
        bigint id PK
        bigint user_id FK
        enum type "focus | break | habit | planning | other"
        text text
        enum source "rule | ai"
        string rule_key
        bool is_read
        timestamp created_at
    }
```

## Правила целостности
- Все пользовательские данные удаляются каскадом при удалении пользователя (`ON DELETE CASCADE` по `user_id`).
- Удаление категории или задачи не удаляет записи времени: `category_id` / `task_id` становятся `NULL` (`ON DELETE SET NULL`).
- `habit_logs`: уникальная пара (`habit_id`, `log_date`) — одна отметка на привычку в день.
- `daily_stats`: уникальная пара (`user_id`, `stat_date`) — пересчёт идемпотентен (`upsert`).
- `devices.token_hash`: уникален; открытый токен нигде не хранится. `activity_logs`: уникальная тройка (`device_id`, `started_at`, `app_name`) — повторная отправка пачки не создаёт дублей.
- Индексы: `tasks (user_id, status)`, `tasks (user_id, due_date)`, `time_entries (user_id, started_at)`, `activity_logs (user_id, started_at)`, `recommendations (user_id, rule_key, created_at)`.

## Назначение таблиц
| Таблица | Кто пишет | Кто читает |
|---|---|---|
| `tasks` | пользователь (страница «Задачи») | дашборд, задачи, рекомендации, статистика |
| `time_entries` | таймер / ручная запись | дашборд, аналитика, `daily_stats` |
| `habits`, `habit_logs` | пользователь (клик по кружку дня) | привычки, дашборд, статистика, рекомендации |
| `daily_stats` | `StatsService::sync()` (страница аналитики, команда `recalc-stats`, рекомендации) | аналитика, AI-модуль |
| `recommendations` | `RecommendationService` (правила), AI-модуль | страница рекомендаций, дашборд |
| `devices`, `activity_logs` | Desktop Agent через Agent API | аналитика («Топ приложений»), `daily_stats.tracked_sec` |
