/**
 * Fake API Vuexy (MSW) — désactivé par défaut.
 * Activer uniquement en local : VITE_ENABLE_FAKE_API=true
 */
export default function () {
  if (!import.meta.env.DEV || import.meta.env.VITE_ENABLE_FAKE_API !== 'true')
    return

  void startFakeApi()
}

async function startFakeApi() {
  const { setupWorker } = await import('msw/browser')
  const { handlerAppBarSearch } = await import('@db/app-bar-search/index')
  const { handlerAppsAcademy } = await import('@db/apps/academy/index')
  const { handlerAppsCalendar } = await import('@db/apps/calendar/index')
  const { handlerAppsChat } = await import('@db/apps/chat/index')
  const { handlerAppsEcommerce } = await import('@db/apps/ecommerce/index')
  const { handlerAppsEmail } = await import('@db/apps/email/index')
  const { handlerAppsInvoice } = await import('@db/apps/invoice/index')
  const { handlerAppsKanban } = await import('@db/apps/kanban/index')
  const { handlerAppLogistics } = await import('@db/apps/logistics/index')
  const { handlerAppsPermission } = await import('@db/apps/permission/index')
  const { handlerAppsUsers } = await import('@db/apps/users/index')
  const { handlerDashboard } = await import('@db/dashboard/index')
  const { handlerPagesDatatable } = await import('@db/pages/datatable/index')
  const { handlerPagesFaq } = await import('@db/pages/faq/index')
  const { handlerPagesHelpCenter } = await import('@db/pages/help-center/index')
  const { handlerPagesProfile } = await import('@db/pages/profile/index')

  const worker = setupWorker(
    ...handlerAppsEcommerce,
    ...handlerAppsAcademy,
    ...handlerAppsInvoice,
    ...handlerAppsUsers,
    ...handlerAppsEmail,
    ...handlerAppsCalendar,
    ...handlerAppsChat,
    ...handlerAppsPermission,
    ...handlerPagesHelpCenter,
    ...handlerPagesProfile,
    ...handlerPagesFaq,
    ...handlerPagesDatatable,
    ...handlerAppBarSearch,
    ...handlerAppLogistics,
    ...handlerAppsKanban,
    ...handlerDashboard,
  )

  const workerUrl = `${import.meta.env.BASE_URL.replace(/build\/$/g, '') ?? '/'}mockServiceWorker.js`

  await worker.start({
    serviceWorker: {
      url: workerUrl,
    },
    onUnhandledRequest: 'bypass',
  })
}
