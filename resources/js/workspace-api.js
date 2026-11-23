export function createWorkspaceApi(getToken, onUnauthorized) {
    return async function api(path, body, method = body === undefined ? 'GET' : 'POST', download = false) {
        const headers = { Accept: 'application/json' };
        const requestToken = getToken();
        const assertCurrentSession = () => {
            if (requestToken && requestToken !== getToken()) {
                throw new DOMException('Bu istek önceki oturuma ait.', 'AbortError');
            }
        };
        if (requestToken) {
            headers.Authorization = 'Bearer ' + requestToken;
        }
        const multipart = body instanceof FormData;
        if (body !== undefined && !multipart) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch('/api' + path, {
            method,
            headers,
            body: multipart || body === undefined ? body : JSON.stringify(body),
            cache: 'no-store',
        });
        assertCurrentSession();
        if (download && response.ok) {
            const blob = await response.blob();
            assertCurrentSession();
            return blob;
        }

        const result = await response.json().catch(() => null);
        assertCurrentSession();
        if (!response.ok || !result?.success) {
            if (response.status === 401 && requestToken) {
                onUnauthorized();
            }

            const error = new Error(
                Object.values(result?.errors ?? {}).flat()[0]
                    ?? result?.message
                    ?? 'İşlem tamamlanamadı. Lütfen tekrar deneyin.',
            );
            error.status = response.status;
            throw error;
        }

        return result.data;
    };
}
