<script setup lang="ts">
/**
 * ONLYOFFICE DocsAPI — le conteneur iframe est créé hors du VDOM Vue
 * (replaceWith) pour éviter que les re-renders effacent l’éditeur.
 */
const props = defineProps<{
  documentId: number | string
  versionId?: number | null
}>()

const emit = defineEmits<{
  saved: []
  reload: []
  error: [message: string]
}>()

const loading = ref(true)
const documentReady = ref(false)
const error = ref('')
const compareDialog = ref(false)
const compareVersions = ref<Array<{ title: string; value: number }>>([])
const selectedCompareVersion = ref<number | null>(null)

const EDITOR_HEIGHT_PX = 720
const READY_TIMEOUT_MS = 20000
const SCRIPT_TIMEOUT_MS = 12000

let editorId = `oo-editor-${props.documentId}-${Math.random().toString(36).slice(2, 9)}`
const vueAnchor = ref<HTMLElement | null>(null)

const statusLabel = computed(() => {
  if (error.value)
    return { text: 'Erreur', color: 'warning' }
  if (documentReady.value)
    return { text: 'Document ouvert', color: 'success' }
  if (loading.value)
    return { text: 'Chargement…', color: 'primary' }

  return { text: 'Initialisé', color: 'info' }
})

let editorInstance: any = null
let hostEl: HTMLDivElement | null = null
let readyTimer: ReturnType<typeof setTimeout> | null = null
let initGeneration = 0
let unmounted = false

declare global {
  interface Window {
    DocsAPI?: {
      DocEditor: new (id: string, config: Record<string, unknown>) => {
        destroyEditor: () => void
        refreshHistory: (data: unknown) => void
        setHistoryData: (data: unknown) => void
        setRevisedFile: (data: unknown) => void
      }
    }
  }
}

const ooErrorMessage = (data: unknown): string => {
  const map: Record<string, string> = {
    '-1': 'Erreur inconnue ONLYOFFICE',
    '-2': 'Délai de conversion dépassé',
    '-3': 'Erreur de conversion du document (format non supporté ? ex. .xlsm)',
    '-4': 'Document Server ne peut pas télécharger le fichier depuis Laravel (ONLYOFFICE_APP_URL / réseau / URL signée)',
    '-5': 'Mot de passe document incorrect',
    '-6': 'Erreur base ONLYOFFICE',
    '-7': 'Erreur force-save',
    '-8': 'Jeton JWT invalide (secret Laravel ≠ conteneur)',
  }

  let code: unknown = data
  let description = ''

  if (data && typeof data === 'object') {
    const obj = data as Record<string, unknown>
    code = obj.errorCode ?? obj.code ?? obj.error ?? obj.data
    description = String(obj.errorDescription ?? obj.message ?? obj.description ?? '')
  }

  const key = String(code ?? '')
  if (map[key])
    return description ? `${map[key]} — ${description}` : map[key]

  if (description)
    return `Erreur éditeur ONLYOFFICE (${key || '?'}) — ${description}`

  if (key && key !== '[object Object]')
    return `Erreur éditeur ONLYOFFICE (${key})`

  return 'Erreur éditeur ONLYOFFICE (détail indisponible — voir console navigateur / logs Docs)'
}

const waitForDocsApi = (timeoutMs: number): Promise<void> => new Promise((resolve, reject) => {
  const started = Date.now()
  const tick = () => {
    if (window.DocsAPI?.DocEditor) {
      resolve()

      return
    }
    if (Date.now() - started >= timeoutMs) {
      reject(new Error('DocsAPI indisponible après chargement de api.js'))

      return
    }
    window.setTimeout(tick, 50)
  }
  tick()
})

const loadScript = async (src: string): Promise<void> => {
  if (window.DocsAPI?.DocEditor)
    return

  const existing = document.querySelector<HTMLScriptElement>('script[data-onlyoffice-api="1"]')

  if (existing) {
    // Script déjà présent mais load passé / DocsAPI pas encore prêt → poller.
    await waitForDocsApi(SCRIPT_TIMEOUT_MS)

    return
  }

  await new Promise<void>((resolve, reject) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    script.dataset.onlyofficeApi = '1'

    const timer = window.setTimeout(() => {
      reject(new Error(`Délai dépassé en chargeant api.js (${SCRIPT_TIMEOUT_MS / 1000}s) — vérifiez ONLYOFFICE_URL`))
    }, SCRIPT_TIMEOUT_MS)

    script.onload = () => {
      window.clearTimeout(timer)
      resolve()
    }
    script.onerror = () => {
      window.clearTimeout(timer)
      script.remove()
      reject(new Error('Chargement api.js ONLYOFFICE impossible — Document Server injoignable'))
    }
    document.head.appendChild(script)
  })

  await waitForDocsApi(SCRIPT_TIMEOUT_MS)
}

