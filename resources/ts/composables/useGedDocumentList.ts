import {
  confidentialityLabels,
  formatDateFr,
  labelOf,
  originLabels,
  priorityColor,
  priorityLabels,
  statusColor,
  statusLabels,
} from '@/utils/gedUi'

export interface GedDocRow {
  id: number
  reference: string
  dossier_number?: string | null
  object: string
  title?: string | null
  status: string
  priority: string
  confidentiality?: string
  origin?: string
  document_date?: string
  current_version?: number
  structure?: { code: string; name: string }
  type?: { name: string }
  category?: { name: string }
  author?: { name: string }
  classification_node?: { name: string; path?: string }
  latest_version?: { version_number: number; is_official?: boolean }
  tags?: Array<{ id: number; name: string }>
}

export function useGedDocumentList(defaultScope?: string) {
  const router = useRouter()
  const loading = ref(false)
  const items = ref<GedDocRow[]>([])
  const total = ref(0)
  const page = ref(1)
  const perPage = ref(15)

  const filters = ref({
    q: '',
    document_type_id: null as number | null,
    category_id: null as number | null,
    structure_id: null as number | null,
    status: null as string | null,
    confidentiality: null as string | null,
    origin: null as string | null,
    classification_node_id: null as number | null,
    archive_status: null as string | null,
    scope: defaultScope || null as string | null,
    document_date_from: '',
    document_date_to: '',
    tag: '',
  })

  const load = async () => {
    loading.value = true
    try {
      const params: Record<string, string | number> = {
        page: page.value,
        per_page: perPage.value,
      }
      Object.entries(filters.value).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '')
          params[k] = v as string | number
      })

      const res = await $api('/ged/documents', { query: params })
      items.value = res.data || []
      total.value = res.total || 0
    }
    finally {
      loading.value = false
    }
  }

  const openDoc = (id: number) => {
    router.push({ name: 'ged-id', params: { id: String(id) } })
  }

  const headers = [
    { title: 'Référence', key: 'reference' },
    { title: 'Titre / Objet', key: 'object' },
    { title: 'Type', key: 'type' },
    { title: 'Structure', key: 'structure' },
    { title: 'Statut', key: 'status' },
    { title: 'Confidentialité', key: 'confidentiality' },
    { title: 'Date', key: 'document_date' },
    { title: '', key: 'actions', sortable: false },
  ]

  return {
    loading,
    items,
    total,
    page,
    perPage,
    filters,
    headers,
    load,
    openDoc,
    labelOf,
    statusLabels,
    statusColor,
    priorityLabels,
    priorityColor,
    confidentialityLabels,
    originLabels,
    formatDateFr,
  }
}
