export interface TaskItem {
  id: number
  reference: string
  title: string
  description?: string | null
  status: string
  priority: string
  due_at?: string | null
  progress?: number
  assignee?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
  structure?: { id: number; code: string; name: string } | null
  is_overdue?: boolean
}

export const useTasks = () => {
  const loading = ref(false)
  const items = ref<TaskItem[]>([])
  const meta = ref<Record<string, any>>({})
  const dashboard = ref<Record<string, number>>({})

  const list = async (params: Record<string, any> = {}) => {
    loading.value = true
    try {
      const res = await $api('/tasks', { query: params })
      items.value = res.data ?? res
      meta.value = {
        current_page: res.current_page,
        last_page: res.last_page,
        total: res.total,
      }
      return res
    }
    finally {
      loading.value = false
    }
  }

  const loadDashboard = async () => {
    dashboard.value = await $api('/tasks/dashboard')
    return dashboard.value
  }

  const get = async (id: number | string) => {
    return await $api(`/tasks/${id}`)
  }

  const create = async (body: Record<string, any>) => {
    return await $api('/tasks', { method: 'POST', body })
  }

  const action = async (id: number | string, path: string, body?: Record<string, any>) => {
    return await $api(`/tasks/${id}/${path}`, { method: 'POST', body })
  }

  const fetchKanban = async (params: Record<string, any> = {}) => {
    return await $api('/tasks/kanban', { query: params })
  }

  const fetchCalendar = async (from: string, to: string) => {
    return await $api('/tasks/calendar', { query: { from, to } })
  }

  const fetchReportOverview = async (days = 30) => {
    return await $api('/tasks/reports/overview', { query: { days } })
  }

  const fetchReportByStructure = async (days = 30) => {
    return await $api('/tasks/reports/by-structure', { query: { days } })
  }

  const fetchReportByPriority = async () => {
    return await $api('/tasks/reports/by-priority')
  }

  const fetchReportBySource = async (days = 30) => {
    return await $api('/tasks/reports/by-source', { query: { days } })
  }

  const fetchReportWorkload = async () => {
    return await $api('/tasks/reports/workload')
  }

  const fetchAudit = async (id: number | string) => {
    return await $api(`/tasks/${id}/audit`)
  }

  return {
    loading,
    items,
    meta,
    dashboard,
    list,
    loadDashboard,
    get,
    create,
    action,
    fetchKanban,
    fetchCalendar,
    fetchReportOverview,
    fetchReportByStructure,
    fetchReportByPriority,
    fetchReportBySource,
    fetchReportWorkload,
    fetchAudit,
  }
}
