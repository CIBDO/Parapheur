import { formatBytes, storagePercent } from '@/utils/workspaceUi'

export function useWorkspaceHome() {
  const loading = ref(true)
  const workspace = ref<any>(null)
  const storage = ref<any>(null)
  const recent = ref<any[]>([])
  const favorites = ref<any[]>([])
  const folders = ref<any[]>([])

  async function load() {
    loading.value = true
    try {
      const res = await $api('/workspace/home')
      workspace.value = res.workspace || res
      storage.value = res.storage || null
      recent.value = res.recent || []
      favorites.value = res.favorites || []
      folders.value = res.folders || res.root_folders || []
    }
    finally {
      loading.value = false
    }
  }

  const storageLabel = computed(() => {
    if (!storage.value)
      return ''
    const used = storage.value.used_bytes ?? 0
    const quota = storage.value.quota_bytes ?? 0

    return `${formatBytes(used)} / ${formatBytes(quota)}`
  })

  const storagePct = computed(() => {
    if (!storage.value)
      return 0

    return storagePercent(storage.value.used_bytes ?? 0, storage.value.quota_bytes ?? 0)
  })

  return {
    loading,
    workspace,
    storage,
    recent,
    favorites,
    folders,
    storageLabel,
    storagePct,
    load,
  }
}

export function useWorkspaceExplorer(workspaceId: Ref<number | null>) {
  const loading = ref(false)
  const folderId = ref<number | null>(null)
  const breadcrumb = ref<any[]>([])
  const folders = ref<any[]>([])
  const documents = ref<any[]>([])
  const currentFolder = ref<any | null>(null)
  const viewMode = ref<'list' | 'grid'>('list')

  async function load() {
    if (!workspaceId.value)
      return
    loading.value = true
    try {
      const params = new URLSearchParams()
      if (folderId.value)
        params.set('folder_id', String(folderId.value))
      const qs = params.toString()
      const res = await $api(`/workspace/${workspaceId.value}/browse${qs ? `?${qs}` : ''}`)
      folders.value = res.folders || []
      documents.value = res.documents || []
      breadcrumb.value = res.breadcrumb || []
      currentFolder.value = res.current_folder || null
    }
    finally {
      loading.value = false
    }
  }

  async function openFolder(id: number | null) {
    folderId.value = id
    await load()
  }

  return {
    loading,
    folderId,
    breadcrumb,
    folders,
    documents,
    currentFolder,
    viewMode,
    load,
    openFolder,
  }
}
