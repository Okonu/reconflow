import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';

export default function Home() {
    const { user } = usePermissions();

    return (
        <AppLayout>
            <Head title="Home" />
            <Card>
                <CardHeader>
                    <CardTitle>Welcome, {user?.name}</CardTitle>
                    <CardDescription>Signed in as {user?.roles.join(', ')}. The dashboard arrives in a later phase.</CardDescription>
                </CardHeader>
                <CardContent />
            </Card>
        </AppLayout>
    );
}
