-- =====================================================================
--  ActivityMind — деректер қорының схемасы (SQLite 3)
--  ЕГЖ «Талаптан прототипке дейін», 2-кезең: нақты деректер қоры
--  Іске қосу:  python run_db.py   (немесе: sqlite3 activitymind.db < schema.sql)
--  Схема 3NF-ке келтірілген: әр кестеде бір ғана мән (entity),
--  транзитивті тәуелділіктер жоқ, анықтамалықтар бөлек кестеде.
-- =====================================================================

PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS recommendations;
DROP TABLE IF EXISTS daily_stats;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS habit_logs;
DROP TABLE IF EXISTS habits;
DROP TABLE IF EXISTS time_entries;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- 1. Пайдаланушылар (рөлі: user | admin)
CREATE TABLE users (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    name                  VARCHAR(255) NOT NULL,
    email                 VARCHAR(255) NOT NULL UNIQUE,
    password              VARCHAR(255) NOT NULL,            -- bcrypt хэш
    role                  VARCHAR(10)  NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    timezone              VARCHAR(64)  NOT NULL DEFAULT 'Asia/Almaty',
    daily_focus_goal_min  INTEGER CHECK (daily_focus_goal_min BETWEEN 1 AND 1440),
    created_at            TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Санаттар (user_id = NULL — жүйелік санат, барлығына ортақ)
CREATE TABLE categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    color       CHAR(7)      NOT NULL DEFAULT '#8b5cf6',
    type        VARCHAR(10)  NOT NULL DEFAULT 'other' CHECK (type IN ('work', 'study', 'rest', 'other'))
);

-- 3. Тапсырмалар
CREATE TABLE tasks (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id    INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    title          VARCHAR(255) NOT NULL,
    description    TEXT,
    priority       VARCHAR(10) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
    status         VARCHAR(12) NOT NULL DEFAULT 'todo'   CHECK (status IN ('todo', 'in_progress', 'done')),
    due_date       TEXT,
    estimated_min  INTEGER CHECK (estimated_min > 0),
    completed_at   TEXT
);
CREATE INDEX idx_tasks_user_status ON tasks(user_id, status);
CREATE INDEX idx_tasks_user_due    ON tasks(user_id, due_date);

-- 4. Уақыт жазбалары (таймер немесе қолмен енгізу); ended_at = NULL — таймер жүріп тұр
CREATE TABLE time_entries (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    task_id       INTEGER REFERENCES tasks(id) ON DELETE SET NULL,
    category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    started_at    TEXT NOT NULL,
    ended_at      TEXT,
    duration_sec  INTEGER CHECK (duration_sec >= 0),
    note          VARCHAR(255),
    CHECK (ended_at IS NULL OR ended_at > started_at)
);
CREATE INDEX idx_time_user_start ON time_entries(user_id, started_at);

-- 5. Әдеттер
CREATE TABLE habits (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id            INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title              VARCHAR(255) NOT NULL,
    frequency          VARCHAR(10) NOT NULL DEFAULT 'daily' CHECK (frequency IN ('daily', 'weekly')),
    target_per_period  INTEGER NOT NULL DEFAULT 1 CHECK (target_per_period BETWEEN 1 AND 7),
    is_active          INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1))
);

-- 6. Әдет белгілері: бір әдетке бір күнге — бір белгі
CREATE TABLE habit_logs (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    habit_id  INTEGER NOT NULL REFERENCES habits(id) ON DELETE CASCADE,
    log_date  TEXT NOT NULL,
    is_done   INTEGER NOT NULL DEFAULT 1 CHECK (is_done IN (0, 1)),
    UNIQUE (habit_id, log_date)
);

-- 7. Құрылғылар (Desktop Agent). Ашық токен сақталмайды — тек SHA-256 хэші
CREATE TABLE devices (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name          VARCHAR(255) NOT NULL,
    platform      VARCHAR(50)  NOT NULL DEFAULT 'windows',
    token_hash    CHAR(64) UNIQUE,
    last_sync_at  TEXT
);

