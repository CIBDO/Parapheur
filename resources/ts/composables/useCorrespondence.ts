import { ref } from 'vue'
import { $api } from '@/utils/api'

export interface Correspondence {
  id: number
  uuid: string
  direction: 'entrant' | 'sortant' | 'interne'
  medium: 'physique' | 'electronique' | 'hybride'
  status: string
  arrival_number: string | null
  departure_number: string | null
  subject: string
  summary: string | null
  observations: string | null
  external_reference: string | null
  priority: string | null
  confidentiality: string | null
  piece_count: number
  requires_reply: boolean
  is_registered: boolean
  received_at: string | null
  correspondence_date: string | null
  registered_at: string | null
  due_date: string | null
  document?: any
  structure?: any
  channel?: any
  category?: any
  parties?: any[]
  assignments?: any[]
  events?: any[]
  links?: { outgoing: any[]; incoming: any[] }
  created_at: string
  updated_at: string
}

export function useCorrespondence() {
  const correspondence = ref<Correspondence | null>(null)
  const correspondences = ref<Correspondence[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchCorrespondences(params: Record<string, any> = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/mail/correspondences', { query: params })
      correspondences.value = response.data ?? response
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

  async function fetchCorrespondence(id: number) {
    loading.value = true
    error.value = null
    try {
      const response = await $api(`/mail/correspondences/${id}`)
      correspondence.value = response
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

  async function createCorrespondence(data: FormData | Record<string, any>) {
    loading.value = true
    error.value = null
    try {
      const response = await $api('/mail/correspondences', {
        method: 'POST',
        body: data,
      })
      correspondence.value = response
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

  async function registerIncoming(data: FormData | Record<string, any>) {
    return $api('/mail/registers/incoming', { method: 'POST', body: data })
  }

  async function assign(id: number, assignments: any[]) {
    return $api(`/mail/correspondences/${id}/assignments`, {
      method: 'POST',
      body: { assignments },
    })
  }

  async function takeCharge(id: number, assignmentId: number) {
    return $api(`/mail/correspondences/${id}/assignments/take-charge`, {
      method: 'POST',
      body: { assignment_id: assignmentId },
    })
  }

  async function reply(id: number, data: Record<string, any> = {}) {
    return $api(`/mail/correspondences/${id}/reply`, { method: 'POST', body: data })
  }

  async function dispatch(id: number, data: Record<string, any>) {
    return $api(`/mail/correspondences/${id}/dispatch`, { method: 'POST', body: data })
  }

  async function fetchDashboard() {
    return $api('/mail/dashboard/order-office')
  }

  async function fetchMeta() {
    const [channels, categories, qualifications, actions] = await Promise.all([
      $api('/mail/meta/channels'),
      $api('/mail/meta/categories'),
      $api('/mail/meta/qualifications'),
      $api('/mail/meta/actions'),
    ])
    return { channels, categories, qualifications, actions }
  }

  async function requestComplement(id: number, assignmentId: number, observation: string) {
    return $api(`/mail/correspondences/${id}/assignments/request-complement`, {
      method: 'POST',
      body: { assignment_id: assignmentId, observation },
    })
  }

  async function syncParties(id: number, parties: any[]) {
    return $api(`/mail/correspondences/${id}/parties`, {
      method: 'POST',
      body: { parties },
    })
  }

  async function createReminder(id: number, data: Record<string, any>) {
    return $api(`/mail/correspondences/${id}/reminders`, {
      method: 'POST',
      body: data,
    })
  }

  async function listReminders(id: number) {
    return $api(`/mail/correspondences/${id}/reminders`)
  }

  async function attachSignedVersion(id: number, formData: FormData) {
    return $api(`/mail/correspondences/${id}/attach-signed-version`, {
      method: 'POST',
      body: formData,
    })
  }

  async function printDocument(id: number, data: { reason: string; copies?: number; is_reprint?: boolean }) {
    return $api(`/mail/correspondences/${id}/print`, {
      method: 'POST',
      body: data,
    })
  }

  async function createCirculationSheet(id: number, data: { generate?: boolean } = {}) {
    return $api(`/mail/correspondences/${id}/circulation-sheet`, {
      method: 'POST',
      body: data,
    })
  }

  async function fetchDashboardDg() {
    return $api('/mail/dashboard/dg')
  }

  async function fetchDashboardDirection() {
    return $api('/mail/dashboard/direction')
  }

  // Admin CRUD helpers
  async function adminList(type: 'channels' | 'categories' | 'qualifications' | 'actions') {
    return $api(`/mail/admin/${type}`)
  }

  async function adminStore(type: 'channels' | 'categories' | 'qualifications' | 'actions', data: Record<string, any>) {
    return $api(`/mail/admin/${type}`, {
      method: 'POST',
      body: data,
    })
  }

  async function adminUpdate(type: 'channels' | 'categories' | 'qualifications' | 'actions', id: number, data: Record<string, any>) {
    return $api(`/mail/admin/${type}/${id}`, {
      method: 'PUT',
      body: data,
    })
  }

  async function adminToggle(type: 'channels' | 'categories' | 'qualifications' | 'actions', id: number) {
    return $api(`/mail/admin/${type}/${id}/toggle`, {
      method: 'POST',
    })
  }

  async function adminDelete(type: 'channels' | 'categories' | 'qualifications' | 'actions', id: number) {
    return $api(`/mail/admin/${type}/${id}`, {
      method: 'DELETE',
    })
  }

  return {
    correspondence,
    correspondences,
    loading,
    error,
    fetchCorrespondences,
    fetchCorrespondence,
    createCorrespondence,
    registerIncoming,
    assign,
    takeCharge,
    reply,
    dispatch,
    fetchDashboard,
    fetchDashboardDg,
    fetchDashboardDirection,
    fetchMeta,
    requestComplement,
    syncParties,
    createReminder,
    listReminders,
    attachSignedVersion,
    printDocument,
    createCirculationSheet,
    adminList,
    adminStore,
    adminUpdate,
    adminToggle,
    adminDelete,
  }
}
