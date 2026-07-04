import { DEFAULT_REQUEST_ERROR_MESSAGE, getRequestErrorMessage } from './errors';

function getDefaultApiBaseUrl() {
  if (typeof window === 'undefined') {
    return '/api/v1';
  }

  return `${window.location.origin}/api/v1`;
}

function normalizeLocalApiBaseUrl(url) {
  if (typeof window === 'undefined') {
    return url;
  }

  try {
    const parsedUrl = new URL(url);
    const frontendHost = window.location.hostname;
    const localHosts = ['127.0.0.1', 'localhost'];

    if (localHosts.includes(frontendHost) && localHosts.includes(parsedUrl.hostname)) {
      parsedUrl.hostname = frontendHost;
    }

    return parsedUrl.toString().replace(/\/$/, '');
  } catch {
    return getDefaultApiBaseUrl();
  }
}

export const API_BASE_URL = normalizeLocalApiBaseUrl(
  import.meta.env.VITE_API_BASE_URL || getDefaultApiBaseUrl()
);
const SANCTUM_CSRF_URL = new URL('/sanctum/csrf-cookie', API_BASE_URL).toString();

function isUnsafeMethod(method) {
  return !['GET', 'HEAD', 'OPTIONS'].includes((method || 'GET').toUpperCase());
}

function getCookie(name) {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith(`${name}=`));

  if (!cookie) {
    return '';
  }

  return decodeURIComponent(cookie.slice(name.length + 1));
}

export function getXsrfToken() {
  return getCookie('XSRF-TOKEN');
}

function csrfHeader(options) {
  if (!isUnsafeMethod(options.method)) {
    return {};
  }

  const xsrfToken = getCookie('XSRF-TOKEN');

  return xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {};
}

async function sendRequest(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...csrfHeader(options),
      ...(options.headers || {}),
    },
  });

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const error = new Error(getRequestErrorMessage(
      { payload },
      { fallbackMessage: DEFAULT_REQUEST_ERROR_MESSAGE },
    ));

    error.status = response.status;
    error.payload = payload;

    throw error;
  }

  return payload;
}

export async function apiRequest(path, options = {}) {
  if (isUnsafeMethod(options.method) && !getCookie('XSRF-TOKEN')) {
    await refreshCsrfToken();
  }

  try {
    return await sendRequest(path, options);
  } catch (error) {
    if (error.status === 419 && isUnsafeMethod(options.method)) {
      await refreshCsrfToken();

      return sendRequest(path, options);
    }

    throw error;
  }
}

export async function refreshCsrfToken() {
  const response = await fetch(SANCTUM_CSRF_URL, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error('Não foi possível preparar a proteção CSRF.');
  }
}