const clearReadyTimer = () => {
  if (readyTimer) {
    clearTimeout(readyTimer)
    readyTimer = null
  }
}

const buildHostEl = (): HTMLDivElement => {
  const el = document.createElement('div')
  el.id = editorId
  el.className = 'onlyoffice-editor-host border rounded'
  el.style.width = '100%'
  el.style.height = `${EDITOR_HEIGHT_PX}px`
  el.style.minHeight = `${EDITOR_HEIGHT_PX}px`
  el.style.overflow = 'hidden'
  el.style.background = '#f5f5f5'

  return el
}

/** Crée / recrée un nœud hors contrôle Vue (id frais à chaque init). */
const ensureHostEl = (): HTMLDivElement => {
  editorId = `oo-editor-${props.documentId}-${Math.random().toString(36).slice(2, 9)}`
  const el = buildHostEl()

  if (hostEl?.parentElement) {
    hostEl.replaceWith(el)
    hostEl = el

    return el
  }

  if (!vueAnchor.value)
    throw new Error('Ancre éditeur manquante')

  vueAnchor.value.replaceWith(el)
  hostEl = el
  vueAnchor.value = null

  return el
}

const destroyEditor = () => {
  clearReadyTimer()
  try {
    editorInstance?.destroyEditor?.()
  }
  catch {
    // ignore
  }
  editorInstance = null

  if (hostEl)
    hostEl.innerHTML = ''
}

const initEditor = async () => {
  const generation = ++initGeneration
  loading.value = true
  documentReady.value = false
  error.value = ''
  destroyEditor()

  try {
    const query = props.versionId ? `?version_id=${props.versionId}` : ''
    const payload = await $api(`/parapheur/documents/${props.documentId}/onlyoffice/config${query}`) as any

    if (unmounted || generation !== initGeneration)
      return

    if (!payload?.config || !payload?.api_script)
      throw new Error('Configuration ONLYOFFICE incomplète')

    await loadScript(payload.api_script)

    if (unmounted || generation !== initGeneration)
      return

    if (!window.DocsAPI?.DocEditor)
      throw new Error('DocsAPI indisponible')

    await nextTick()

    if (unmounted || generation !== initGeneration)
      return

    const host = ensureHostEl()

    const config: Record<string, unknown> = {
      documentType: payload.config.documentType,
      document: payload.config.document,
      editorConfig: payload.config.editorConfig,
      token: payload.config.token,
      type: payload.config.type || 'desktop',
      width: '100%',
      height: `${EDITOR_HEIGHT_PX}px`,
      events: {
        onAppReady() {
          if (generation !== initGeneration)
            return
          loading.value = false
        },
        onDocumentReady() {
          if (generation !== initGeneration)
            return
          clearReadyTimer()
          loading.value = false
          documentReady.value = true
        },
        onDocumentStateChange(event: { data?: boolean }) {
          // Ne pas rafraîchir tout le dossier ici (re-render parent).
          if (event?.data === false)
            emit('saved')
        },
        onError(event: { data?: unknown }) {
          if (generation !== initGeneration)
            return
          clearReadyTimer()
          loading.value = false
          error.value = ooErrorMessage(event?.data)
          emit('error', error.value)
        },
        async onRequestHistory() {
          try {
            const history = await $api(`/parapheur/documents/${props.documentId}/onlyoffice/history`) as any
            editorInstance?.refreshHistory(history)
          }
          catch (e: any) {
            emit('error', e?.data?.message || 'Historique indisponible')
          }
        },
        async onRequestHistoryData(event: { data?: number }) {
          const versionNumber = event?.data
          if (!versionNumber)
            return
          try {
            const data = await $api(`/parapheur/documents/${props.documentId}/onlyoffice/history/${versionNumber}`) as any
            editorInstance?.setHistoryData(data)
          }
          catch (e: any) {
            emit('error', e?.data?.message || 'Données de version indisponibles')
          }
        },
        onRequestHistoryClose() {
          emit('reload')
        },
        async onRequestRestore(event: { data?: { version?: number } }) {
          const versionNumber = event?.data?.version
          if (!versionNumber)
            return
          try {
            await $api(`/parapheur/documents/${props.documentId}/onlyoffice/restore/${versionNumber}`, {
              method: 'POST',
            })
            emit('reload')
          }
          catch (e: any) {
            emit('error', e?.data?.message || 'Restauration impossible')
          }
        },
        async onRequestCompareFile() {
          try {
            const history = await $api(`/parapheur/documents/${props.documentId}/onlyoffice/history`) as any
            compareVersions.value = (history?.history || [])
              .map((h: any) => ({ title: `V${h.version}`, value: h.version as number }))
              .reverse()
            selectedCompareVersion.value = compareVersions.value[0]?.value ?? null
            compareDialog.value = true
          }
          catch (e: any) {
            emit('error', e?.data?.message || 'Comparaison indisponible')
          }
        },
      },
    }

    void host
    editorInstance = new window.DocsAPI.DocEditor(editorId, config)

    readyTimer = setTimeout(() => {
      if (generation !== initGeneration || documentReady.value || error.value)
        return

      const iframeCount = host.querySelectorAll('iframe').length
      error.value = iframeCount
        ? 'ONLYOFFICE a chargé une iframe mais le document ne s’ouvre pas (JWT, téléchargement fichier ou ONLYOFFICE_APP_URL injoignable depuis Docs).'
        : 'ONLYOFFICE n’a pas créé d’iframe. Vérifiez que le Document Server répond (ONLYOFFICE_URL / healthcheck) puis réessayez.'
      emit('error', error.value)
      loading.value = false
    }, READY_TIMEOUT_MS)
  }
  catch (e: any) {
    if (unmounted || generation !== initGeneration)
      return
    error.value = e?.data?.message || e?.message || 'Impossible d’ouvrir ONLYOFFICE'
    emit('error', error.value)
    loading.value = false
  }
}

