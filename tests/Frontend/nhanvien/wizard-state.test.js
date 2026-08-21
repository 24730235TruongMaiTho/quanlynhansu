import assert from 'node:assert/strict';
import test from 'node:test';

import {
    reconcileAvatarChoice,
    firstInvalidStep,
    nextStep,
    previousStep,
} from '../../../resources/js/frontend/nhanvien/wizard-state.js';

test('opens the step containing the first invalid field', () => {
    assert.equal(firstInvalidStep(['email', 'ma_pb']), 1);
    assert.equal(firstInvalidStep(['ma_pb']), 2);
    assert.equal(firstInvalidStep([]), 3);
});

test('moves only within the three wizard steps', () => {
    assert.equal(nextStep(1), 2);
    assert.equal(nextStep(2), 3);
    assert.equal(nextStep(3), 3);
    assert.equal(previousStep(3), 2);
    assert.equal(previousStep(2), 1);
    assert.equal(previousStep(1), 1);
});

test('reconciles avatar upload and deletion as mutually exclusive choices', () => {
    assert.deepEqual(reconcileAvatarChoice('file', true, true), {
        hasFile: true,
        deleteChecked: false,
    });
    assert.deepEqual(reconcileAvatarChoice('delete', true, true), {
        hasFile: false,
        deleteChecked: true,
    });
    assert.deepEqual(reconcileAvatarChoice('delete', true, false), {
        hasFile: true,
        deleteChecked: false,
    });
});

test('wizard indicators are resolved from the employee page instead of the form', async () => {
    const source = await import('node:fs/promises').then(({ readFile }) => readFile(
        new URL('../../../resources/js/frontend/nhanvien/wizard.js', import.meta.url),
        'utf8',
    ));

    assert.match(source, /page\.querySelectorAll\('\[data-step-indicator\]'\)/);
    assert.doesNotMatch(source, /form\.querySelectorAll\('\[data-step-indicator\]'\)/);
});
