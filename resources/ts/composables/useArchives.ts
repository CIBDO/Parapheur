import { useApi } from '@/composables/useApi'

export function useArchives() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function getJson<T = any>(url: string): Promise<T> {
    loading.value = true
    error.value = null
    try {
      const { data, error: err, response } = await useApi(url).get().json()
      if (err.value || (response.value && response.value.status >= 400)) {
        throw new Error(data.value?.message || 'Erreur API archives')
      }
      return data.value as T
    }
    catch (e: any) {
      error.value = e?.message || 'Erreur'
      throw e
    }
    finally {
      loading.value = false
    }
  }

  async function postJson<T = any>(url: string, body: Record<string, unknown> = {}): Promise<T> {
    loading.value = true
    error.value = null
    try {
      const { data, error: err, response } = await useApi(url).post(body).json()
      if (err.value || (response.value && response.value.status >= 400)) {
        throw new Error(data.value?.message || 'Erreur API archives')
      }
      return data.value as T
    }
    catch (e: any) {
      error.value = e?.message || 'Erreur'
      throw e
    }
    finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    getJson,
    postJson,
    fetchDashboard: () => getJson('/archives/dashboard'),
    fetchTransfers: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/transfers${suffix}`)
    },
    fetchRecords: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/records${suffix}`)
    },
    fetchFonds: () => getJson('/archives/fonds'),
    fetchNodes: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/classification-nodes${suffix}`)
    },
    createTransfer: (body: Record<string, unknown>) => postJson('/archives/transfers', body),
    proposeFromDocument: (body: Record<string, unknown>) => postJson('/archives/propose', body),
    digitizeBatch: async (form: FormData) => {
      loading.value = true
      error.value = null
      try {
        const { data, error: err, response } = await useApi('/archives/digitize/batch').post(form).json()
        if (err.value || (response.value && response.value.status >= 400))
          throw new Error(data.value?.message || 'Numérisation impossible')
        return data.value
      }
      catch (e: any) {
        error.value = e?.message || 'Erreur'
        throw e
      }
      finally {
        loading.value = false
      }
    },
    updateHybrid: (recordId: number, body: Record<string, unknown>) =>
      postJson(`/archives/records/${recordId}/hybrid`, body),
    restoreRecord: (recordId: number) => postJson(`/archives/records/${recordId}/restore`),
    restorePackage: (packageId: number) => postJson(`/archives/packages/${packageId}/restore`),
    submitTransfer: (id: number) => postJson(`/archives/transfers/${id}/submit`),
    controlTransfer: (id: number) => postJson(`/archives/transfers/${id}/control`),
    acceptTransfer: (id: number) => postJson(`/archives/transfers/${id}/accept`),
    rejectTransfer: (id: number, reason: string) => postJson(`/archives/transfers/${id}/reject`, { reason }),
    takeOverTransfer: (id: number) => postJson(`/archives/transfers/${id}/take-over`),
    fetchLocations: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/locations${suffix}`)
    },
    fetchOccupation: (structureId?: number) => {
      const suffix = structureId ? `?structure_id=${structureId}` : ''
      return getJson(`/archives/locations/occupation${suffix}`)
    },
    createLocation: (body: Record<string, unknown>) => postJson('/archives/locations', body),
    fetchBoxes: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/boxes${suffix}`)
    },
    createBox: (body: Record<string, unknown>) => postJson('/archives/boxes', body),
    assignBox: (id: number, locationId: number, force = false) =>
      postJson(`/archives/boxes/${id}/assign`, { location_id: locationId, force_capacity: force }),
    fetchMovements: () => getJson('/archives/movements'),
    fetchLoans: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/loans${suffix}`)
    },
    createLoan: (body: Record<string, unknown>) => postJson('/archives/loans', body),
    validateLoan: (id: number) => postJson(`/archives/loans/${id}/validate`),
    checkoutLoan: (id: number) => postJson(`/archives/loans/${id}/checkout`),
    returnLoan: (id: number) => postJson(`/archives/loans/${id}/return`),
    fetchInventories: () => getJson('/archives/inventories'),
    createInventory: (body: Record<string, unknown>) => postJson('/archives/inventories', body),
    startInventory: (id: number) => postJson(`/archives/inventories/${id}/start`),
    scanInventory: (id: number, code: string) => postJson(`/archives/inventories/${id}/scan`, { code }),
    closeInventory: (id: number) => postJson(`/archives/inventories/${id}/close`),
    fetchRetentionRules: () => getJson('/archives/retention-rules'),
    createRetentionRule: (body: Record<string, unknown>) => postJson('/archives/retention-rules', body),
    validateRetentionRule: (id: number) => postJson(`/archives/retention-rules/${id}/validate`),
    fetchRetentionDue: () => getJson('/archives/retention/due'),
    applyRetention: (recordId: number, ruleId: number) =>
      postJson(`/archives/records/${recordId}/retention`, { retention_rule_id: ruleId }),
    placeHold: (recordId: number, body: Record<string, unknown>) =>
      postJson(`/archives/records/${recordId}/holds`, body),
    liftHold: (holdId: number, notes?: string) =>
      postJson(`/archives/holds/${holdId}/lift`, notes ? { notes } : {}),
    fetchCommunications: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/communications${suffix}`)
    },
    createCommunication: (body: Record<string, unknown>) => postJson('/archives/communications', body),
    authorizeCommunication: (id: number, days = 30) =>
      postJson(`/archives/communications/${id}/authorize`, { days }),
    refuseCommunication: (id: number, reason: string) =>
      postJson(`/archives/communications/${id}/refuse`, { reason }),
    fetchDispositions: () => getJson('/archives/dispositions'),
    createDisposition: (body: Record<string, unknown>) => postJson('/archives/dispositions', body),
    authorizeDisposition: (id: number, body: Record<string, unknown> = {}) =>
      postJson(`/archives/dispositions/${id}/authorize`, body),
    refuseDisposition: (id: number, reason: string) =>
      postJson(`/archives/dispositions/${id}/refuse`, { reason }),
    executeDisposition: (id: number) => postJson(`/archives/dispositions/${id}/execute`),
    fetchResponsibleReport: () => getJson('/archives/reports/responsible'),
    fetchDgReport: () => getJson('/archives/reports/dg'),
    exportMetadata: () => getJson('/archives/reports/export-metadata'),
    fetchSupervision: () => getJson('/archives/preservation/supervision'),
    fetchPackages: (params: Record<string, string | number | boolean> = {}) => {
      const qs = new URLSearchParams()
      Object.entries(params).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined)
          qs.set(k, String(v))
      })
      const suffix = qs.toString() ? `?${qs}` : ''
      return getJson(`/archives/packages${suffix}`)
    },
    buildAip: (recordId: number, replicate = true) =>
      postJson(`/archives/records/${recordId}/aip`, { replicate }),
    buildDip: (recordId: number) => postJson(`/archives/records/${recordId}/dip`),
    verifyRecord: (recordId: number) => postJson(`/archives/records/${recordId}/verify-integrity`),
    verifyBatch: (limit = 50) => postJson('/archives/integrity/batch', { limit }),
    replicatePackage: (id: number) => postJson(`/archives/packages/${id}/replicate`),
  }
}

export const transferStatusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  soumis: 'Soumis',
  en_controle: 'En contrôle',
  rejete: 'Rejeté',
  accepte: 'Accepté',
  pris_en_charge: 'Pris en charge',
  conserve: 'Conservé',
}

export const transferStatusColor = (status: string) => {
  switch (status) {
    case 'conserve': return 'success'
    case 'soumis':
    case 'en_controle': return 'warning'
    case 'rejete': return 'error'
    case 'accepte':
    case 'pris_en_charge': return 'info'
    default: return 'secondary'
  }
}

export const recordStatusLabels: Record<string, string> = {
  en_versement: 'En versement',
  conserve: 'Conservé',
  communique: 'Communiqué',
  elimine: 'Éliminé',
  transfere: 'Transféré',
}

export function formatDateFr(value?: string | null, withTime = false) {
  if (!value)
    return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime()))
    return value
  return withTime
    ? d.toLocaleString('fr-FR')
    : d.toLocaleDateString('fr-FR')
}
