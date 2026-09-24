import { Sparkles } from 'lucide-react';

export function AiLabel({ model }: { model: string }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">
            <Sparkles className="size-3" />
            AI generated · {model}
        </span>
    );
}
