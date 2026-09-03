const csrfToken =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

export async function apiRequest<T>(
    url: string,
    options: RequestInit = {},
): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers ?? {}),
        },
    });

    if (response.status === 401) {
        window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    }

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            payload?.message ??
            Object.values(payload?.errors ?? {}).flat()[0] ??
            'Запрос не выполнен.';

        throw new Error(String(message));
    }

    return payload as T;
}
