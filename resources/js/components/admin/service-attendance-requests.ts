import { type ServiceOccurrence } from './service-operation-types';

/**
 * Attendance is recorded while the operations sheet stays open, so these calls
 * deliberately bypass Inertia: a router visit re-renders the whole page and the
 * sheet visibly reloads after every tap. The endpoints already answer JSON with
 * the refreshed occurrence, which the caller can drop straight into state.
 */
type AttendanceResponse = {
    message: string;
    occurrence: ServiceOccurrence;
};

type ErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

function xsrfToken(): string {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

function firstErrorMessage(payload: ErrorPayload | null): string | undefined {
    const fieldError = Object.values(payload?.errors ?? {})
        .flat()
        .find((message): message is string => typeof message === 'string');

    return fieldError ?? payload?.message;
}

export async function recordAttendance(
    url: string,
    method: 'POST' | 'PATCH',
    body: Record<string, unknown> = {},
): Promise<AttendanceResponse> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = (await response.json().catch(() => null)) as (AttendanceResponse & ErrorPayload) | null;

    if (!response.ok) {
        throw new Error(firstErrorMessage(payload) ?? 'The request could not be completed.');
    }

    if (!payload?.occurrence) {
        throw new Error('The server returned an incomplete service record.');
    }

    return payload;
}
