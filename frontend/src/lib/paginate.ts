import type { Page, PaginatedBody } from '@/types/api';

export function toPage<T>(body: PaginatedBody<T> | null | undefined): Page<T> {
  if (!body) {
    return {
      items: [],
      currentPage: 1,
      lastPage: 1,
      perPage: 10,
      total: 0,
    };
  }

  // Resource collections nest paging info under `meta`; raw paginators put it at the top level
  const meta = body.meta ?? body;

  return {
    items: Array.isArray(body.data) ? body.data : [],
    currentPage: Number(meta.current_page ?? 1),
    lastPage: Number(meta.last_page ?? 1),
    perPage: Number(meta.per_page ?? 10),
    total: Number(meta.total ?? 0),
  };
}

export type QueryParams = Record<string, string | number | boolean | null | undefined>;

/** Builds a query string, dropping empty values so the API doesn't receive `?status=`. */
export function toQuery(params: QueryParams = {}): string {
  const cleaned: Record<string, string> = {};
  for (const [key, val] of Object.entries(params)) {
    if (val !== undefined && val !== null && val !== '') {
      cleaned[key] = String(val);
    }
  }
  const query = new URLSearchParams(cleaned).toString();
  return query ? `?${query}` : '';
}
