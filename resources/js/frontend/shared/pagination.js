const DEFAULT_PAGE_ITEMS = (currentPage, lastPage) => {
    if (lastPage <= 7) {
        return Array.from({ length: lastPage }, (_, index) => index + 1);
    }

    const pages = new Set([
        1,
        lastPage,
        currentPage - 1,
        currentPage,
        currentPage + 1,
    ]);

    return Array.from(pages)
        .filter((page) => page >= 1 && page <= lastPage)
        .sort((left, right) => left - right)
        .reduce((items, page) => {
            const previous = items.at(-1);
            if (previous !== undefined && page - previous > 1) {
                items.push('ellipsis');
            }
            items.push(page);
            return items;
        }, []);
};

function appendControl(documentRef, list, {
    active = false,
    ariaLabel,
    disabled = false,
    icon,
    page,
    pageAttribute,
    decorateControl,
    text,
}) {
    const item = documentRef.createElement('li');
    item.className = `page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}`;

    if (page === null) {
        const ellipsis = documentRef.createElement('span');
        ellipsis.className = 'page-link';
        ellipsis.setAttribute('aria-hidden', 'true');
        ellipsis.textContent = '…';
        item.appendChild(ellipsis);
        list.appendChild(item);
        return;
    }

    const button = documentRef.createElement('button');
    button.className = 'page-link';
    button.type = 'button';
    button.disabled = disabled;
    button.setAttribute('aria-label', ariaLabel);
    button.dataset[pageAttribute] = String(page);

    if (active) {
        button.setAttribute('aria-current', 'page');
    }

    decorateControl?.(button, { page, active, disabled });

    if (icon) {
        const iconElement = documentRef.createElement('i');
        iconElement.className = `bi ${icon}`;
        iconElement.setAttribute('aria-hidden', 'true');
        button.appendChild(iconElement);
    } else {
        button.textContent = text;
    }

    item.appendChild(button);
    list.appendChild(item);
}

/**
 * Render a Bootstrap-compatible paginator used by every backend list.
 * The caller keeps its existing event delegation/data attribute via options.
 * `showSinglePage` preserves the navigation shell for populated one-page lists.
 */
export function renderSharedPagination(container, metadata = {}, options = {}) {
    if (!container) {
        return;
    }

    const documentRef = options.document || container.ownerDocument || document;
    const currentPage = Math.max(Number(metadata.current_page) || 1, 1);
    const lastPage = Math.max(Number(metadata.last_page) || 1, 1);
    const pageAttribute = options.pageAttribute || 'page';

    container.classList?.add('backend-pagination');
    container.replaceChildren();

    if (lastPage <= 1 && options.showSinglePage !== true) {
        return;
    }

    const list = documentRef.createElement('ul');
    list.className = 'pagination pagination-sm mb-0 flex-wrap justify-content-center';

    appendControl(documentRef, list, {
        ariaLabel: 'Trang trước',
        disabled: currentPage <= 1,
        icon: 'bi-chevron-left',
        page: currentPage - 1,
        pageAttribute,
        decorateControl: options.decorateControl,
    });

    const pageItems = typeof options.pageItems === 'function'
        ? options.pageItems(currentPage, lastPage)
        : DEFAULT_PAGE_ITEMS(currentPage, lastPage);

    pageItems.forEach((page) => {
        if (page === 'ellipsis') {
            appendControl(documentRef, list, { page: null });
            return;
        }

        appendControl(documentRef, list, {
            active: page === currentPage,
            ariaLabel: `Trang ${page}`,
            page,
            pageAttribute,
            decorateControl: options.decorateControl,
            text: String(page),
        });
    });

    appendControl(documentRef, list, {
        ariaLabel: 'Trang sau',
        disabled: currentPage >= lastPage,
        icon: 'bi-chevron-right',
        page: currentPage + 1,
        pageAttribute,
        decorateControl: options.decorateControl,
    });

    container.appendChild(list);
}
