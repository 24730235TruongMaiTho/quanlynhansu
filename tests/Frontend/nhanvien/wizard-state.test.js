import assert from 'node:assert/strict';
import test from 'node:test';

import {
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
