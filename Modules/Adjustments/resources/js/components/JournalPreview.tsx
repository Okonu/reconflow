import { formatMoney } from '@/lib/format';
import type { AdjustmentRow } from '../types';

export function JournalPreview({ journal }: { journal: AdjustmentRow['journal'] }) {
    if (!journal) {
        return null;
    }

    return (
        <div className="rounded-md bg-muted/50 p-3 text-xs">
            {journal.reverse_journal_id && <p className="mb-1">Reverses ERP journal {journal.reverse_journal_id}</p>}
            {journal.lines.length > 0 && (
                <table className="w-full font-mono">
                    <thead className="text-muted-foreground">
                        <tr>
                            <th className="text-left font-normal">Account</th>
                            <th className="text-right font-normal">Debit</th>
                            <th className="text-right font-normal">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        {journal.lines.map((line, index) => (
                            <tr key={index}>
                                <td>{line.account}</td>
                                <td className="text-right">{Number(line.debit) ? formatMoney(line.debit) : ''}</td>
                                <td className="text-right">{Number(line.credit) ? formatMoney(line.credit) : ''}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