-- 8. Белсенділік журналы (агенттен келген сессиялар).
--    «Қиын жер»: UNIQUE(device_id, started_at, app_name) — қайта жіберілген пакет дубль жасамайды
CREATE TABLE activity_logs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id     INTEGER NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    app_name      VARCHAR(255) NOT NULL,
    window_title  VARCHAR(255),
    started_at    TEXT NOT NULL,
    duration_sec  INTEGER NOT NULL CHECK (duration_sec BETWEEN 1 AND 86400),
    is_idle       INTEGER NOT NULL DEFAULT 0 CHECK (is_idle IN (0, 1)),
    CONSTRAINT activity_logs_device_start_app_unique UNIQUE (device_id, started_at, app_name)
);
CREATE INDEX idx_activity_device_start ON activity_logs(device_id, started_at);

-- 9. Күндік статистика (кэш): бір пайдаланушыға бір күнге — бір жол (upsert)
CREATE TABLE daily_stats (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id             INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    stat_date           TEXT NOT NULL,
    focus_sec           INTEGER NOT NULL DEFAULT 0,
    tracked_sec         INTEGER NOT NULL DEFAULT 0,
    tasks_done          INTEGER NOT NULL DEFAULT 0,
    habits_done         INTEGER NOT NULL DEFAULT 0,
    habits_total        INTEGER NOT NULL DEFAULT 0,
    productivity_score  INTEGER NOT NULL DEFAULT 0 CHECK (productivity_score BETWEEN 0 AND 100),
    UNIQUE (user_id, stat_date)
);

-- 10. Ұсыныстар (ережелер бойынша немесе AI-модуль)
CREATE TABLE recommendations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type        VARCHAR(10) NOT NULL DEFAULT 'other' CHECK (type IN ('focus', 'break', 'habit', 'planning', 'other')),
    text        TEXT NOT NULL,
    source      VARCHAR(5)  NOT NULL DEFAULT 'rule' CHECK (source IN ('rule', 'ai')),
    rule_key    VARCHAR(80),
    is_read     INTEGER NOT NULL DEFAULT 0 CHECK (is_read IN (0, 1)),
    created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_rec_user_rule ON recommendations(user_id, rule_key, created_at);

-- =====================================================================
--  Тест деректері (әр кестеге 5–10 жазба)
-- =====================================================================

INSERT INTO users (id, name, email, password, role, timezone, daily_focus_goal_min, created_at) VALUES
 (1, 'Әкімші',          'admin@activitymind.local', '$2y$12$adminhashadminhashadminhashadminhashadminhashadminh', 'admin', 'Asia/Almaty', NULL, '2026-11-01 09:00:00'),
 (2, 'Айгерім Сәрсен',  'aigerim@demo.test',        '$2y$12$demohash1demohash1demohash1demohash1demohash1demoha', 'user',  'Asia/Almaty', 240,  '2026-11-02 10:15:00'),
 (3, 'Данияр Ахметов',  'daniyar@demo.test',        '$2y$12$demohash2demohash2demohash2demohash2demohash2demoha', 'user',  'Asia/Almaty', 300,  '2026-11-03 18:40:00'),
 (4, 'Мадина Ерланқызы','madina@demo.test',         '$2y$12$demohash3demohash3demohash3demohash3demohash3demoha', 'user',  'Asia/Aqtobe', 180,  '2026-11-05 08:05:00'),
 (5, 'Тимур Қасымов',   'timur@demo.test',          '$2y$12$demohash4demohash4demohash4demohash4demohash4demoha', 'user',  'Asia/Almaty', 120,  '2026-11-07 21:30:00'),
 (6, 'Әлия Нұрланова',  'aliya@demo.test',          '$2y$12$demohash5demohash5demohash5demohash5demohash5demoha', 'user',  'Asia/Almaty', 200,  '2026-11-10 12:00:00');

INSERT INTO categories (id, user_id, name, color, type) VALUES
 (1, NULL, 'Оқу',           '#38bdf8', 'study'),
 (2, NULL, 'Бағдарламалау', '#8b5cf6', 'work'),
 (3, NULL, 'Құжаттар',      '#2dd4bf', 'work'),
 (4, NULL, 'Байланыс',      '#f59e0b', 'other'),
 (5, NULL, 'Демалыс',       '#f43f5e', 'rest'),
 (6, NULL, 'Басқа',         '#64748b', 'other'),
 (7, 2,    'Дипломдық жоба','#a78bfa', 'study');

