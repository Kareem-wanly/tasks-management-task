import { readPreference, writePreference } from '@/lib/preferences/storage';
import ar from './locales/ar';
import en, { type Dictionary } from './locales/en';

export type Language = 'en' | 'ar';

export const LANGUAGES: { value: Language; label: string; dir: 'ltr' | 'rtl' }[] = [
  { value: 'en', label: 'English', dir: 'ltr' },
  { value: 'ar', label: 'العربية', dir: 'rtl' },
];

/** "namespace.key" for every key of the English dictionary (the source of truth). */
export type TranslationKey = {
  [N in keyof Dictionary]: `${N & string}.${Extract<keyof Dictionary[N], string>}`;
}[keyof Dictionary];

/** Base keys that have plural forms, written in the dictionaries as `key_one`, `key_other` (and `_two`, `_few`… in Arabic). */
export type PluralKey = {
  [N in keyof Dictionary]: `${N & string}.${Extract<keyof Dictionary[N], string> extends infer K
    ? K extends `${infer Base}_other`
      ? Base
      : never
    : never}`;
}[keyof Dictionary];

export type TranslationVars = Record<string, string | number>;

const DICTIONARIES: Record<Language, Record<string, Record<string, string>>> = { en, ar };

/** The language is fixed for the page's lifetime: switching reloads, so every module sees one language. */
const current: Language = readPreference('ui-language') === 'ar' ? 'ar' : 'en';

export function getLanguage(): Language {
  return current;
}

export function getDirection(): 'ltr' | 'rtl' {
  return current === 'ar' ? 'rtl' : 'ltr';
}

/** Saves the language and reloads, so text built at startup (labels, schemas, dates) is rebuilt in it. */
export function setLanguage(language: Language): void {
  if (language === current) return;
  writePreference('ui-language', language);
  window.location.reload();
}

function lookup(key: string): string | undefined {
  const dot = key.indexOf('.');
  const namespace = key.slice(0, dot);
  const name = key.slice(dot + 1);
  return DICTIONARIES[current][namespace]?.[name] ?? DICTIONARIES.en[namespace]?.[name];
}

function interpolate(text: string, vars?: TranslationVars): string {
  if (!vars) return text;
  return text.replace(/\{(\w+)\}/g, (match, name: string) => (name in vars ? String(vars[name]) : match));
}

/**
 * Translates a key, e.g. `t('tasks.newTask')` or `t('tasks.deleted', { title })`.
 * Missing Arabic text falls back to English; a missing key shows the key itself.
 */
export function t(key: TranslationKey, vars?: TranslationVars): string {
  return interpolate(lookup(key) ?? key, vars);
}

const pluralRules = new Intl.PluralRules(current);

/**
 * Plural-aware translation: `tp('tasks.taskCount', 3)` reads `taskCount_few` in Arabic, `taskCount_other` in
 * English. `{count}` is available in the text.
 */
export function tp(key: PluralKey, count: number, vars?: TranslationVars): string {
  const form = lookup(`${key}_${pluralRules.select(count)}`) ?? lookup(`${key}_other`) ?? key;
  return interpolate(form, { count, ...vars });
}
