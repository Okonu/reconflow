import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function Login() {
    const form = useForm({ email: '', password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('login.store'), { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title="Sign in" description="Daily sales reconciliation for Tupande">
            <Head title="Sign in" />
            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                            {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                            {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                        </div>
                        <Button type="submit" className="w-full" disabled={form.processing}>
                            Sign in
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}