/** Réessaie localement — ne dépend plus du parent (@reload). */
const retryEditor = () => {
  void initEditor()
}

const applyCompare = async () => {
  if (!selectedCompareVersion.value)
    return
  try {
    const data = await $api(
      `/parapheur/documents/${props.documentId}/onlyoffice/compare/${selectedCompareVersion.value}`,
    ) as any
    editorInstance?.setRevisedFile(data)
    compareDialog.value = false
  }
  catch (e: any) {
    emit('error', e?.data?.message || 'Comparaison impossible')
  }
}

onMounted(() => {
  unmounted = false
  initEditor()
})

onBeforeUnmount(() => {
  unmounted = true
  initGeneration += 1
  destroyEditor()
  // Remet une ancre vide pour le GC DOM si le parent conserve le slot
  if (hostEl?.parentElement) {
    const placeholder = document.createElement('div')
    placeholder.style.height = `${EDITOR_HEIGHT_PX}px`
    hostEl.replaceWith(placeholder)
    hostEl = null
  }
})
</script>

<template>
  <div class="onlyoffice-editor-wrap">
    <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-2">
      <div class="text-subtitle-2 d-flex align-center gap-2">
        <VIcon
          icon="tabler-file-text"
          size="20"
        />
        Éditeur ONLYOFFICE
        <VChip
          size="x-small"
          :color="statusLabel.color"
          label
        >
          {{ statusLabel.text }}
        </VChip>
      </div>
      <VBtn
        size="small"
        variant="tonal"
        prepend-icon="tabler-refresh"
        :loading="loading"
        @click="retryEditor"
      >
        Recharger
      </VBtn>
    </div>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-2"
    />
    <VAlert
      v-if="error"
      type="warning"
      variant="tonal"
      class="mb-2"
    >
      {{ error }}
      <template #append>
        <VBtn
          size="small"
          variant="text"
          :loading="loading"
          @click="retryEditor"
        >
          Réessayer
        </VBtn>
      </template>
    </VAlert>

    <!-- Ancre Vue remplacée au runtime par un nœud hors VDOM -->
    <div
      ref="vueAnchor"
      class="onlyoffice-editor-anchor"
    />

    <VDialog
      v-model="compareDialog"
      max-width="420"
    >
      <VCard title="Comparer avec une version">
        <VCardText>
          <VSelect
            v-model="selectedCompareVersion"
            :items="compareVersions"
            label="Version à comparer"
            density="comfortable"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="compareDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!selectedCompareVersion"
            @click="applyCompare"
          >
            Comparer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.onlyoffice-editor-wrap {
  width: 100%;
}

.onlyoffice-editor-anchor {
  width: 100%;
  height: 720px;
  min-height: 720px;
  background: #f5f5f5;
}
</style>
