import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';
import eslintConfigPrettier from 'eslint-config-prettier';

export default tseslint.config(
  { ignores: ['dist', 'node_modules'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
      // Deuda heredada del proyecto (Fase 1 del plan de mejora): se
      // suaviza a "warn" hoy para no bloquear, y se sube a "error"
      // más adelante archivo por archivo.
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': 'warn',
      // Reglas nuevas de eslint-plugin-react-hooks orientadas al React
      // Compiler (vienen como "error" en el preset recommended). Detectan
      // patrones reales mezclados en los componentes grandes (ver 1.3.1
      // del diagnóstico), pero arreglarlos implica refactors de
      // comportamiento que exceden esta fase (solo tooling). Se dejan en
      // "warn" como deuda documentada; subir a "error" cuando se aborde
      // la Fase 3/4 (backend/frontend por capas) componente por componente.
      'react-hooks/set-state-in-effect': 'warn',
      'react-hooks/static-components': 'warn',
      'react-hooks/purity': 'warn',
      'react-hooks/immutability': 'warn',
    },
  },
  // Prettier va al final: desactiva reglas de ESLint que compitan con formato
  eslintConfigPrettier,
);
