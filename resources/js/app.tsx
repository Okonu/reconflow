import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePage } from '@/lib/pages';

createInertiaApp({
    title: (title) => (title ? `${title} · ReconFlow` : 'ReconFlow'),
    resolve: resolvePage,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: 'oklch(0.48 0.11 195)' },
});
