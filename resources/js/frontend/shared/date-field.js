function dateFromParts(year, month, day) {
    const date = new Date(0);
    date.setUTCHours(0, 0, 0, 0);
    date.setUTCFullYear(year, month - 1, day);

    return date;
}

function isValidDateParts(year, month, day) {
    const date = dateFromParts(year, month, day);

    return date.getUTCFullYear() === year
        && date.getUTCMonth() === month - 1
        && date.getUTCDate() === day;
}

export function parseDisplayDate(display) {
    if (typeof display !== 'string') return null;

    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(display);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    return isValidDateParts(year, month, day) ? { day, month, year } : null;
}

export function toIsoDate(display) {
    const parts = parseDisplayDate(display);
    if (!parts) return null;

    return `${String(parts.year).padStart(4, '0')}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

export function formatDisplayDate(iso) {
    if (typeof iso !== 'string') return '';

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!match) return '';

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (!isValidDateParts(year, month, day)) return '';

    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${String(year).padStart(4, '0')}`;
}

export function canonicalServerDate(value) {
    const match = typeof value === 'string'
        ? /^(\d{4}-\d{2}-\d{2})/.exec(value)
        : null;
    const iso = match?.[1] || null;

    return iso && formatDisplayDate(iso) ? iso : null;
}

export function daysBetweenIsoDates(from, to) {
    const fromMatch = typeof from === 'string' ? /^(\d{4})-(\d{2})-(\d{2})$/.exec(from) : null;
    const toMatch = typeof to === 'string' ? /^(\d{4})-(\d{2})-(\d{2})$/.exec(to) : null;
    if (!fromMatch || !toMatch || !formatDisplayDate(from) || !formatDisplayDate(to)) return 0;

    const fromTime = Date.UTC(Number(fromMatch[1]), Number(fromMatch[2]) - 1, Number(fromMatch[3]));
    const toTime = Date.UTC(Number(toMatch[1]), Number(toMatch[2]) - 1, Number(toMatch[3]));

    return Math.floor((toTime - fromTime) / 86400000) + 1;
}
