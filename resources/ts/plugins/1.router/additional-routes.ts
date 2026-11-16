import type { RouteRecordRaw } from 'vue-router/auto';

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'index',
    redirect: to => {
      const userData = useCookie<Record<string, unknown> | null | undefined>('userData');

      if (!userData.value) return { name: 'login', query: to.query };

      const homeRoute = String(userData.value?.homeRoute || '/parapheur');

      if (homeRoute.includes('/admin')) return { name: 'parapheur-admin' };
      if (homeRoute.includes('/dg')) return { name: 'parapheur-dg' };

      return { name: 'parapheur' };
    },
  },
];

/** Routes supplémentaires métier (plus de démos Vuexy). */
export const routes: RouteRecordRaw[] = [];
