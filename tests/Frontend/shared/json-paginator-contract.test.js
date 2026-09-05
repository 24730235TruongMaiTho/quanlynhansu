import assert from 'node:assert/strict';
import test from 'node:test';

import {
    normalizePageSize,
    normalizePaginator,
} from '../../../resources/js/frontend/shared/json-paginator.js';

test('normalizes the Laravel wrapper to the shared list shape', () => {
    assert.deepEqual(
        normalizePaginator({
            success: true,
            data: {
                data: [{ id: 1 }],
                current_page: 2,
                last_page: 4,
                per_page: 20,
                total: 65,
                from: 21,
                to: 40,
            },
        }),
        {
            data: [{ id: 1 }],
            current_page: 2,
            last_page: 4,
            per_page: 20,
            total: 65,
            from: 21,
            to: 40,
        },
    );
});

test('falls back to ten and keeps empty metadata deterministic', () => {
    assert.equal(normalizePageSize(5), 10);
    assert.equal(normalizePageSize(50), 50);
    assert.deepEqual(normalizePaginator({ data: [] }), {
        data: [], current_page: 1, last_page: 1, per_page: 10,
        total: 0, from: 0, to: 0,
    });
});
