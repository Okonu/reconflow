import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export function ResetDemoCard() {
    const form = useForm({ confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('ingestion.demo.reset'), { onSuccess: () => form.reset() });
    };

    return (
        <Card className="border-destructive/30">
            <CardHeader>
                <CardTitle>Reset demo data</CardTitle>
                <CardDescription>
                    Clears all operational data (source batches, uploads, simulated source systems) and regenerates synthetic data. Users, roles and the
                    audit log are kept. Type RESET to confirm.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row">
                    <Input value={form.data.confirmation} onChange={(e) => form.setData('confirmation', e.target.value)} placeholder="RESET" className="sm:max-w-40" />
                    <Button type="submit" variant="destructive" disabled={form.processing || form.data.confirmation !== 'RESET'}>
                        Reset demo data
                    </Button>
                </form>
                {form.errors.confirmation && <p className="mt-2 text-sm text-destructive">{form.errors.confirmation}</p>}
            </CardContent>
        </Card>
    );
}
