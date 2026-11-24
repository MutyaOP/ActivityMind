import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Графитовая нейтральная шкала вместо голубоватого slate/gray
const GRAPHITE = {
    50: '#f7f7f8', 100: '#ededed', 200: '#dcdcdf', 300: '#c4c4c8', 400: '#a1a1a6',
    500: '#85858b', 600: '#5e5e63', 700: '#3d3d41', 800: '#2a2a2d', 900: '#1c1c1e',
};

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
                    50: '#fdf6ec', 100: '#fbe9cf', 200: '#f6d29e', 300: '#f0b866', 400: '#e9a23b',
                    500: '#d98a1f', 600: '#b86f14', 700: '#925612', 800: '#6f4112', 900: '#4d2d0d',
                },
                slate: GRAPHITE,
                gray: GRAPHITE, // пагинация Laravel использует gray
                surface: { 600: '#2c2c2f', 700: '#232325', 800: '#19191b', 900: '#141415', 950: '#0f0f10' },
            },
            boxShadow: {
                // Без свечений: только мягкий верхний блик и нейтральная тень для всплывающих окон
                glow: 'inset 0 1px 0 rgba(255,255,255,.10)',
                card: 'none',
                pop: '0 12px 32px rgba(0,0,0,.45)',
            },
            transitionTimingFunction: { DEFAULT: 'cubic-bezier(0.22, 1, 0.36, 1)' },
            transitionDuration: { DEFAULT: '150ms' },
        },
    },

    plugins: [forms],
};
