export const PAGE_SIZES = Object.freeze([10, 20, 50]);

export function normalizePageSize(value, fallback = PAGE_SIZES[0]) {
    const parsed = Number(value);

    return PAGE_SIZES.includes(parsed) ? parsed : fallback;
}

export function normalizePaginator(payload, fallbackPageSize = PAGE_SIZES[0]) {
    const wrapped = payload?.data;
    const source = wrapped && !Array.isArray(wrapped) && Array.isArray(wrapped.data)
        ? wrapped
        : payload;
    const rows = Array.isArray(source?.data)
        ? source.data
        : Array.isArray(source)
            ? source
            : [];
    const perPage = normalizePageSize(source?.per_page, fallbackPageSize);
    const total = Number.isFinite(Number(source?.total))
        ? Math.max(Number(source.total), rows.length)
        : rows.length;
    const currentPage = Math.max(Number(source?.current_page) || 1, 1);
    const lastPage = Math.max(
        Number(source?.last_page) || Math.ceil(total / perPage) || 1,
        1,
    );

    return {
        data: rows,
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total,
        from: total > 0 ? Number(source?.from) || ((currentPage - 1) * perPage) + 1 : 0,
        to: total > 0 ? Number(source?.to) || Math.min(currentPage * perPage, total) : 0,
    };
}
