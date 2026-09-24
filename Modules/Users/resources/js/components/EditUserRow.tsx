import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TableCell, TableRow } from '@/components/ui/table';
import type { RoleOption, UserRow } from '../types';
import { RoleChecks } from './RoleChecks';

export function EditUserRow({ user, roles, canAssign, onDone }: { user: UserRow; roles: RoleOption[]; canAssign: boolean; onDone: () => void }) {
    const form = useForm({ name: user.name, region: user.region ?? '' });
    const [roleIds, setRoleIds] = useState(user.roles.map((r) => r.id));

    const save = () => {
        form.patch(route('users.update', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (canAssign && JSON.stringify([...roleIds].sort()) !== JSON.stringify(user.roles.map((r) => r.id).sort())) {
                    router.put(route('rbac.users.roles', user.id), { role_ids: roleIds }, { preserveScroll: true, onSuccess: onDone });
                } else {
                    onDone();
                }
            },
        });
    };

    return (
        <TableRow>
            <TableCell colSpan={6} className="bg-muted/40">
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="space-y-1">
                        <Label>Name</Label>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <FieldError message={form.errors.name} />
                    </div>
                    <div className="space-y-1">
                        <Label>Region</Label>
                        <Input value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} />
                    </div>
                </div>
                {canAssign && (
                    <div className="mt-3 space-y-1">
                        <Label>Roles</Label>
                        <RoleChecks roles={roles} selected={roleIds} onChange={setRoleIds} />
                    </div>
                )}
                <div className="mt-3 flex gap-2">
                    <Button size="sm" onClick={save} disabled={form.processing || roleIds.length === 0}>
                        Save
                    </Button>
                    <Button size="sm" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}
