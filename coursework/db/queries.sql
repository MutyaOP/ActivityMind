-- =====================================================================
--  ActivityMind — жүйенің негізгі SQL сұраулары (SQLite 3)
--  Әр сұраудың алдында: қай функционалдық талапқа (FR) қызмет ететіні.
-- =====================================================================

-- Q1 (FR-03, FR-04). Пайдаланушының белсенді тапсырмалары:
--     алдымен мерзімі өткендер, содан кейін басымдық, содан кейін мерзім.
SELECT t.id,
       t.title,
       t.priority,
       t.status,
       t.due_date,
       COALESCE(c.name, '—')                            AS category,
       CASE WHEN t.due_date < '2026-11-24' THEN 'мерзімі өткен' ELSE '' END AS overdue
FROM tasks t
LEFT JOIN categories c ON c.id = t.category_id
WHERE t.user_id = 2
  AND t.status <> 'done'
ORDER BY (t.due_date < '2026-11-24') DESC,
         CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
         t.due_date;

-- Q2 (FR-08, FR-09). Соңғы 7 күндегі уақыттың санаттар бойынша бөлінуі (сағат) — дашбордтағы дөңгелек диаграмма.
SELECT COALESCE(c.name, 'Санатсыз')               AS category,
       ROUND(SUM(te.duration_sec) / 3600.0, 2)    AS hours,
       COUNT(*)                                   AS entries
FROM time_entries te
LEFT JOIN categories c ON c.id = te.category_id
WHERE te.user_id = 2
  AND te.ended_at IS NOT NULL
  AND te.started_at >= '2026-11-18'
GROUP BY c.name
ORDER BY hours DESC;

-- Q3 (FR-06). Әдеттердің соңғы 7 күндегі орындалу пайызы.
SELECT h.title,
       h.frequency,
       COUNT(hl.id)                                      AS done_days,
       ROUND(COUNT(hl.id) * 100.0 /
             CASE h.frequency WHEN 'daily' THEN 7 ELSE h.target_per_period END, 0) AS rate_percent
FROM habits h
LEFT JOIN habit_logs hl ON hl.habit_id = h.id
                       AND hl.is_done = 1
                       AND hl.log_date BETWEEN '2026-11-18' AND '2026-11-24'
WHERE h.user_id = 2 AND h.is_active = 1
GROUP BY h.id
ORDER BY rate_percent DESC;

-- Q4 (FR-11). Desktop Agent деректері: «Топ қолданбалар» (бос тұру сессияларынсыз).
SELECT a.app_name,
       COALESCE(c.name, '—')                       AS category,
       ROUND(SUM(a.duration_sec) / 3600.0, 2)      AS hours
FROM activity_logs a
JOIN devices d         ON d.id = a.device_id
LEFT JOIN categories c ON c.id = a.category_id
WHERE d.user_id = 2
  AND a.is_idle = 0
GROUP BY a.app_name, c.name
ORDER BY hours DESC
LIMIT 5;

-- Q5 (FR-12, «қиын жер»). Идемпотентті қабылдау: сол пакетті қайта жіберу дубль жасамайды.
--     INSERT OR IGNORE + UNIQUE(device_id, started_at, app_name). Нәтиже — 1 жаңа жол, 1 дубль еленбейді.
INSERT OR IGNORE INTO activity_logs (device_id, category_id, app_name, started_at, duration_sec, is_idle) VALUES
 (1, 2, 'Code',  '2026-11-24 03:30:00', 3600, 0),   -- бұрын келген (дубль)
 (1, 1, 'chrome','2026-11-24 06:05:00',  300, 0);   -- жаңа
SELECT changes() AS accepted_rows;
SELECT COUNT(*) AS logs_of_device_1 FROM activity_logs WHERE device_id = 1;

-- Q6 (FR-09). Апталық талдау: күндік статистика + жылжымалы орташа өнімділік.
SELECT stat_date,
       ROUND(focus_sec / 3600.0, 1)      AS focus_hours,
       productivity_score,
       ROUND(AVG(productivity_score) OVER (ORDER BY stat_date ROWS BETWEEN 2 PRECEDING AND CURRENT ROW), 1) AS score_avg_3d
FROM daily_stats
WHERE user_id = 2
ORDER BY stat_date;

-- Q7 (FR-10). Оқылмаған ұсыныстар саны (мәзірдегі белгі) және дереккөз бойынша бөлу.
SELECT source,
       COUNT(*)                         AS total,
       SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread
FROM recommendations
WHERE user_id = 2
GROUP BY source;

-- Q8 (FR-13, әкімші). Пайдаланушылар бойынша жалпы көрсеткіштер.
SELECT u.name,
       u.role,
       (SELECT COUNT(*) FROM tasks t   WHERE t.user_id = u.id)                          AS tasks,
       (SELECT COUNT(*) FROM devices d WHERE d.user_id = u.id)                          AS devices,
       (SELECT ROUND(COALESCE(SUM(duration_sec), 0) / 3600.0, 1)
          FROM time_entries te WHERE te.user_id = u.id)                                 AS focus_hours
FROM users u
ORDER BY focus_hours DESC;
