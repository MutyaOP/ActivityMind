# ActivityMind — курстық жоба: «Талаптан прототипке дейін»

ActivityMind — жеке өнімділікті бақылау веб-платформасы: тапсырмалар, фокус-таймер, әдеттер,
компьютердегі белсенділікті автоматты есепке алу (Desktop Agent), аналитика және ереже/AI негізіндегі ұсыныстар.

Стек: Laravel 13 (PHP 8.3), Blade + Tailwind + Alpine.js + Chart.js, MySQL (тесттерде SQLite),
Python Desktop Agent (Windows, SQLite буфер), Python AI Analyzer (Flask, pandas, scikit-learn).

## Папка құрылымы

| Жол | Мазмұны |
|---|---|
| `report.docx` | Есеп (қазақ тілінде, Times New Roman 14) |
| `diagrams/*.drawio` | Диаграммалардың бастапқы файлдары (draw.io-да ашылады) |
| `diagrams/png/*.png` | Диаграммалардың суреттері |
| `diagrams/build_diagrams.py` | .drawio файлдарын қайта құрастыратын скрипт |
| `diagrams/export_png.ps1` | Барлық .drawio → PNG экспорты (draw.io Desktop арқылы) |
| `db/schema.sql` | 10 кесте, 3NF, шектеулер, индекстер, әр кестеде 5–11 жазба |
| `db/queries.sql` | 8 сұрау (JOIN, GROUP BY, терезе функциясы, идемпотентті INSERT) |
| `db/run_db.py`, `db/results.txt` | SQLite-та іске қосу скрипті және нәтижелер |
| `prototype.txt` | Figma сілтемесі және импорт нұсқаулығы |
| `prototype/screens/*.png` | 13 экран + дизайн-жүйе |
| `prototype/design-system.html` | Мини дизайн-жүйе (токендер, компоненттер) |
| `code/Recommendations/` | SOLID түзетуі: Strategy үлгісінің PHP қаңқасы + unit-тест |

## Диаграммалар

1. `01_use_case` — 4 актор, 16 прецедент, «include»/«extend», актор мұрагерлігі
2. `02_activity_focus_session` — тапсырма → таймер → статистика → ұсыныс (3 жолақ)
3. `03_activity_agent_sync` — Desktop Agent: офлайн буфер және синхрондау (3 жолақ)
4. `04_class` — 18 класс/интерфейс, мұрагерлік, композиция, агрегация, еселіктер
5. `05_sequence_agent_sync` — «қиын жер»: идемпотентті қабылдау, желі үзілгенде қайталау
6. `06_sequence_recommendations` — ұсыныстар генерациясы (opt AI, loop)
7. `07_er` — 10 сущность, 3NF, 1:N байланыстар
8. `08_c4_context`, `09_c4_containers` — C4 1 және 2 деңгейлері
9. `10_solid_strategy` — SOLID бұзылуы «дейін / кейін»

## Іске қосу

```powershell
# Деректер қоры (SQLite)
cd coursework\db
python run_db.py

# Диаграммаларды қайта құрастыру және экспорттау
cd ..\diagrams
python build_diagrams.py
.\export_png.ps1

# Экрандарды қайта түсіру (қосымша :8123 портында жұмыс істеп тұруы керек)
cd ..\prototype
python capture_screens.py
```

Негізгі жоба: `php artisan migrate --seed`, `npm run build`, `php artisan serve`; тесттер — `php artisan test`.
