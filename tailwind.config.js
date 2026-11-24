import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Цвета берутся из CSS-переменных (resources/css/app.css): тёмная и светлая тема меняют только значения переменных.
const v = (name) => `rgb(var(--am-${name}) / <alpha-value>)`;
const scale = (prefix, steps) => Object.fromEntries(steps.map((s) => [s, v(`${prefix}-${s}`)]));

// Графитовая нейтральная шкала вместо голубоватого slate/gray; в светлой теме она перевёрнута
const GRAPHITE = scale('g', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Акцент — янтарь «настольной лампы». Используется редко: главное действие, выбор, идущий таймер.
                brand: {
                    ...scale('b', [50, 100, 200, 300, 400]),
                    500: '#d98a1f', 600: '#b86f14', 700: '#925612', 800: '#6f4112', 900: '#4d2d0d',
                },
                slate: GRAPHITE,
                gray: GRAPHITE, // пагинация Laravel использует gray
                surface: scale('s', [600, 700, 800, 900, 950]),
                // «white» — контрастный к фону цвет: белый в тёмной теме, чёрный в светлой (тонкие линии, блики, заголовки)
                white: v('w'),
            },
            boxShadow: {
                // Без свечений: только мягкий верхний блик и нейтральная тень для всплывающих окон
                glow: 'inset 0 1px 0 var(--am-glow)',
                card: 'none',
                pop: '0 12px 32px var(--am-pop)',
            },
            transitionTimingFunction: { DEFAULT: 'cubic-bezier(0.22, 1, 0.36, 1)' },
            transitionDuration: { DEFAULT: '150ms' },
        },
    },

    plugins: [forms],
};
