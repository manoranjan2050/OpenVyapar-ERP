/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
          300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
          600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a',
        },
      },
      fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
      keyframes: {
        fadeInUp:   { from: { opacity: '0', transform: 'translateY(24px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        fadeInDown: { from: { opacity: '0', transform: 'translateY(-24px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
        slideInLeft: { from: { opacity: '0', transform: 'translateX(-32px)' }, to: { opacity: '1', transform: 'translateX(0)' } },
        scaleIn:    { from: { opacity: '0', transform: 'scale(0.92)' }, to: { opacity: '1', transform: 'scale(1)' } },
        float:      { '0%,100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-16px)' } },
        gradientShift: { '0%,100%': { backgroundPosition: '0% 50%' }, '50%': { backgroundPosition: '100% 50%' } },
        blob:       { '0%,100%': { borderRadius: '60% 40% 30% 70% / 60% 30% 70% 40%' }, '50%': { borderRadius: '30% 60% 70% 40% / 50% 60% 30% 60%' } },
      },
      animation: {
        'fade-up':     'fadeInUp 0.4s ease-out both',
        'fade-down':   'fadeInDown 0.4s ease-out both',
        'slide-left':  'slideInLeft 0.4s ease-out both',
        'scale-in':    'scaleIn 0.35s ease-out both',
        'float':       'float 6s ease-in-out infinite',
        'blob':        'blob 8s ease-in-out infinite',
        'gradient':    'gradientShift 4s ease infinite',
      },
    },
  },
  plugins: [],
}
