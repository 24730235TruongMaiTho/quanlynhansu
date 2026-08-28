import { initializeActionDialogs } from './confirm-actions.js';
import { initializeEmployeeFilters } from './filter-submit.js';
import { initializeEmployeeWizards } from './wizard.js';
import { bindRowActionSelects } from '../shared/row-action-select.js';

const initializedPages = new WeakSet();

export function initializeEmployeePage(
    root = typeof document !== 'undefined' ? document : null,
    browser = typeof window !== 'undefined' ? window : {},
) {
    if (!root || typeof root !== 'object' || typeof root.querySelectorAll !== 'function') {
        return;
    }

    if (initializedPages.has(root)) {
        return;
    }

    initializedPages.add(root);
    initializeEmployeeWizards(root);
    initializeEmployeeFilters(root);
    initializeActionDialogs(root, browser);
    bindRowActionSelects(root, browser);
}