INSERT INTO tasks (id, user_id, category_id, title, priority, status, due_date, estimated_min, completed_at) VALUES
 (1, 2, 7, 'Дипломның 2-тарауын жазу',      'high',   'in_progress', '2026-11-28', 240, NULL),
 (2, 2, 2, 'UML-диаграммаларды салу',        'high',   'todo',        '2026-11-23', 120, NULL),
 (3, 2, 1, 'Oracle бойынша дәрісті оқу',     'medium', 'todo',        '2026-11-22',  60, NULL),
 (4, 2, 3, 'README рәсімдеу',                'low',    'done',        '2026-11-20',  30, '2026-11-20 16:10:00'),
 (5, 3, 2, 'API-ды тестілеу',                'high',   'done',        '2026-11-21',  90, '2026-11-21 19:00:00'),
 (6, 3, 1, 'Ағылшын тілі: 20 сөз',          'low',    'todo',        NULL,          20, NULL),
 (7, 4, 3, 'Есепті тексеру',                 'medium', 'in_progress', '2026-11-26',  45, NULL),
 (8, 5, 2, 'Git-тегі тапсырмаларды бөлу',    'medium', 'done',        '2026-11-19',  30, '2026-11-19 11:30:00'),
 (9, 6, 1, 'Курстық жұмысқа әдебиет жинау',  'high',   'todo',        '2026-11-30', 120, NULL);

INSERT INTO time_entries (id, user_id, task_id, category_id, started_at, ended_at, duration_sec, note) VALUES
 (1,  2, 1, 7, '2026-11-23 04:00:00', '2026-11-23 05:30:00', 5400, 'Кіріспе'),
 (2,  2, 2, 2, '2026-11-23 06:00:00', '2026-11-23 06:50:00', 3000, NULL),
 (3,  2, 3, 1, '2026-11-23 09:00:00', '2026-11-23 09:45:00', 2700, NULL),
 (4,  2, 1, 7, '2026-11-24 03:30:00', '2026-11-24 06:00:00', 9000, '1.2 бөлім'),
 (5,  2, 4, 3, '2026-11-20 10:00:00', '2026-11-20 10:30:00', 1800, NULL),
 (6,  3, 5, 2, '2026-11-21 13:00:00', '2026-11-21 15:00:00', 7200, NULL),
 (7,  3, NULL, 1, '2026-11-22 14:00:00', '2026-11-22 14:20:00', 1200, 'Сөздер'),
 (8,  4, 7, 3, '2026-11-24 05:00:00', '2026-11-24 05:40:00', 2400, NULL),
 (9,  5, 8, 2, '2026-11-19 05:00:00', '2026-11-19 05:30:00', 1800, NULL),
 (10, 6, 9, 1, '2026-11-24 08:00:00', NULL, NULL, 'Таймер жүріп тұр');

INSERT INTO habits (id, user_id, title, frequency, target_per_period, is_active) VALUES
 (1, 2, 'Таңғы жаттығу',          'daily',  1, 1),
 (2, 2, '20 минут кітап оқу',      'daily',  1, 1),
 (3, 2, 'Ағылшын тілі',            'weekly', 3, 1),
 (4, 3, '23:00-ге дейін ұйықтау',  'daily',  1, 1),
 (5, 4, 'Су ішу (2 л)',            'daily',  1, 1),
 (6, 5, 'Жүгіру',                  'weekly', 2, 0);

INSERT INTO habit_logs (habit_id, log_date, is_done) VALUES
 (1, '2026-11-20', 1), (1, '2026-11-21', 1), (1, '2026-11-22', 1), (1, '2026-11-23', 1), (1, '2026-11-24', 1),
 (2, '2026-11-22', 1), (2, '2026-11-24', 1),
 (3, '2026-11-18', 1), (3, '2026-11-21', 1),
 (4, '2026-11-23', 1),
 (5, '2026-11-24', 1);

