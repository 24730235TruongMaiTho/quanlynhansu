(function (window) {
    'use strict';

    const api = window.qlnsSidebarState = window.qlnsSidebarState || {};

    api.findActiveSubmenu = function (documentRef) {
        const activeGroup = documentRef?.querySelector?.(
            '[data-route-active="true"], [data-route-active="1"]'
        );

        if (!activeGroup) return null;
        if (activeGroup.matches?.('.sub-menu')) return activeGroup;

        return activeGroup.querySelector?.(':scope > .sub-menu')
            || activeGroup.querySelector?.('.sub-menu')
            || null;
    };
}(window));
