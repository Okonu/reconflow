import * as React from 'react';

import { cn } from '@/lib/utils';

const NativeSelect = React.forwardRef<HTMLSelectElement, React.ComponentProps<'select'>>(({ className, ...props }, ref) => (
    <select ref={ref} className={cn('h-10 rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50', className)} {...props} />
));
NativeSelect.displayName = 'NativeSelect';

export { NativeSelect };