INSERT INTO devices (id, user_id, name, platform, token_hash, last_sync_at) VALUES
 (1, 2, 'Ноутбук',            'windows', 'a3f1c9d2e4b5a6978877665544332211aabbccddeeff00112233445566778899', '2026-11-24 06:01:00'),
 (2, 2, 'Үйдегі компьютер',   'windows', 'b4e2d0c3f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2', '2026-11-22 18:00:00'),
 (3, 3, 'Зертхана ПК',        'windows', 'c5f3e1d4a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3', '2026-11-21 15:05:00'),
 (4, 4, 'MacBook',            'macos',   'd6a4f2e5b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4', NULL),
 (5, 6, 'Ноутбук Lenovo',     'windows', 'e7b5a3f6c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5', '2026-11-24 08:30:00');

INSERT INTO activity_logs (device_id, category_id, app_name, window_title, started_at, duration_sec, is_idle) VALUES
 (1, 2,    'Code',      NULL, '2026-11-24 03:30:00', 3600, 0),
 (1, 1,    'chrome',    NULL, '2026-11-24 04:30:00', 1500, 0),
 (1, 3,    'WINWORD',   NULL, '2026-11-24 04:55:00', 2700, 0),
 (1, 4,    'Telegram',  NULL, '2026-11-24 05:40:00',  600, 0),
 (1, NULL, 'idle',      NULL, '2026-11-24 05:50:00',  900, 1),
 (2, 2,    'Code',      NULL, '2026-11-22 15:00:00', 5400, 0),
 (2, 5,    'Spotify',   NULL, '2026-11-22 16:30:00', 1200, 0),
 (3, 2,    'pycharm64', NULL, '2026-11-21 13:00:00', 7000, 0),
 (5, 1,    'chrome',    NULL, '2026-11-24 08:00:00', 1800, 0),
 (5, 3,    'AcroRd32',  NULL, '2026-11-24 08:30:00',  900, 0);

INSERT INTO daily_stats (user_id, stat_date, focus_sec, tracked_sec, tasks_done, habits_done, habits_total, productivity_score) VALUES
 (2, '2026-11-20', 1800,  2400, 1, 1, 3, 38),
 (2, '2026-11-21', 0,     0,    0, 2, 3, 22),
 (2, '2026-11-22', 0,     6600, 0, 2, 3, 30),
 (2, '2026-11-23', 11100, 0,    0, 1, 3, 61),
 (2, '2026-11-24', 9000,  9300, 0, 2, 3, 70),
 (3, '2026-11-21', 7200,  7000, 1, 0, 1, 64),
 (3, '2026-11-22', 1200,  0,    0, 0, 1, 12),
 (4, '2026-11-24', 2400,  0,    0, 1, 1, 48);

INSERT INTO recommendations (user_id, type, text, source, rule_key, is_read, created_at) VALUES
 (2, 'focus',    'Белсенділігіңіздің шыңы — 09:00–11:00. Күрделі тапсырмаларды осы уақытқа жоспарлаңыз.', 'rule', 'best_hour',        0, '2026-11-24 06:05:00'),
 (2, 'planning', 'Мерзімі өткен 2 тапсырма бар: оларды бөліп, 30–60 минуттық қадамдарға жіктеңіз.',      'rule', 'overdue_tasks',    0, '2026-11-24 06:05:00'),
 (2, 'break',    '90 минут үздіксіз жұмыстан кейін 10–15 минут үзіліс жасаңыз.',                         'rule', 'long_sessions',    1, '2026-11-23 07:00:00'),
 (2, 'habit',    'Әдеттерді орындаған күндері фокус орта есеппен 38% жоғары.',                            'ai',   'ai_habits_boost',  0, '2026-11-24 06:06:00'),
 (3, 'focus',    'Сейсенбі — ең әлсіз күніңіз: фокус орташадан 45% төмен.',                              'ai',   'ai_weekday_weak_2',0, '2026-11-22 20:00:00'),
 (4, 'other',    'Desktop Agent қосылмаған: құрылғыны қосып, қолданбалар статистикасын алыңыз.',          'rule', 'no_tracking',      1, '2026-11-24 07:00:00');
