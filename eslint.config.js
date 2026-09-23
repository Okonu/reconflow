import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'storage'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['**/*.{ts,tsx}'],
        languageOptions: { globals: globals.browser },
        plugins: { 'react-hooks': reactHooks },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'no-restricted-globals': ['error', { name: 'fetch', message: 'Use resources/js/lib/http.ts' }],
            'no-warning-comments': 'off',
        },
    },
    {
        files: ['resources/js/lib/http.ts'],
        rules: { 'no-restricted-globals': 'off' },
    },
);
