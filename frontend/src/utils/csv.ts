/** One CSV field: quoted when it holds a comma, quote or line break; formula-looking text is defused. */
function field(value: string | number | null | undefined): string {
  let text = value === null || value === undefined ? '' : String(value);
  // Spreadsheets run cells starting with = + - @ as formulas
  if (/^[=+\-@]/.test(text)) text = `'${text}`;
  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

/** Rows to CSV text (first row = header), with a BOM so Excel reads UTF-8 (e.g. Arabic) correctly. */
export function toCsv(rows: (string | number | null | undefined)[][]): string {
  return '﻿' + rows.map((row) => row.map(field).join(',')).join('\r\n');
}
