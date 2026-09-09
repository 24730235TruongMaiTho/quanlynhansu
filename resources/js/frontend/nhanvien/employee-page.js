import { initializeActionDialogs } from './confirm-actions.js';
import { initializeEmployeeFilters } from './filter-submit.js';
import { initializeEmployeeWizards } from './wizard.js';
import { initializeEmployeeEditModal } from './edit-modal.js';
import { bindRowActionSelects } from '../shared/row-action-select.js';

const initializedPages = new WeakSet();

export function bindEmployeeEditTriggers(root, editModal) {
    if (!editModal || typeof root?.querySelectorAll !== 'function') {
        return;
    }

    const queryTriggers = (selector) => {
        try {
            return root.querySelectorAll(selector);
        } catch {
            return [];
        }
    };
    const triggers = [
        ...queryTriggers('[data-employee-edit-trigger]'),
        ...queryTriggers('[data-employee-create-trigger]'),
    ];

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            editModal.open(
                trigger,
                trigger.getAttribute('href') || trigger.href,
                trigger.dataset.employeeModalMode || (trigger.hasAttribute?.('data-employee-create-trigger') ? 'create' : 'edit'),
            );
        });
    });
}

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
    const editModal = initializeEmployeeEditModal(root, browser);
    bindEmployeeEditTriggers(root, editModal);
    bindRowActionSelects(root, browser, browser.location, {
        modal: (option, select) => editModal?.open(
            select,
            option.dataset.modalUrl || option.value,
        ),
    });
}
