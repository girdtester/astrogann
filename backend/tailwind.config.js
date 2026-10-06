import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * ASTRO GANN light theme (owner request 2026-10-06, DECISIONS D-69 — supersedes the dark theme of D-61):
 * white / very light background for readability, olive green (primary), light blue (info/links) and
 * yellow (warning/attention) as accents. NO RED anywhere.
 *
 * Normal Tailwind semantics: low numbers (50–200) = light tints for backgrounds/borders, high numbers
 * (600–900) = strong tones for text and buttons.
 */
const olive = {
    50: '#f6f8ee', 100: '#ebf0d8', 200: '#d6e0b0', 300: '#bccb80', 400: '#9fb156',
    500: '#83963a', 600: '#6b7c2e', 700: '#556227', 800: '#454f23', 900: '#3a4321', 950: '#1e240f',
};
const lightBlue = {
    50: '#f0f8fd', 100: '#e0f0fa', 200: '#bae1f5', 300: '#86cbee', 400: '#4fb1e3',
    500: '#2996d0', 600: '#1b78b0', 700: '#18608f', 800: '#195176', 900: '#1a4462', 950: '#112c41',
};
const yellow = {
    50: '#fefbe8', 100: '#fef6c3', 200: '#feea8a', 300: '#fdd847', 400: '#facc15',
    500: '#eab308', 600: '#ca8a04', 700: '#a16207', 800: '#854d0e', 900: '#713f12', 950: '#422006',
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
                surface: { DEFAULT: '#ffffff', raised: '#ffffff' },
                olive,
                indigo: olive,   // Breeze's accent colour → olive
                green: olive,
                blue: lightBlue,
                sky: lightBlue,
                yellow,
                amber: yellow,
                red: yellow,     // safety net: any leftover "red" class renders yellow
                rose: yellow,
                pink: yellow,
                orange: yellow,
            },
        },
    },

    plugins: [forms],
};
