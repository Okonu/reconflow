import { useForm } from '@inertiajs/react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RoleOption } from '../types';
import { RoleChecks } from './RoleChecks';

export function CreateUser({ roles }: { roles: RoleOption[] }) {
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
