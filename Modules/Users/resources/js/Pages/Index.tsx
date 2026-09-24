import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FieldError } from '@/components/field-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';

interface RoleOption {
    id: number;
    label: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    region: string | null;
    is_active: boolean;
    roles: { id: number; code: string; label: string }[];
    last_login_at: string | null;
    can: { update: boolean };
}

interface Props {
    users: { data: UserRow[] };
    roles: RoleOption[];
    can: { create: boolean; assign_roles: boolean };
}

function RoleChecks({ roles, selected, onChange }: { roles: RoleOption[]; selected: number[]; onChange: (ids: number[]) => void }) {
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

function CreateUser({ roles }: { roles: RoleOption[] }) {
    const form = useForm({ name: '', email: '', password: '', region: '', role_ids: [] as number[] });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Add a user</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('users.store'), { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1">
                            <Label htmlFor="new-name">Name</Label>
                            <Input id="new-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            <FieldError message={form.errors.name} />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="new-email">Email</Label>
                            <Input id="new-email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            <FieldError message={form.errors.email} />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="new-password">Initial password</Label>
                            <Input id="new-password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                            <FieldError message={form.errors.password} />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="new-region">Region (optional)</Label>
                            <Input id="new-region" value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label>Roles</Label>
                        <RoleChecks roles={roles} selected={form.data.role_ids} onChange={(ids) => form.setData('role_ids', ids)} />
                        <FieldError message={form.errors.role_ids} />
                    </div>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Create user
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function EditRow({ user, roles, canAssign, onDone }: { user: UserRow; roles: RoleOption[]; canAssign: boolean; onDone: () => void }) {
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

export default function UsersIndex({ users, roles, can }: Props) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <AppLayout>
            <Head title="Users" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Users</h1>
                    <p className="text-sm text-muted-foreground">Access comes from roles, and roles are made of permissions. Deactivated users cannot sign in; their history stays.</p>
                </div>
                {can.create && <CreateUser roles={roles} />}
                <Card>
                    <CardContent className="pt-6">
                        {users.data.length === 0 ? (
                            <EmptyState title="No users yet" />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Roles</TableHead>
                                        <TableHead>Region</TableHead>
                                        <TableHead>Last sign-in</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) =>
                                        editing === user.id ? (
                                            <EditRow key={user.id} user={user} roles={roles} canAssign={can.assign_roles} onDone={() => setEditing(null)} />
                                        ) : (
                                            <TableRow key={user.id} className={user.is_active ? undefined : 'opacity-60'}>
                                                <TableCell>
                                                    {user.name}
                                                    {!user.is_active && <span className="ml-2 text-xs text-muted-foreground">(deactivated)</span>}
                                                </TableCell>
                                                <TableCell>{user.email}</TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles.map((role) => (
                                                            <Badge key={role.id} variant="secondary">
                                                                {role.label}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </TableCell>
                                                <TableCell>{user.region ?? '—'}</TableCell>
                                                <TableCell className="whitespace-nowrap">{formatDateTime(user.last_login_at)}</TableCell>
                                                <TableCell className="whitespace-nowrap text-right">
                                                    {user.can.update && (
                                                        <div className="flex justify-end gap-2">
                                                            <Button size="sm" variant="outline" onClick={() => setEditing(user.id)}>
                                                                Edit
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => router.patch(route('users.update', user.id), { is_active: !user.is_active }, { preserveScroll: true })}
                                                            >
                                                                {user.is_active ? 'Deactivate' : 'Reactivate'}
                                                            </Button>
                                                        </div>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
