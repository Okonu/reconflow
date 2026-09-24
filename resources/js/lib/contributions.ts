import type { ComponentType } from 'react';

export interface ContributionProps<T = unknown> {
    data: T;
    exceptionId: number;
}

interface ContributionModule {
    key: string;
    order?: number;
    default: ComponentType<ContributionProps<never>>;
}

const modules = import.meta.glob<ContributionModule>('../../../Modules/*/resources/js/contributions/*.tsx', { eager: true });

export function contributionPanels(contributions: Record<string, unknown>) {
    return Object.values(modules)
        .filter((module) => module.key in contributions)
        .sort((a, b) => (a.order ?? 50) - (b.order ?? 50))
        .map((module) => ({ key: module.key, Component: module.default as ComponentType<ContributionProps<unknown>>, data: contributions[module.key] }));
}
