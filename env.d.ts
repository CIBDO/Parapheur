/// <reference types="vite/client" />

declare module '*.png' {
  const src: string;
  export default src;
}

declare module '*.jpg' {
  const src: string;
  export default src;
}

declare module '*.svg' {
  const src: string;
  export default src;
}

import 'vue-router';
declare module 'vue-router' {
  interface RouteMeta {
    action?: string;
    subject?: string;
    layoutWrapperClasses?: string;
    navActiveLink?: RouteLocationRaw;
    layout?: 'blank' | 'default';
    unauthenticatedOnly?: boolean;
    public?: boolean;
  }
}
