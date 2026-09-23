import { cn } from '@/lib/utils';

export function SyntheticDataNotice({ className }: { className?: string }) {
    return (
        <p className={cn('text-center text-xs text-muted-foreground', className)}>
            Demonstration system · synthetic data only · no real customer data is used
        </p>
    );
}
