import assert from 'node:assert/strict';
import test from 'node:test';

import { createConfirmDialog } from '../../../resources/js/frontend/shared/confirm-dialog.js';

class FakeElement {
    constructor() {
        this.listeners = new Map();
        this.focusCount = 0;
    }

    addEventListener(name, handler) {
        const handlers = this.listeners.get(name) || [];
        handlers.push(handler);
        this.listeners.set(name, handlers);
    }

    dispatch(name, event = {}) {
        const handlers = this.listeners.get(name) || [];
        const dispatched = {
            target: this,
            preventDefault() {
                dispatched.prevented = true;
            },
            ...event,
        };

        handlers.forEach((handler) => handler(dispatched));
        return dispatched;
    }

    focus() {
        this.focusCount += 1;
    }
}

class FakeDialog extends FakeElement {
    constructor() {
        super();
        this.open = false;
        this.showCount = 0;
        this.closeCount = 0;
    }

    showModal() {
        this.showCount += 1;
        this.open = true;
    }

    close(returnValue = '') {
        this.closeCount += 1;
        this.returnValue = returnValue;
        this.open = false;
    }
}

function createFixture(overrides = {}) {
    const dialog = overrides.dialog || new FakeDialog();
    const cancelButton = new FakeElement();
    const confirmButton = new FakeElement();
    const firstOpener = new FakeElement();
    const secondOpener = new FakeElement();
    const unavailable = [];
    const controller = createConfirmDialog({
        dialog,
        cancelButton,
        confirmButton,
        onUnavailable: () => unavailable.push(true),
    });

    return {
        dialog,
        cancelButton,
        confirmButton,
        firstOpener,
        secondOpener,
        unavailable,
        controller,
    };
}

test('custom dialog focuses cancel, resolves confirm, and restores opener focus', async () => {
    const fixture = createFixture();
    const pending = fixture.controller.open(fixture.firstOpener);

    assert.equal(fixture.dialog.open, true);
    assert.equal(fixture.cancelButton.focusCount, 1);

    fixture.confirmButton.dispatch('click');

    assert.equal(await pending, true);
    assert.equal(fixture.dialog.open, false);
    assert.equal(fixture.firstOpener.focusCount, 1);
});

test('custom dialog cancel and Escape resolve false and restore focus', async () => {
    const fixture = createFixture();
    const cancelled = fixture.controller.open(fixture.firstOpener);
    const cancelEvent = fixture.cancelButton.dispatch('click');

    assert.equal(cancelEvent.prevented, true);
    assert.equal(await cancelled, false);
    assert.equal(fixture.firstOpener.focusCount, 1);

    const escaped = fixture.controller.open(fixture.secondOpener);
    const keyEvent = fixture.dialog.dispatch('keydown', { key: 'Escape' });

    assert.equal(keyEvent.prevented, true);
    assert.equal(await escaped, false);
    assert.equal(fixture.secondOpener.focusCount, 1);
});

test('native dialog cancel event resolves false', async () => {
    const fixture = createFixture();
    const pending = fixture.controller.open(fixture.firstOpener);

    const event = fixture.dialog.dispatch('cancel');

    assert.equal(event.prevented, true);
    assert.equal(await pending, false);
    assert.equal(fixture.firstOpener.focusCount, 1);
});

test('concurrent open closes the old request and binds listeners once', async () => {
    const fixture = createFixture();
    const first = fixture.controller.open(fixture.firstOpener);
    const second = fixture.controller.open(fixture.secondOpener);

    assert.equal(await first, false);
    assert.equal(fixture.dialog.showCount, 2);
    assert.equal(fixture.dialog.listeners.get('cancel')?.length, 1);
    assert.equal(fixture.dialog.listeners.get('keydown')?.length, 1);
    assert.equal(fixture.cancelButton.listeners.get('click')?.length, 1);
    assert.equal(fixture.confirmButton.listeners.get('click')?.length, 1);

    fixture.confirmButton.dispatch('click');
    assert.equal(await second, true);
    assert.equal(fixture.secondOpener.focusCount, 1);
});

test('missing dialog API fails closed without opening or native prompt', async () => {
    const fixture = createFixture({ dialog: new FakeElement() });
    const pending = fixture.controller.open(fixture.firstOpener);

    assert.equal(await pending, false);
    assert.equal(fixture.unavailable.length, 1);
    assert.equal(fixture.firstOpener.focusCount, 0);
});
