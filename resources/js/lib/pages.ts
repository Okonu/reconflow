import type { ComponentType } from 'react';

type PageModule = { default: ComponentType };

const sharedPages = import.meta.glob<PageModule>('../pages/**/*.tsx');
const modulePages = import.meta.glob<PageModule>('../../../Modules/*/resources/js/Pages/**/*.tsx');

export function pagePath(name: string): string {
    const [module, ...rest] = name.split('/');
    const moduleKey = `../../../Modules/${module}/resources/js/Pages/${rest.join('/')}.tsx`;
    return moduleKey in modulePages ? moduleKey : `../pages/${name}.tsx`;
}

export async function resolvePage(name: string): Promise<ComponentType> {
    const key = pagePath(name);
    const loader = modulePages[key] ?? sharedPages[key];
    if (!loader) {
        throw new Error(`Inertia page not found: ${name}`);
    }
    return (await loader()).default;
}
