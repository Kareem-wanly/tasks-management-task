
export function toPage(body) {
  if (!body) {
    return {
      items: [],
      currentPage: 1,
      lastPage: 1,
      perPage: 10,
      total: 0,
    };
  }


  const meta = body.meta ?? body;

  return {
    items: Array.isArray(body.data) ? body.data : [],
    currentPage: Number(meta.current_page ?? 1),
    lastPage: Number(meta.last_page ?? 1),
    perPage: Number(meta.per_page ?? 10),
    total: Number(meta.total ?? 0),
  };
}