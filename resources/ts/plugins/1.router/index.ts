import { setupLayouts } from 'virtual:meta-layouts';
import type { App } from 'vue';

import type { RouteRecordRaw } from 'vue-router/auto';

import { createRouter, createWebHistory } from 'vue-router/auto';

import { redirects, routes } from './additional-routes';
import { setupGuards } from './guards';

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children?.length) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i]);

    // Parent with its own page component (ex: foo.vue + foo/[id].vue) must still get a layout.
    if (route.component || (route as RouteRecordRaw & { components?: unknown }).components)
      return setupLayouts([route])[0];

    return route;
  }

  return setupLayouts([route])[0];
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash) return { el: to.hash, behavior: 'smooth', top: 60 };

    return { top: 0 };
  },
  extendRoutes: pages => [...redirects, ...[...pages, ...routes].map(route => recursiveLayouts(route))],
});

setupGuards(router);

export { router };

export default function (app: App) {
  app.use(router);
}
