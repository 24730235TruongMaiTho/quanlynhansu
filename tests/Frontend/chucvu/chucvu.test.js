import test from 'node:test';
import assert from 'node:assert/strict';

test('position submit disables the button and announces saving', async () => {
    const source = await import('../../..//resources/js/frontend/chucvu/chucvu.js');
    const submit = { dataset: { submittingText: 'Đang lưu...' }, disabled: false, setAttribute: () => {}, textContent: 'Lưu' };
    const form = { querySelector: () => submit };

    source.disableSubmit(form);

    assert.equal(submit.disabled, true);
    assert.equal(submit.textContent, 'Đang lưu...');
});
