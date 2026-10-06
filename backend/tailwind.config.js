import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * ASTRO GANN dark theme (owner brief 2026-10-06, DECISIONS D-61):
 * olive green (primary), light blue (info), yellow (warning/attention) on a dark background. NO RED anywhere.
 *
 * Palettes are "dark-semantic": low numbers (50–200) are dark tints for backgrounds/borders, high numbers
 * (600–900) are bright tones for text and buttons. That way the existing Breeze/Tailwind class names
 * (bg-*-50, text-*-800, bg-white → bg-surface …) render correctly on the dark background.
 */
const olive = {
    50: '#161c0d', 100: '#1e2612', 200: '#2f3b1a', 300: '#485a26', 400: '#6a8035',
    500: '#879e42', 600: '#9db552', 700: '#b3c86c', 800: '#cbd98f', 900: '#e2ebb8', 950: '#f1f6dc',
};
const lightBlue = {
    50: '#0c1a22', 100: '#10242f', 200: '#1a3a4c', 300: '#275674', 400: '#3f7fa6',
    500: '#5ea6d0', 600: '#7cc3ea', 700: '#9fd3f1', 800: '#c3e4f7', 900: '#e1f2fb', 950: '#f0f9fd',
};
const yellow = {
    50: '#221d08', 100: '#2e270b', 200: '#4a3f12', 300: '#6e5d18', 400: '#a08a22',
    500: '#cdb02c', 600: '#e6c638', 700: '#f0d65c', 800: '#f6e38d', 900: '#fbf0bf', 950: '#fdf8e0',
};
// Neutral scale inverted for the dark theme: gray-100 = page background, gray-900 = main text.
const gray = {
    50: '#0b0e0a', 100: '#0f130e', 200: '#252c22', 300: '#3a4535', 400: '#5d6957',
    500: '#8d9887', 600: '#aab3a5', 700: '#c6cdc2', 800: '#dfe4dc', 900: '#eff2ed', 950: '#f8faf7',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                surface: { DEFAULT: '#161c15', raised: '#1d251b' },
                olive,
                gray,
                indigo: olive,   // Breeze's accent colour → olive
                green: olive,
                blue: lightBlue,
                sky: lightBlue,
                yellow,
                amber: yellow,
                red: yellow,     // safety net: any leftover "red" class renders yellow
            },
        },
    },

    plugins: [forms],
};
