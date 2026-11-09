const DATE_OPTIONS: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' };

/**
 * Formats a calendar date (`due_date`, `start_date`). The API sends these as midnight UTC,
 * so parsing them with `new Date()` shows the previous day for users west of UTC.
 */
export function formatDateOnly(value: string | null | undefined, fallback = '—'): string {
  if (!value) return fallback;
  const [y, m, d] = value.slice(0, 10).split('-').map(Number);
  if (!y || !m || !d) return fallback;
  return new Date(y, m - 1, d).toLocaleDateString(uiLocale(), DATE_OPTIONS);
}

/** Formats the date part of a real timestamp (`created_at`, `completed_at`) in the user's time zone. */
export function formatTimestampDate(value: string | null | undefined, fallback = '—'): string {
  if (!value) return fallback;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? fallback : date.toLocaleDateString(uiLocale(), DATE_OPTIONS);
}

export function formatDateTime(value: string | null | undefined, fallback = '—'): string {
  if (!value) return fallback;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;
  return date.toLocaleString(uiLocale(), { ...DATE_OPTIONS, hour: '2-digit', minute: '2-digit' });
}

/** Today's date as `YYYY-MM-DD` in local time, for comparing against calendar dates. */
export function todayDateString(): string {
  const now = new Date();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${mm}-${dd}`;
}

/** UI locale for Intl formatting: follows `<html lang>` so an Arabic page gets Arabic numerals and dates. */
export function uiLocale(): string {
  return document.documentElement.lang === 'ar' ? 'ar' : 'en-US';
}

/** Long date for page eyebrows, e.g. "Sunday, November 1". */
export function formatLongDate(date: Date = new Date()): string {
  return date.toLocaleDateString(uiLocale(), { weekday: 'long', month: 'long', day: 'numeric' });
}

/** Up to two initials from a name, for avatars. */
export function initials(name: string | null | undefined): string {
  if (!name) return 'U';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  return parts
    .slice(0, 2)
    .map((part) => part[0] ?? '')
    .join('')
    .toUpperCase();
}

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
];

/** "5 minutes ago", "yesterday", "in 2 days" for a timestamp, in the UI language. */
export function formatRelativeTime(value: string | null | undefined, now: Date = new Date(), fallback = '—'): string {
  if (!value) return fallback;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;
  const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
  const format = new Intl.RelativeTimeFormat(uiLocale(), { numeric: 'auto' });
  for (const [unit, size] of RELATIVE_UNITS) {
    if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit);
  }
  return format.format(0, 'second');
}
