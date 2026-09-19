import { ref } from 'vue'
import { $api } from '@/utils/api'
import { listItems } from '@/utils/ticketingUi'

export interface Ticket {
  id: number
  number: string
  title: string
  description?: string | null
  status: string
  status_label?: string
  requester?: { id: number; name: string; email?: string } | null
  assignee?: { id: number; name: string; email?: string } | null
  team?: { id: number; code: string; name: string } | null
  priority?: { id: number; code: string; name: string; level?: number } | null
  impact?: { id: number; code: string; name: string } | null
  urgency?: { id: number; code: string; name: string } | null
  channel?: { id: number; code: string; name: string } | null
  category?: { id: number; code: string; name: string } | null
  type?: { id: number; code: string; name: string } | null
  service_item?: { id: number; code: string; name: string } | null
  sla?: any
  attachments?: any[]
  satisfaction?: any
  custom_fields?: Record<string, any> | null
  resolution_summary?: string | null
  created_at?: string
  updated_at?: string
  resolved_at?: string | null
  closed_at?: string | null
  [key: string]: any
}

export function useTicketing() {
  const ticket = ref<Ticket | null>(null)
  const tickets = ref<Ticket[]>([])
  const meta = ref<Record<string, any> | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchMeta() {
    const response = await $api('/ticketing/meta')
    meta.value = response

    return response
  }

  async function fetchTickets(params: Record<string, any> = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/ticketing/tickets', { query: params })
      tickets.value = listItems(response)

      return response
    }
    catch (e: any) {
      error.value = e.message
      throw e
    }
    finally {
      loading.value = false
    }
  }

  async function searchTickets(params: Record<string, any> = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/ticketing/tickets/search', { query: params })
      tickets.value = listItems(response)

      return response
    }
    catch (e: any) {
      error.value = e.message
      throw e
    }
    finally {
      loading.value = false
    }
  }

  async function fetchTicket(id: number) {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/ticketing/tickets/${id}`)
      ticket.value = response

      return response
    }
    catch (e: any) {
      error.value = e.message
      throw e
    }
    finally {
      loading.value = false
    }
  }

  async function createTicket(data: Record<string, any>) {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/ticketing/tickets', {
        method: 'POST',
        body: data,
      })
      ticket.value = response

      return response
    }
    catch (e: any) {
      error.value = e.message
      throw e
    }
    finally {
      loading.value = false
    }
  }

  async function duplicateCheck(data: { title: string; exclude_id?: number; requester_id?: number }) {
    return $api('/ticketing/tickets/duplicate-check', {
      method: 'POST',
      body: data,
    })
  }

  async function fetchKanban(teamId: number) {
    return $api('/ticketing/tickets/kanban', {
      query: { team_id: teamId },
    })
  }

  async function fetchServiceCatalog() {
    return $api('/ticketing/service-catalog')
  }

  async function fetchServiceForm(serviceItemId: number) {
    return $api(`/ticketing/service-catalog/${serviceItemId}/form`)
  }

  async function takeCharge(id: number) {
    return $api(`/ticketing/tickets/${id}/take-charge`, { method: 'POST', body: {} })
  }

  async function assign(id: number, data: { support_team_id?: number | null; assignee_id?: number | null; comment?: string }) {
    return $api(`/ticketing/tickets/${id}/assign`, { method: 'POST', body: data })
  }

  async function resolve(id: number, data: { summary: string; await_validation?: boolean }) {
    return $api(`/ticketing/tickets/${id}/resolve`, { method: 'POST', body: data })
  }

  async function close(id: number, data: { comment?: string } = {}) {
    return $api(`/ticketing/tickets/${id}/close`, { method: 'POST', body: data })
  }

  async function reopen(id: number, data: { reason: string }) {
    return $api(`/ticketing/tickets/${id}/reopen`, { method: 'POST', body: data })
  }

  async function wait(id: number, data: { status: string; comment?: string }) {
    return $api(`/ticketing/tickets/${id}/wait`, { method: 'POST', body: data })
  }

  async function escalate(id: number, data: { to_team_id?: number | null; to_user_id?: number | null; reason?: string }) {
    return $api(`/ticketing/tickets/${id}/escalate`, { method: 'POST', body: data })
  }

  async function cancel(id: number, data: { reason: string }) {
    return $api(`/ticketing/tickets/${id}/cancel`, { method: 'POST', body: data })
  }

  async function changeStatus(id: number, data: { status: string; comment?: string }) {
    return $api(`/ticketing/tickets/${id}/status`, { method: 'PATCH', body: data })
  }

  async function addComment(id: number, data: { body: string; is_internal?: boolean }) {
    return $api(`/ticketing/tickets/${id}/comments`, { method: 'POST', body: data })
  }

  async function fetchComments(id: number) {
    return $api(`/ticketing/tickets/${id}/comments`)
  }

  async function fetchTimeline(id: number) {
    return $api(`/ticketing/tickets/${id}/timeline`)
  }

  async function uploadAttachment(id: number, file: File) {
    const body = new FormData()
    body.append('file', file)

    return $api(`/ticketing/tickets/${id}/attachments`, {
      method: 'POST',
      body,
    })
  }

  async function fetchWorklogs(id: number) {
    return $api(`/ticketing/tickets/${id}/worklogs`)
  }

  async function addWorklog(id: number, data: { minutes: number; note?: string; worked_at?: string }) {
    return $api(`/ticketing/tickets/${id}/worklogs`, { method: 'POST', body: data })
  }

  async function submitSatisfaction(id: number, data: { score: number; comment?: string }) {
    return $api(`/ticketing/tickets/${id}/satisfaction`, { method: 'POST', body: data })
  }

  async function fetchDashboardRequester() {
    return $api('/ticketing/dashboard/requester')
  }

  async function fetchDashboardAgent() {
    return $api('/ticketing/dashboard/agent')
  }

  async function fetchDashboardManagement() {
    return $api('/ticketing/dashboard/management')
  }

  async function fetchReportVolume(params: Record<string, any> = {}) {
    return $api('/ticketing/reports/volume', { query: params })
  }

  async function fetchReportSla(params: Record<string, any> = {}) {
    return $api('/ticketing/reports/sla', { query: params })
  }

  async function adminList(resource: string) {
    return $api(`/ticketing/admin/${resource}`)
  }

  async function adminStore(resource: string, data: Record<string, any>) {
    return $api(`/ticketing/admin/${resource}`, { method: 'POST', body: data })
  }

  return {
    ticket,
    tickets,
    meta,
    loading,
    error,
    fetchMeta,
    fetchTickets,
    searchTickets,
    fetchTicket,
    createTicket,
    duplicateCheck,
    fetchKanban,
    fetchServiceCatalog,
    fetchServiceForm,
    takeCharge,
    assign,
    resolve,
    close,
    reopen,
    wait,
    escalate,
    cancel,
    changeStatus,
    addComment,
    fetchComments,
    fetchTimeline,
    uploadAttachment,
    fetchWorklogs,
    addWorklog,
    submitSatisfaction,
    fetchDashboardRequester,
    fetchDashboardAgent,
    fetchDashboardManagement,
    fetchReportVolume,
    fetchReportSla,
    adminList,
    adminStore,
  }
}
