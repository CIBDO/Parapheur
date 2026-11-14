export const useProductions = () => {
  const loading = ref(false)
  const items = ref<any[]>([])
  const meta = ref<Record<string, any>>({})
  const dashboard = ref<Record<string, number>>({})

  const listOccurrences = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const res = await $api('/productions/occurrences', { query: params })
      items.value = res.data ?? res
      meta.value = {
        current_page: res.current_page,
        last_page: res.last_page,
        total: res.total ?? items.value.length,
        per_page: res.per_page,
      }
      return res
    }
    finally {
      loading.value = false
    }
  }

  const listObligations = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const res = await $api('/productions/obligations', { query: params })
      items.value = res.data ?? res
      meta.value = {
        current_page: res.current_page,
        last_page: res.last_page,
        total: res.total ?? items.value.length,
        per_page: res.per_page,
      }
      return res
    }
    finally {
      loading.value = false
    }
  }

  const loadMyDashboard = async () => {
    dashboard.value = await $api('/productions/dashboard/my-work')
    return dashboard.value
  }

  const loadDirectionDashboard = async () => {
    dashboard.value = await $api('/productions/dashboard/direction')
    return dashboard.value
  }

  const getOccurrence = async (id: number | string) => {
    return await $api(`/productions/occurrences/${id}`)
  }

  const getObligation = async (id: number | string) => {
    return await $api(`/productions/obligations/${id}`)
  }

  const updateObligation = async (id: number | string, body: Record<string, any>) => {
    return await $api(`/productions/obligations/${id}`, { method: 'PUT', body })
  }

  const setObligationActive = async (id: number | string, isActive: boolean) => {
    return await $api(`/productions/obligations/${id}/active`, {
      method: 'POST',
      body: { is_active: isActive },
    })
  }

  const suspendObligation = async (id: number | string, body: Record<string, any>) => {
    return await $api(`/productions/obligations/${id}/suspend`, { method: 'POST', body })
  }

  const occurrenceAction = async (id: number | string, path: string, body?: Record<string, any>) => {
    return await $api(`/productions/occurrences/${id}/${path}`, { method: 'POST', body })
  }

  return {
    loading,
    items,
    meta,
    dashboard,
    listOccurrences,
    listObligations,
    loadMyDashboard,
    loadDirectionDashboard,
    getOccurrence,
    getObligation,
    updateObligation,
    setObligationActive,
    suspendObligation,
    occurrenceAction,
  }
}
