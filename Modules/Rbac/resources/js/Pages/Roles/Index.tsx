import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';

interface RoleRow {
    id: number;
    code: string;
    label: string;
    description: string | null;
    is_system: boolean;
    permissions: string[];
    user_count?: number;
    can: { update: boolean; delete: boolean };
}

interface PermissionRow {
    code: string;
    group: string;
    description: string;
}

interface Props {
    roles: { data: RoleRow[] };
    permissions: { data: PermissionRow[] };
    can: { create: boolean };
}

function PermissionPicker({ permissions, selected, onChange, disabled }: { permissions: PermissionRow[]; selected: string[]; onChange: (codes: string[]) => void; disabled?: boolean }) {
    const groups = useMemo(() => {
        const map = new Map<string, PermissionRow[]>();
        permissions.forEach((p) => map.set(p.group, [...(map.get(p.group) ?? []), p]));
        return [...map.entries()];
    }, [permissions]);

    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {groups.map(([group, items]) => (
                <fieldset key={group} className="space-y-2 rounded-md border p-3">
                    <legend className="px-1 text-sm font-medium">{group}</legend>
                    {items.map((p) => (
                        <label key={p.code} className="flex items-start gap-2 text-sm">
                            <Checkbox
                                className="mt-0.5"
                                disabled={disabled}
                                checked={selected.includes(p.code)}
                                onCheckedChange={(v) => onChange(v === true ? [...selected, p.code] : selected.filter((c) => c !== p.code))}
                            />
                            <span>
                                {p.description}
                                <span className="block font-mono text-[11px] text-muted-foreground">{p.code}</span>
                            </span>
                        </label>
                    ))}
                </fieldset>
            ))}
        </div>
    );
}

function RoleEditor({ role, permissions, onDone }: { role: RoleRow | null; permissions: PermissionRow[]; onDone: () => void }) {
    const form = useForm({ code: role?.code ?? '', label: role?.label ?? '', description: role?.description ?? '', permissions: role?.permissions ?? [] });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = () =>
        role === null
            ? form.post(route('rbac.roles.store'), { preserveScroll: true, onSuccess: onDone })
            : form.patch(route('rbac.roles.update', role.id), { preserveScroll: true, onSuccess: onDone });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{role === null ? 'New role' : `Edit ${role.label}`}</CardTitle>
                <CardDescription>Pick the permissions this role grants. Changes apply to everyone with the role immediately and are audited.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-3">
                    {role === null && (
                        <div className="space-y-1">
                            <Label htmlFor="role-code">Code</Label>
                            <Input id="role-code" placeholder="e.g. regional_lead" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                            <FieldError message={errors.code} />
                        </div>
                    )}
                    <div className="space-y-1">
                        <Label htmlFor="role-label">Name</Label>
                        <Input id="role-label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
                        <FieldError message={errors.label} />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="role-description">Description</Label>
                        <Input id="role-description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>
                </div>
                <PermissionPicker permissions={permissions} selected={form.data.permissions} onChange={(codes) => form.setData('permissions', codes)} />
                <FieldError message={errors.permissions} />
                <div className="flex gap-2">
                    <Button size="sm" onClick={submit} disabled={form.processing}>
                        {role === null ? 'Create role' : 'Save role'}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={onDone}>
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function RolesIndex({ roles, permissions, can }: Props) {
    const [editing, setEditing] = useState<RoleRow | 'new' | null>(null);

    return (
        <AppLayout>
            <Head title="Roles" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Roles</h1>
                        <p className="text-sm text-muted-foreground">Permissions are defined by the system; roles bundle them and can be changed at any time without a release.</p>
                    </div>
                    {can.create && editing === null && (
                        <Button size="sm" onClick={() => setEditing('new')}>
                            New role
                        </Button>
                    )}
                </div>

                {editing !== null && <RoleEditor key={editing === 'new' ? 'new' : editing.id} role={editing === 'new' ? null : editing} permissions={permissions.data} onDone={() => setEditing(null)} />}

                <div className="grid gap-4 lg:grid-cols-2">
                    {roles.data.map((role) => (
                        <Card key={role.id}>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                    {role.label}
                                    {role.is_system && <Badge variant="outline">protected</Badge>}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {role.user_count ?? 0} users · {role.permissions.length} permissions
                                    </span>
                                </CardTitle>
                                {role.description && <CardDescription>{role.description}</CardDescription>}
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex flex-wrap gap-1">
                                    {role.permissions.map((code) => (
                                        <Badge key={code} variant="secondary" className="font-mono text-[11px]">
                                            {code}
                                        </Badge>
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    {role.can.update && (
                                        <Button size="sm" variant="outline" onClick={() => setEditing(role)}>
                                            Edit
                                        </Button>
                                    )}
                                    {role.can.delete && (role.user_count ?? 0) === 0 && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => router.delete(route('rbac.roles.destroy', role.id), { preserveScroll: true })}>
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
