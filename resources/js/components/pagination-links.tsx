import { Button } from '@/components/ui/button';

export function PaginationLinks({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (page: number) => void }) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-2 pt-4 text-sm">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
                Previous
            </Button>
            <span className="text-muted-foreground">
                Page {page} of {lastPage}
            </span>
            <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>
                Next
            </Button>
        </div>
    );
}
