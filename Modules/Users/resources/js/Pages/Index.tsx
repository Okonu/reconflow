import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: { id: number; code: string; label: string }[];
    last_login_at: string | null;
}

export default function UsersIndex({ users }: { users: { data: UserRow[] } }) {
    return (
        <AppLayout>
            <Head title="Users" />
            <Card>
                <CardHeader>
                    <CardTitle>Users</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-2">Name</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Last sign-in</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="py-2">{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>{user.roles.map((role) => role.label).join(', ')}</td>
                                    <td>
                                        <Badge variant={user.is_active ? 'secondary' : 'outline'}>{user.is_active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td>{formatDateTime(user.last_login_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
