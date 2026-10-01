import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ComponentType } from 'react';
import '../css/app.css';
const pages = import.meta.glob<{ default: ComponentType }>('./Pages/*.tsx');
createInertiaApp({
    title: (title) => `${title} · 2 COME HOME Akquise`,
    resolve: async (name) => {
        const page = await pages[`./Pages/${name}.tsx`]();
        return page.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#b8d299' },
});
