import Chart from 'chart.js/auto';

// Графитовая тема: приглушённая сетка, моноширинные подписи, один акцент (янтарь) на график
Chart.defaults.color = '#85858b';
Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.06)';
Chart.defaults.font.family = '"JetBrains Mono", ui-monospace, monospace';
Chart.defaults.font.size = 11;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.font = { family: '"IBM Plex Sans", ui-sans-serif, sans-serif', size: 13 };
Chart.defaults.plugins.tooltip.backgroundColor = '#232325';
Chart.defaults.plugins.tooltip.borderColor = 'rgba(255, 255, 255, 0.08)';
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.titleFont = { family: '"IBM Plex Sans", sans-serif', weight: '500' };
Chart.defaults.animation.duration = 400;

const ACCENT = '#e9a23b';                     // brand-400
const BAR = 'rgba(237, 237, 237, 0.14)';      // нейтральные столбцы
const LINE = '#a1a1a6';                       // вторая серия — нейтральная линия
// Доли категорий: ведёт акцент, остальные — нейтральные ступени
const SHADES = ['#e9a23b', '#c4c4c8', '#85858b', '#5e5e63', '#a9824f', '#3d3d41', '#dcdcdf'];

/** Акцент только у одного столбца: последнего (сегодня) или максимального. */
const highlight = (data, mode = 'last') => {
    const idx = mode === 'max' ? data.indexOf(Math.max(...data)) : data.length - 1;
    return data.map((_, i) => (i === idx ? ACCENT : BAR));
};

function readConfig(id) {
    const el = document.getElementById(id);
    return el ? { el, cfg: JSON.parse(el.dataset.config) } : null;
}

/** Столбцы «фокус, ч» + линия «продуктивность» — общий вид для дашборда и аналитики. */
function focusAndScore(el, labels, focus, scores) {
    new Chart(el, {
        data: {
            labels,
            datasets: [
                { type: 'bar', label: 'Фокус, ч', data: focus, backgroundColor: highlight(focus), borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: 'Продуктивность', data: scores, borderColor: LINE, backgroundColor: LINE, borderWidth: 1.5,
                  tension: 0.35, pointRadius: labels.length > 31 ? 0 : 3, yAxisID: 'y1' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'часы' } },
                y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: 'баллы' } },
                x: { ticks: { maxTicksLimit: 15 } },
            },
        },
    });
}

function doughnut(el, cfg) {
    new Chart(el, {
        type: 'doughnut',
        data: { labels: cfg.labels, datasets: [{ data: cfg.hours, backgroundColor: cfg.labels.map((_, i) => SHADES[i % SHADES.length]), borderColor: '#19191b', borderWidth: 2, hoverOffset: 0 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed} ч` } },
            },
        },
    });
}

// ---------- Дашборд ----------
const week = readConfig('weekChart');
if (week) focusAndScore(week.el, week.cfg.labels, week.cfg.focus, week.cfg.scores);

const cats = readConfig('categoryChart');
if (cats) doughnut(cats.el, cats.cfg);

// ---------- Аналитика ----------
const daily = readConfig('analyticsDaily');
if (daily) focusAndScore(daily.el, daily.cfg.labels, daily.cfg.focus, daily.cfg.scores);

const analyticsCats = readConfig('analyticsCategories');
if (analyticsCats) doughnut(analyticsCats.el, analyticsCats.cfg);

const hours = readConfig('analyticsHours');
if (hours) {
    new Chart(hours.el, {
        type: 'bar',
        data: {
            labels: Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0')),
            datasets: [{ label: 'Часы работы', data: hours.cfg, backgroundColor: highlight(hours.cfg, 'max'), borderRadius: 3 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'часы' } } },
        },
    });
}

const weekdays = readConfig('analyticsWeekdays');
if (weekdays) {
    new Chart(weekdays.el, {
        type: 'bar',
        data: { labels: weekdays.cfg.labels, datasets: [{ label: 'Среднее, ч', data: weekdays.cfg.avg, backgroundColor: highlight(weekdays.cfg.avg, 'max'), borderRadius: 4 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

const apps = readConfig('analyticsApps');
if (apps) {
    new Chart(apps.el, {
        type: 'bar',
        data: { labels: apps.cfg.labels, datasets: [{ label: 'Часы', data: apps.cfg.hours, backgroundColor: BAR, borderRadius: 3 }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, title: { display: true, text: 'часы' } } },
        },
    });
}
