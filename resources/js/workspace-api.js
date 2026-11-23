export function createWorkspaceApi(getToken, onUnauthorized) {
    return async function api(path, body, method = body === undefined ? 'GET' : 'POST', download = false) {
        const headers = { Accept: 'application/json' };
        const requestToken = getToken();
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
        if (download && response.ok) {
            return response.blob();
        }

        const result = await response.json().catch(() => null);
        if (!response.ok || !result?.success) {
            if (response.status === 401 && requestToken && requestToken === getToken()) {
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
