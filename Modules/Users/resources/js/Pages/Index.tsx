import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { CreateUser } from '../components/CreateUser';
import { EditUserRow } from '../components/EditUserRow';
import type { RoleOption, UserRow } from '../types';

interface Props {
    users: { data: UserRow[] };
    roles: RoleOption[];
    can: { create: boolean; assign_roles: boolean };
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
                                            <EditUserRow key={user.id} user={user} roles={roles} canAssign={can.assign_roles} onDone={() => setEditing(null)} />
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
