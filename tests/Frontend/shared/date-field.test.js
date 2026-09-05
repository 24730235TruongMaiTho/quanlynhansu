import assert from 'node:assert/strict';
import test from 'node:test';

import {
    canonicalServerDate,
    formatDisplayDate,
    daysBetweenIsoDates,
    parseDisplayDate,
    toIsoDate,
} from '../../../resources/js/frontend/shared/date-field.js';

test('formats an ISO date for the Vietnamese display format', () => {
    assert.equal(formatDisplayDate('2026-09-03'), '03/09/2026');
});

test('parses a strict display date into date parts', () => {
    assert.deepEqual(parseDisplayDate('03/09/2026'), {
        day: 3,
        month: 9,
        year: 2026,
    });
});

test('converts valid display dates to ISO without accepting impossible dates', () => {
    assert.equal(toIsoDate('03/09/2026'), '2026-09-03');
    assert.equal(toIsoDate('31/02/2026'), null);
});

test('requires the complete DD/MM/YYYY display format', () => {
    assert.equal(parseDisplayDate('3/9/2026'), null);
    assert.equal(parseDisplayDate('03-09-2026'), null);
    assert.equal(parseDisplayDate('03/09/2026 extra'), null);
});

test('calculates inclusive day counts from canonical ISO dates', () => {
    assert.equal(daysBetweenIsoDates('2026-09-03', '2026-09-05'), 3);
    assert.equal(daysBetweenIsoDates('2026-02-31', '2026-03-02'), 0);
});

test('normalizes canonical server date prefixes without trusting invalid dates', () => {
    assert.equal(canonicalServerDate('2026-09-03T00:00:00.000000Z'), '2026-09-03');
    assert.equal(canonicalServerDate('2026-02-31'), null);
    assert.equal(canonicalServerDate('03/09/2026'), null);
});
