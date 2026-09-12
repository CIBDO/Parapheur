import type { RouteRecordRaw } from 'vue-router/auto';

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'index',
    redirect: to => {
      const userData = useCookie<Record<string, unknown> | null | undefined>('userData');
      const userRole = String(userData.value?.role ?? '');

      if (!userData.value) return { name: 'login', query: to.query };

      if (userRole === 'Administrateur') return { name: 'parapheur-admin' };

      if (userRole === 'Directeur Général' || userRole === 'DGA') return { name: 'parapheur-dg' };

      return { name: 'parapheur' };
    },
  },
];

/** Routes supplémentaires métier (plus de démos Vuexy). */
export const routes: RouteRecordRaw[] = [];
