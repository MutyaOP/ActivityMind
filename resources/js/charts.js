import Chart from 'chart.js/auto';

// Цвета графиков берутся из CSS-переменных темы (resources/css/app.css), поэтому подходят и к тёмной, и к светлой теме
const css = getComputedStyle(document.documentElement);
const rgb = (name, alpha = 1) => `rgba(${css.getPropertyValue(`--am-${name}`).trim().split(/\s+/).join(', ')}, ${alpha})`;
const t = (key) => (window.AM_I18N && window.AM_I18N[key]) || key;

Chart.defaults.color = rgb('g-500');
Chart.defaults.borderColor = rgb('w', 0.06);
Chart.defaults.font.family = '"JetBrains Mono", ui-monospace, monospace';
Chart.defaults.font.size = 11;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.font = { family: '"IBM Plex Sans", ui-sans-serif, sans-serif', size: 13 };
Chart.defaults.plugins.tooltip.backgroundColor = rgb('s-700');
Chart.defaults.plugins.tooltip.titleColor = rgb('g-100');
Chart.defaults.plugins.tooltip.bodyColor = rgb('g-300');
Chart.defaults.plugins.tooltip.borderColor = rgb('w', 0.08);
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.titleFont = { family: '"IBM Plex Sans", sans-serif', weight: '500' };
Chart.defaults.animation.duration = 400;

const ACCENT = rgb('b-400');                  // brand-400
const BAR = rgb('g-100', 0.14);               // нейтральные столбцы
const LINE = rgb('g-400');                    // вторая серия — нейтральная линия
// Доли категорий: ведёт акцент, остальные — нейтральные ступени
const SHADES = [ACCENT, rgb('g-300'), rgb('g-500'), rgb('g-600'), '#a9824f', rgb('g-700'), rgb('g-200')];

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
                { type: 'bar', label: t('Фокус, ч'), data: focus, backgroundColor: highlight(focus), borderRadius: 4, yAxisID: 'y' },
                { type: 'line', label: t('Продуктивность'), data: scores, borderColor: LINE, backgroundColor: LINE, borderWidth: 1.5,
                  tension: 0.35, pointRadius: labels.length > 31 ? 0 : 3, yAxisID: 'y1' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: t('часы') } },
                y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: t('баллы') } },
                x: { ticks: { maxTicksLimit: 15 } },
            },
        },
    });
}

function doughnut(el, cfg) {
    new Chart(el, {
        type: 'doughnut',
        data: { labels: cfg.labels, datasets: [{ data: cfg.hours, backgroundColor: cfg.labels.map((_, i) => SHADES[i % SHADES.length]), borderColor: rgb('s-800'), borderWidth: 2, hoverOffset: 0 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed} ${t('ч')}` } },
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
            datasets: [{ label: t('Часы работы'), data: hours.cfg, backgroundColor: highlight(hours.cfg, 'max'), borderRadius: 3 }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: t('часы') } } },
        },
    });
}

const weekdays = readConfig('analyticsWeekdays');
if (weekdays) {
    new Chart(weekdays.el, {
        type: 'bar',
        data: { labels: weekdays.cfg.labels, datasets: [{ label: t('Среднее, ч'), data: weekdays.cfg.avg, backgroundColor: highlight(weekdays.cfg.avg, 'max'), borderRadius: 4 }] },
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
        data: { labels: apps.cfg.labels, datasets: [{ label: t('Часы'), data: apps.cfg.hours, backgroundColor: BAR, borderRadius: 3 }] },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, title: { display: true, text: t('часы') } } },
        },
    });
}
