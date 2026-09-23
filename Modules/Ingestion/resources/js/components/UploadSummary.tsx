import { Card, CardContent } from '@/components/ui/card';
import type { StagedUpload } from '../types';

function Stat({ label, value, tone }: { label: string; value: number; tone?: 'danger' }) {
    return (
        <Card>
            <CardContent className="pt-6">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className={tone === 'danger' && value > 0 ? 'text-2xl font-semibold text-destructive' : 'text-2xl font-semibold'}>{value.toLocaleString()}</p>
            </CardContent>
        </Card>
    );
}

export function UploadSummary({ upload, appendConflicts }: { upload: StagedUpload; appendConflicts: number }) {
    return (
        <div className="space-y-3">
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="Rows read" value={upload.rows_read} />
                <Stat label="Valid rows" value={upload.rows_valid} />
                <Stat label="Invalid rows (will be quarantined)" value={upload.rows_invalid} tone="danger" />
            </div>
            {upload.duplicate_of_batch_id !== null && (
                <div className="rounded-md border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
                    This exact file was already imported for this date (batch #{upload.duplicate_of_batch_id}). Importing it again is allowed but usually unnecessary.
                </div>
            )}
            {upload.date_has_data && appendConflicts > 0 && (
                <div className="rounded-md border border-warning/40 bg-warning/10 px-4 py-3 text-sm">
                    {appendConflicts.toLocaleString()} valid rows already exist for this date. If you choose Append they will not be imported; choose Replace to load this
                    file instead of the existing data.
                </div>
            )}
        </div>
    );
}
