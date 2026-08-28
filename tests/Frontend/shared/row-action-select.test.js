import assert from 'node:assert/strict';
import test from 'node:test';

import { handleRowAction } from '../../../resources/js/frontend/shared/row-action-select.js';

test('row action select navigates only after selecting a real route', () => {
    const select = { selectedIndex: 1 };
    const option = { value: '/chuc-vu/2/edit', dataset: { action: 'navigate' } };
    let href = '';

    handleRowAction(select, option, {}, { set href(value) { href = value; } });

    assert.equal(href, '/chuc-vu/2/edit');
    assert.equal(select.selectedIndex, 0);
});

test('row action select confirms before submitting a destructive form', () => {
    const select = { selectedIndex: 1 };
    const option = { dataset: { action: 'delete', confirmMessage: 'Xóa?', formId: 'delete-form' } };
    let submitted = 0;
    let asked = 0;
    const form = { submit: () => { submitted += 1; } };
    const root = { getElementById: () => form };

    handleRowAction(select, option, {
        confirm: () => { asked += 1; return true; },
    }, null, root);

    assert.equal(asked, 1);
    assert.equal(submitted, 1);
    assert.equal(select.selectedIndex, 0);
});
