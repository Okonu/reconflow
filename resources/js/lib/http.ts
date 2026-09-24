export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly requestId: string | null,
    ) {
        super(message);
    }
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function requestId(): string {
    return crypto.randomUUID().replaceAll('-', '');
}

export async function http<T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Request-ID': requestId(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const payload = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const error = payload?.error ?? {};
        throw new HttpError(response.status, error.code ?? 'error', error.message ?? response.statusText, response.headers.get('X-Request-ID'));
    }
    return payload as T;
}

export async function downloadPost(url: string, body: unknown): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/octet-stream, application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Request-ID': requestId(),
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        const error = payload?.error ?? {};
        const firstValidation = payload?.errors ? Object.values(payload.errors as Record<string, string[]>)[0]?.[0] : undefined;
        throw new HttpError(response.status, error.code ?? 'error', firstValidation ?? error.message ?? payload?.message ?? response.statusText, response.headers.get('X-Request-ID'));
    }
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? 'download';
    const blob = await response.blob();
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
}
