import { createFetch } from '@vueuse/core';
import { destr } from 'destr';

function readCookie(name: string): string | null {
  if (typeof document === 'undefined')
    return null;

  const match = document.cookie
    .split('; ')
    .find(row => row.startsWith(`${name}=`));

  return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null;
}

export const useApi = createFetch({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '/api',
  fetchOptions: {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      const method = String(options.method || 'GET').toUpperCase();

      if (!['GET', 'HEAD', 'OPTIONS', 'TRACE'].includes(method)) {
        if (!readCookie('XSRF-TOKEN')) {
          await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
        }

        const xsrfToken = readCookie('XSRF-TOKEN');
        if (xsrfToken) {
          options.headers = {
            ...options.headers,
            'X-XSRF-TOKEN': xsrfToken,
          };
        }
      }

      const accessToken = useCookie('accessToken').value;

      if (accessToken) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${accessToken}`,
        };
      }

      return { options };
    },
    afterFetch(ctx) {
      const { data, response } = ctx;

      // Parse data if it's JSON

      let parsedData = null;
      try {
        parsedData = destr(data);
      } catch (error) {
        console.error(error);
      }

      return { data: parsedData, response };
    },
  },
});
