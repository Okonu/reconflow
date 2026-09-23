const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
const dateTime = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Nairobi' });
const date = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeZone: 'Africa/Nairobi' });

export function formatMoney(amount: string | null | undefined): string {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }
    return currency.format(Number(amount));
}

export function formatDateTime(iso: string | null | undefined): string {
    return iso ? dateTime.format(new Date(iso)) : '—';
}

export function formatDate(iso: string | null | undefined): string {
    return iso ? date.format(new Date(iso)) : '—';
}
