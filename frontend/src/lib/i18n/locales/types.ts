/** A translation of an English namespace: every key, plus extra plural forms (Arabic has six). */
export type Translation<T> = { [K in keyof T]: string } & Record<string, string>;
