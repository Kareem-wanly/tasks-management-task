// Helpers for calendar dates (`YYYY-MM-DD`, e.g. due_date), computed in local time.
import { todayDateString, uiLocale } from './format';

const DAY_MS = 24 * 60 * 60 * 1000;

function parseDate(value: string): Date | null {
  const [y, m, d] = value.slice(0, 10).split('-').map(Number);
  if (!y || !m || !d) return null;
  return new Date(y, m - 1, d);
}

function toDateString(date: Date): string {
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const dd = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${mm}-${dd}`;
}

/** The `YYYY-MM-DD` part of an API date, or null. */
export function toDateOnly(value: string | null | undefined): string | null {
  return value ? value.slice(0, 10) : null;
}

/** `value` moved by `days` (negative goes back). */
export function addDays(value: string, days: number): string {
  const date = parseDate(value) ?? new Date();
  date.setDate(date.getDate() + days);
  return toDateString(date);
}

/** Today + `days`, as `YYYY-MM-DD`. */
export function daysFromNow(days: number): string {
  return addDays(todayDateString(), days);
}

/** Whole days from today to `value`: negative when it has passed, 0 for today, null without a date. */
export function daysUntil(value: string | null | undefined): number | null {
  if (!value) return null;
  const date = parseDate(value);
  const today = parseDate(todayDateString());
  if (!date || !today) return null;
  return Math.round((date.getTime() - today.getTime()) / DAY_MS);
}

/** The later of a due date and today, so extending an overdue item starts from today. */
export function extensionBase(dueDate: string | null | undefined): string {
  const today = todayDateString();
  const due = toDateOnly(dueDate);
  return due && due > today ? due : today;
}

/** First day of the month containing `value`, as `YYYY-MM-01`. */
export function startOfMonth(value: string): string {
  return `${value.slice(0, 7)}-01`;
}

/** The first day of the month `months` away from the month of `value`. */
export function addMonths(value: string, months: number): string {
  const date = parseDate(startOfMonth(value)) ?? new Date();
  date.setMonth(date.getMonth() + months);
  return toDateString(date);
}

/** The 6×7 days shown for a month view, starting on the Sunday on or before the 1st. */
export function monthGridDays(month: string): string[] {
  const first = parseDate(startOfMonth(month)) ?? new Date();
  first.setDate(first.getDate() - first.getDay());
  return Array.from({ length: 42 }, (_, i) => {
    const day = new Date(first);
    day.setDate(first.getDate() + i);
    return toDateString(day);
  });
}

/** "November 2026" in the UI language. */
export function monthLabel(month: string, locale = uiLocale()): string {
  const date = parseDate(startOfMonth(month)) ?? new Date();
  return date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
}

/** Short weekday names, Sunday first. */
export function weekdayLabels(locale = uiLocale()): string[] {
  // 2023-01-01 was a Sunday
  return Array.from({ length: 7 }, (_, i) => new Date(2023, 0, 1 + i).toLocaleDateString(locale, { weekday: 'short' }));
}

/** Day of the month (1–31) of a calendar date. */
export function dayOfMonth(value: string): number {
  return Number(value.slice(8, 10));
}
