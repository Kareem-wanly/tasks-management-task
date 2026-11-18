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

/** A span of seconds as "1h 30m", "45m" or "20s" (timer and time log). */
export function formatDuration(seconds: number): string {
  const total = Math.max(0, Math.floor(seconds));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  if (hours > 0) return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
  if (minutes > 0) return `${minutes}m`;
  return `${total}s`;
}

/** A running timer's clock, "1:05:09". */
export function formatClock(seconds: number): string {
  const total = Math.max(0, Math.floor(seconds));
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${Math.floor(total / 3600)}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;
}

/** A file size as "820 B", "14 KB", "2.4 MB". */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Bitrix24 text (BBCode, e.g. "[p]Done[/p]", "[b]x[/b]", "[url=…]link[/url]")
 * as plain text: tags dropped, paragraphs and [br] as line breaks, and chat
 * timestamps ("[TIMESTAMP=1794837753 FORMAT=…]") as a local date and time.
 */
export function bbcodeToText(value: string): string {
  return value
    .replace(/\[timestamp=(\d+)[^\]]*\]/gi, (_, seconds: string) => formatDateTime(new Date(Number(seconds) * 1000).toISOString()))
    .replace(/\[br\s*\/?\]/gi, '\n')
    .replace(/\[\/p\]\s*\[p\]/gi, '\n\n')
    .replace(/\[\/?[a-z*]+(=[^\]]*)?\]/gi, '')
    .trim();
}
