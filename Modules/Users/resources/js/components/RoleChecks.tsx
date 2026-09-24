import { Checkbox } from '@/components/ui/checkbox';
import type { RoleOption } from '../types';

export function RoleChecks({ roles, selected, onChange }: { roles: RoleOption[]; selected: number[]; onChange: (ids: number[]) => void }) {
    return (
        <div className="flex flex-wrap gap-3">
            {roles.map((r) => (
                <label key={r.id} className="flex items-center gap-2 text-sm">
                    <Checkbox checked={selected.includes(r.id)} onCheckedChange={(v) => onChange(v === true ? [...selected, r.id] : selected.filter((x) => x !== r.id))} />
                    {r.label}
                </label>
            ))}
        </div>
    );
}
