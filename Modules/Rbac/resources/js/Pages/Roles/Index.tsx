import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface RoleRow {
    id: number;
    code: string;
    label: string;
    description: string;
    is_system: boolean;
    permissions: string[];
    user_count?: number;
}

export default function RolesIndex({ roles }: { roles: { data: RoleRow[] } }) {
    return (
        <AppLayout>
            <Head title="Roles" />
            <div className="grid gap-4 md:grid-cols-2">
                {roles.data.map((role) => (
                    <Card key={role.id}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {role.label}
                                {role.is_system && <Badge variant="outline">System</Badge>}
                            </CardTitle>
                            <CardDescription>{role.description}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-1">
                            {role.permissions.map((permission) => (
                                <Badge key={permission} variant="secondary">
                                    {permission}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
