import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    exceptionId: number;
    can: { work: boolean; review: boolean; resolve: boolean };
}

export function WorkActions({ exceptionId, can }: Props) {
    const [resolving, setResolving] = useState(false);
    const comment = useForm({ comment: '' });
    const resolve = useForm({ reason: '' });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
                {can.review && (
                    <Button size="sm" onClick={() => router.post(route('exceptions.review', exceptionId), {}, { preserveScroll: true })}>
                        Start review
                    </Button>
                )}
                {can.resolve && !resolving && (
                    <Button size="sm" variant="outline" onClick={() => setResolving(true)}>
                        Resolve without adjustment
                    </Button>
                )}
            </div>
            {resolving && (
                <form
                    className="space-y-2 rounded-md border p-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        resolve.post(route('exceptions.resolve', exceptionId), { preserveScroll: true, onSuccess: () => setResolving(false) });
                    }}
                >
                    <Label htmlFor="resolve-reason">Why does this need no correction?</Label>
                    <Textarea id="resolve-reason" value={resolve.data.reason} onChange={(e) => resolve.setData('reason', e.target.value)} />
                    <FieldError message={resolve.errors.reason} />
                    <div className="flex gap-2">
                        <Button size="sm" type="submit" disabled={resolve.processing}>
                            Resolve
                        </Button>
                        <Button size="sm" type="button" variant="ghost" onClick={() => setResolving(false)}>
                            Cancel
                        </Button>
                    </div>
                </form>
            )}
            {can.work && (
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        comment.post(route('exceptions.comment', exceptionId), { preserveScroll: true, onSuccess: () => comment.reset() });
                    }}
                >
                    <Label htmlFor="comment">Add a comment</Label>
                    <Textarea id="comment" value={comment.data.comment} onChange={(e) => comment.setData('comment', e.target.value)} placeholder="What did you find? Who did you contact?" />
                    <FieldError message={comment.errors.comment} />
                    <Button size="sm" type="submit" variant="secondary" disabled={comment.processing || comment.data.comment.trim() === ''}>
                        Comment
                    </Button>
                </form>
            )}
        </div>
    );
}
