import { getToken, clearToken } from '@/lib/tokenStorage';
import type { ValidationErrors } from '@/types/api';

// Same address as the SPA by default: the dev server (and the production web server) forwards /api to Laravel.
const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';

type Body = Record<string, unknown> | unknown[] | FormData;

export interface RequestOptions extends Omit<RequestInit, 'body' | 'headers'> {
  body?: Body;
  headers?: Record<string, string>;
}

interface ErrorBody {
  message?: string;
  errors?: ValidationErrors;
  /** A machine-readable cause some endpoints add (e.g. Bitrix24 sign-in). */
  reason?: string;
}

export class ApiError extends Error {
  status: number;
  data: ErrorBody | null;
  errors: ValidationErrors;
  isNetworkError: boolean;

  constructor(message: string, status: number, data: ErrorBody | null = null, isNetworkError = false) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
    this.errors = data?.errors || {};
    this.isNetworkError = isNetworkError;
  }
}

export function isAbortError(err: unknown): boolean {
  return err instanceof DOMException && err.name === 'AbortError';
}

/** Best message to show for any caught error. */
export function errorMessage(err: unknown, fallback: string): string {
  if (err instanceof ApiError) return err.data?.message || err.message || fallback;
  if (err instanceof Error) return err.message || fallback;
  return fallback;
}

async function request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
  const url = `${BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

  const token = getToken();

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    ...(options.headers || {}),
  };

  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const { body, ...rest } = options;
  const config: RequestInit = {
    ...rest,
    method: options.method || 'GET',
    headers,
  };

  if (body !== undefined) {
    config.body = body instanceof FormData ? body : JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(url, config);
  } catch (networkError) {
    if (isAbortError(networkError)) {
      throw networkError;
    }
    console.error('Fetch error:', networkError);
    throw new ApiError('Could not connect to the server. Please check your internet connection.', 0, null, true);
  }

  if (response.status === 204) {
    return { success: true, data: null } as T;
  }

  let data: unknown = null;
  const contentType = response.headers.get('content-type');
  if (contentType && contentType.includes('application/json')) {
    try {
      data = await response.json();
    } catch {
      data = null;
    }
  }

  if (!response.ok) {
    const errorBody = data as ErrorBody | null;
    const error = new ApiError(
      errorBody?.message || 'An error occurred while processing the request.',
      response.status,
      errorBody
    );

    switch (response.status) {
      case 401:
        clearToken();
        window.dispatchEvent(new Event('auth:unauthorized'));
        break;

      case 403:
        error.message = errorBody?.message || 'You are not authorized to perform this action.';
        break;

      case 404:
        error.message = errorBody?.message || 'The requested resource was not found.';
        break;

      case 422:
        error.message = errorBody?.message || 'Please ensure that the data entered is correct.';
        break;

      case 500:
        error.message = 'An internal server error occurred. Please try again later.';
        break;

      default:
        break;
    }

    throw error;
  }

  return data as T;
}

/**
 * A file the API streams (e.g. a task attachment). Downloads need the
 * Bearer token, so a plain <a href> can't fetch them.
 */
async function requestBlob(endpoint: string, options: RequestOptions = {}): Promise<Blob> {
  const url = `${BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
  const token = getToken();

  let response: Response;
  try {
    response = await fetch(url, {
      signal: options.signal,
      headers: { Accept: 'application/json, */*', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    });
  } catch (networkError) {
    if (isAbortError(networkError)) throw networkError;
    throw new ApiError('Could not connect to the server. Please check your internet connection.', 0, null, true);
  }

  if (!response.ok) {
    let errorBody: ErrorBody | null = null;
    if (response.headers.get('content-type')?.includes('application/json')) {
      errorBody = (await response.json().catch(() => null)) as ErrorBody | null;
    }
    throw new ApiError(errorBody?.message || 'The file could not be downloaded.', response.status, errorBody);
  }

  return response.blob();
}

const apiClient = {
  get: <T>(endpoint: string, options: RequestOptions = {}) => request<T>(endpoint, { ...options, method: 'GET' }),
  post: <T>(endpoint: string, body?: Body, options: RequestOptions = {}) =>
    request<T>(endpoint, { ...options, method: 'POST', body }),
  put: <T>(endpoint: string, body?: Body, options: RequestOptions = {}) =>
    request<T>(endpoint, { ...options, method: 'PUT', body }),
  patch: <T>(endpoint: string, body?: Body, options: RequestOptions = {}) =>
    request<T>(endpoint, { ...options, method: 'PATCH', body }),
  delete: <T = unknown>(endpoint: string, options: RequestOptions = {}) =>
    request<T>(endpoint, { ...options, method: 'DELETE' }),
  blob: requestBlob,
};

export default apiClient;
export { BASE_URL, request };
