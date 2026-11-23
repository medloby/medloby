import assert from 'node:assert/strict';
import test from 'node:test';
import { createWorkspaceApi } from '../resources/js/workspace-api.js';

test('a pending attachment download is discarded when its session ends', async (t) => {
    let token = 'first-session';
    const reading = Promise.withResolvers();
    const body = Promise.withResolvers();
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        assert.equal(options.headers.Authorization, 'Bearer first-session');

        return {
            ok: true,
            blob() {
                reading.resolve();
                return body.promise;
            },
        };
    });
    const api = createWorkspaceApi(() => token, () => assert.fail('unexpected logout'));

    const request = api('/attachments/1/view', undefined, 'GET', true);
    await reading.promise;
    token = undefined;
    body.resolve(new Blob(['synthetic private attachment']));

    await assert.rejects(request, { name: 'AbortError' });
});

test('a pending workspace response cannot reach a newly signed-in account', async (t) => {
    let token = 'first-session';
    const reading = Promise.withResolvers();
    const body = Promise.withResolvers();
    t.mock.method(globalThis, 'fetch', async () => ({
        ok: true,
        json() {
            reading.resolve();
            return body.promise;
        },
    }));
    const api = createWorkspaceApi(() => token, () => assert.fail('unexpected logout'));

    const request = api('/me');
    await reading.promise;
    token = 'second-session';
    body.resolve({ success: true, data: { user: { id: 1 } } });

    await assert.rejects(request, { name: 'AbortError' });
});

test('a late forbidden response cannot end the current administrator session', async (t) => {
    let token = 'first-session';
    const pending = Promise.withResolvers();
    t.mock.method(globalThis, 'fetch', () => pending.promise);
    const api = createWorkspaceApi(() => token, () => assert.fail('unexpected logout'));

    const request = api('/admin/operations');
    token = 'second-session';
    pending.resolve(Response.json({ message: 'Forbidden' }, { status: 403 }));

    await assert.rejects(request, { name: 'AbortError' });
});

test('an unauthorized response for the current token ends that session', async (t) => {
    const ended = t.mock.fn();
    t.mock.method(globalThis, 'fetch', async () => Response.json({ message: 'Unauthenticated' }, { status: 401 }));
    const api = createWorkspaceApi(() => 'current-session', ended);

    await assert.rejects(api('/me'), { status: 401 });

    assert.equal(ended.mock.callCount(), 1);
});
