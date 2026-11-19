<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useApi } from '@/composables/useApi'
import { useArchives } from '@/composables/useArchives'
import { useDigitizeCamera } from '@/composables/useDigitizeCamera'
import { type QcResult, runQc } from '@/composables/useDigitizeQc'
import { useDigitizeScanner } from '@/composables/useDigitizeScanner'
import { $api } from '@/utils/api'

definePage({
  meta: { action: 'create', subject: 'Archives' },
})

type QueueItem = {
  id: string
  file: File
  title: string
  source: 'upload' | 'camera' | 'scanner'
  previewUrl?: string
  date_start: string
  date_end: string
  qc: QcResult
}

const MAX_BYTES = 20 * 1024 * 1024
const ALLOWED_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'tif', 'tiff', 'gif', 'webp', 'doc', 'docx']

const steps = [
  { title: 'Capture', subtitle: 'Fichiers + contrôle qualité' },
  { title: 'Constitution', subtitle: 'Ordre, intitulés, dates' },
  { title: 'Description', subtitle: 'Classement et métadonnées' },
  { title: 'Remise', subtitle: 'Bordereau et soumission' },
]

const router = useRouter()
const { getJson, fetchFonds, fetchNodes, loading } = useArchives()
const camera = reactive(useDigitizeCamera())
const scanner = reactive(useDigitizeScanner())
const videoRef = ref<HTMLVideoElement | null>(null)

const step = ref(1)
const tab = ref<'upload' | 'camera' | 'scanner'>('upload')
const structures = ref<any[]>([])
const fonds = ref<any[]>([])
const nodes = ref<any[]>([])
const documentTypes = ref<any[]>([])
const queue = ref<QueueItem[]>([])
const submitting = ref(false)
const error = ref<string | null>(null)
const previewId = ref<string | null>(null)
const createdTransfer = ref<any | null>(null)
const submitAfter = ref(true)

const form = ref({
  structure_id: null as number | null,
  producer_unit_id: null as number | null,
  document_type_id: null as number | null,
  title: '',
  typology: 'numerisation',
  medium: 'electronique',
  grouping: 'pieces' as 'pieces' | 'dossier',
  fonds_id: null as number | null,
  series_id: null as number | null,
  classification_node_id: null as number | null,
  notes: '',
})

const profiles = [
  { title: 'Courrier entrant', typology: 'courrier', medium: 'electronique', grouping: 'pieces' as const },
  { title: 'Dossier complet', typology: 'dossier', medium: 'hybride', grouping: 'dossier' as const },
  { title: 'Pièces justificatives', typology: 'justificatif', medium: 'electronique', grouping: 'pieces' as const },
]

const structureItems = computed(() => structures.value.map(s => ({ title: s.name || s.code, value: s.id })))
const typeItems = computed(() => documentTypes.value.map(t => ({ title: t.name || t.code, value: t.id })))
const fondsItems = computed(() => fonds.value.map(f => ({ title: `${f.code} — ${f.name}`, value: f.id })))
const seriesItems = computed(() => {
  const selected = fonds.value.find(f => f.id === form.value.fonds_id)
  return (selected?.series || []).map((s: any) => ({ title: `${s.code} — ${s.name}`, value: s.id }))
})
const nodeItems = computed(() =>
  nodes.value
    .filter((n) => {
      if (form.value.fonds_id && n.fonds_id && n.fonds_id !== form.value.fonds_id)
        return false
      if (form.value.series_id && n.series_id && n.series_id !== form.value.series_id)
        return false
      return true
    })
    .map(n => ({ title: `${n.code} — ${n.name}`, value: n.id })),
)

const mediumItems = [
  { title: 'Électronique', value: 'electronique' },
  { title: 'Hybride', value: 'hybride' },
  { title: 'Physique', value: 'physique' },
]
const groupingItems = [
  { title: 'Une pièce par fichier', value: 'pieces' },
  { title: 'Un dossier (1 document, multi-fichiers)', value: 'dossier' },
]

const sourceChannel = computed<'upload' | 'camera' | 'scanner'>(() => {
  if (queue.value.some(i => i.source === 'scanner'))
    return 'scanner'
  if (queue.value.some(i => i.source === 'camera'))
    return 'camera'
  return 'upload'
})

const qcSummary = computed(() => {
  const total = queue.value.length
  const errors = queue.value.filter(i => i.qc.status === 'error').length
  const warns = queue.value.filter(i => i.qc.status === 'warn').length
  const pending = queue.value.filter(i => i.qc.status === 'pending').length
  return { total, errors, warns, pending, ok: total - errors - warns - pending }
})

const canGoStep2 = computed(() => queue.value.length > 0 && qcSummary.value.errors === 0 && qcSummary.value.pending === 0)
const canGoStep3 = computed(() => queue.value.every(i => i.title.trim().length > 0))
const canGoStep4 = computed(() => {
  if (!form.value.structure_id || !form.value.title.trim())
    return false
  if (form.value.typology === 'courrier' || form.value.typology === 'dossier')
    return Boolean(form.value.fonds_id || form.value.classification_node_id)
  return true
})

const previewItem = computed(() => queue.value.find(i => i.id === previewId.value) || null)

function uid() {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}
function extOf(name: string) {
  return (name.split('.').pop() || '').toLowerCase()
}
function pathTitle(name: string) {
  return name.replace(/\.[^.]+$/, '') || name
}
function previewFor(file: File) {
  if (file.type.startsWith('image/') || file.type === 'application/pdf')
    return URL.createObjectURL(file)
  return undefined
}
function qcColor(status: string) {
  if (status === 'ok')
    return 'success'
  if (status === 'warn')
    return 'warning'
  if (status === 'error')
    return 'error'
  return 'secondary'
}

async function pushFiles(files: File[], source: QueueItem['source']) {
  const errors: string[] = []
  for (const file of files) {
    if (file.size > MAX_BYTES) {
      errors.push(`${file.name} dépasse 20 Mo`)
      continue
    }
    if (!ALLOWED_EXT.includes(extOf(file.name))) {
      errors.push(`${file.name} : type non autorisé`)
      continue
    }
    const item: QueueItem = {
      id: uid(),
      file,
      title: pathTitle(file.name),
      source,
      previewUrl: previewFor(file),
      date_start: '',
      date_end: '',
      qc: { status: 'pending', notes: ['Contrôle en cours…'] },
    }
    queue.value.push(item)
    item.qc = await runQc(file)
  }
  if (errors.length)
    error.value = errors.join(' · ')
}

function onFiles(files: File[] | File | null) {
  const list = !files ? [] : Array.isArray(files) ? files : [files]
  pushFiles(list, 'upload')
}

function removeAt(index: number) {
  const item = queue.value[index]
  if (item?.previewUrl)
    URL.revokeObjectURL(item.previewUrl)
  if (previewId.value === item?.id)
    previewId.value = null
  queue.value.splice(index, 1)
}

function clearQueue() {
  queue.value.forEach((i) => {
    if (i.previewUrl)
      URL.revokeObjectURL(i.previewUrl)
  })
  queue.value = []
  previewId.value = null
}

function move(index: number, delta: number) {
  const target = index + delta
  if (target < 0 || target >= queue.value.length)
    return
  const copy = [...queue.value]
  const [item] = copy.splice(index, 1)
  copy.splice(target, 0, item)
  queue.value = copy
}

async function recheck(item: QueueItem) {
  item.qc = { status: 'pending', notes: ['Contrôle en cours…'] }
  item.qc = await runQc(item.file)
}

function applyProfile(p: typeof profiles[number]) {
  form.value.typology = p.typology
  form.value.medium = p.medium
  form.value.grouping = p.grouping
  if (!form.value.title)
    form.value.title = p.title
}

function next() {
  error.value = null
  if (step.value === 1 && !canGoStep2.value) {
    error.value = 'Ajoutez des pièces sans erreur QC avant de continuer.'
    return
  }
  if (step.value === 2 && !canGoStep3.value) {
    error.value = 'Chaque pièce doit avoir un intitulé.'
    return
  }
  if (step.value === 3 && !canGoStep4.value) {
    error.value = 'Intitulé, structure et classement (fonds ou nœud) requis pour ce profil.'
    return
  }
  if (step.value < 4)
    step.value += 1
}

function prev() {
  error.value = null
  if (step.value > 1)
    step.value -= 1
}

async function loadMeta() {
  const [structs, fondsRes, nodesRes, typesRes] = await Promise.all([
    getJson('/meta/structures').catch(() => ({ data: [] })),
    fetchFonds().catch(() => ({ data: [] })),
    fetchNodes().catch(() => ({ data: [] })),
    getJson('/meta/document-types').catch(() => ({ data: [] })),
  ])
  structures.value = structs.data || structs || []
  fonds.value = fondsRes.data || []
  nodes.value = nodesRes.data || []
  documentTypes.value = typesRes.data || typesRes || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
  if (!form.value.document_type_id && documentTypes.value[0])
    form.value.document_type_id = documentTypes.value[0].id
}

watch(() => form.value.fonds_id, () => {
  form.value.series_id = null
})
watch(videoRef, (el) => {
  camera.videoEl = el
})
watch(tab, async (v) => {
  if (v === 'camera' && !camera.active)
    await camera.start()
  if (v !== 'camera')
    camera.stop()
  if (v === 'scanner' && scanner.available)
    await scanner.init()
})

async function addCameraPdf() {
  try {
    const file = await camera.pagesToPdfFile(`camera-${Date.now()}.pdf`)
    await pushFiles([file], 'camera')
    camera.clearPages()
  }
  catch (e: any) {
    error.value = e?.message || 'Assemblage PDF impossible'
  }
}

async function acquireScanner() {
  const files = await scanner.acquire()
  if (files.length)
    await pushFiles(files, 'scanner')
}

async function downloadBordereau(transferId: number) {
  const blob = await $api<Blob>(`/archives/transfers/${transferId}/bordereau`, {
    responseType: 'blob',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `bordereau-versement-${transferId}.pdf`
  a.click()
  URL.revokeObjectURL(url)
}

async function submit() {
  if (!form.value.structure_id || queue.value.length === 0)
    return
  submitting.value = true
  error.value = null
  try {
    const body = new FormData()
    body.append('structure_id', String(form.value.structure_id))
    if (form.value.producer_unit_id)
      body.append('producer_unit_id', String(form.value.producer_unit_id))
    if (form.value.document_type_id)
      body.append('document_type_id', String(form.value.document_type_id))
    body.append('title', form.value.title.trim())
    if (form.value.typology)
      body.append('typology', form.value.typology)
    if (form.value.notes)
      body.append('notes', form.value.notes)
    body.append('medium', form.value.medium)
    body.append('grouping', form.value.grouping)
    body.append('source_channel', sourceChannel.value)
    body.append('submit_after', submitAfter.value ? '1' : '0')
    body.append('piece_titles', JSON.stringify(queue.value.map(i => i.title)))
    body.append('piece_meta', JSON.stringify(queue.value.map(i => ({
      date_start: i.date_start || null,
      date_end: i.date_end || null,
      qc_checksum: i.qc.checksum || null,
      qc_status: i.qc.status,
      qc_notes: i.qc.notes.join(' | '),
    }))))
    if (form.value.fonds_id)
      body.append('fonds_id', String(form.value.fonds_id))
    if (form.value.series_id)
      body.append('series_id', String(form.value.series_id))
    if (form.value.classification_node_id)
      body.append('classification_node_id', String(form.value.classification_node_id))
    queue.value.forEach(i => body.append('files[]', i.file))

    const { data, error: err, response } = await useApi('/archives/digitize/batch').post(body).json()
    if (err.value || (response.value && response.value.status >= 400))
      throw new Error(data.value?.message || 'Remise impossible')

    createdTransfer.value = data.value
    try {
      await downloadBordereau(Number(data.value.id))
    }
    catch {
      // PDF optionnel : le versement est déjà créé
    }
  }
  catch (e: any) {
    error.value = e?.message || 'Erreur'
  }
  finally {
    submitting.value = false
  }
}

onMounted(loadMeta)
onBeforeUnmount(() => {
  camera.stop()
  scanner.destroy()
  clearQueue()
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Poste de versement"
      subtitle="Assistant numérisation — capture, constitution, description, remise"
      icon="tabler-scan"
    />

    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VStepper
          :model-value="step"
          alt-labels
          hide-actions
          flat
        >
          <VStepperHeader>
            <template
              v-for="(s, i) in steps"
              :key="s.title"
            >
              <VStepperItem
                :value="i + 1"
                :title="s.title"
                :subtitle="s.subtitle"
              />
              <VDivider v-if="i < steps.length - 1" />
            </template>
          </VStepperHeader>
        </VStepper>
      </VCardText>
    </VCard>

    <!-- Étape 1 — Capture + QC -->
    <div v-show="step === 1">
      <VRow>
        <VCol
          cols="12"
          lg="6"
        >
          <VCard class="parapheur-section-card mb-4">
            <VTabs v-model="tab">
              <VTab value="upload">
                Téléversement
              </VTab>
              <VTab value="camera">
                Caméra
              </VTab>
              <VTab value="scanner">
                Scanner
              </VTab>
            </VTabs>
            <VCardText>
              <div v-show="tab === 'upload'">
                <VFileInput
                  label="PDF, images, Office…"
                  multiple
                  show-size
                  prepend-icon="tabler-upload"
                  accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.gif,.webp,.doc,.docx"
                  @update:model-value="onFiles"
                />
              </div>
              <div v-show="tab === 'camera'">
                <VAlert
                  v-if="camera.error"
                  type="warning"
                  class="mb-3"
                >
                  {{ camera.error }}
                </VAlert>
                <div class="digitize-camera-frame mb-3">
                  <video
                    ref="videoRef"
                    class="digitize-camera-video"
                    playsinline
                    muted
                  />
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <VBtn
                    v-if="!camera.active"
                    color="primary"
                    @click="camera.start()"
                  >
                    Démarrer
                  </VBtn>
                  <VBtn
                    v-else
                    color="primary"
                    @click="camera.capturePage()"
                  >
                    Capturer
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    :disabled="!camera.pages.length"
                    @click="addCameraPdf"
                  >
                    Ajouter PDF ({{ camera.pages.length }})
                  </VBtn>
                </div>
              </div>
              <div v-show="tab === 'scanner'">
                <VAlert
                  v-if="!scanner.available"
                  type="info"
                  variant="tonal"
                >
                  Définir <code>VITE_DYNAMSOFT_LICENSE</code> pour le scanner bureau.
                </VAlert>
                <template v-else>
                  <div
                    id="dwt-container"
                    class="digitize-dwt mb-3"
                  />
                  <VBtn
                    color="primary"
                    :loading="scanner.loading"
                    @click="acquireScanner"
                  >
                    Acquérir
                  </VBtn>
                </template>
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          lg="6"
        >
          <VCard class="parapheur-section-card mb-4">
            <VCardTitle class="text-body-1 d-flex justify-space-between">
              <span>Contrôle qualité ({{ qcSummary.total }})</span>
              <div class="d-flex gap-1">
                <VChip
                  size="small"
                  color="success"
                  label
                >
                  {{ qcSummary.ok }} OK
                </VChip>
                <VChip
                  size="small"
                  color="warning"
                  label
                >
                  {{ qcSummary.warns }}
                </VChip>
                <VChip
                  size="small"
                  color="error"
                  label
                >
                  {{ qcSummary.errors }}
                </VChip>
              </div>
            </VCardTitle>
            <VCardText>
              <div
                v-if="!queue.length"
                class="text-medium-emphasis"
              >
                Aucune pièce capturée.
              </div>
              <VList
                v-else
                density="compact"
              >
                <VListItem
                  v-for="(item, index) in queue"
                  :key="item.id"
                  :active="previewId === item.id"
                  @click="previewId = item.id"
                >
                  <VListItemTitle class="text-truncate">
                    {{ item.title }}
                  </VListItemTitle>
                  <VListItemSubtitle>
                    {{ item.qc.notes[0] }}
                  </VListItemSubtitle>
                  <template #append>
                    <VChip
                      size="x-small"
                      :color="qcColor(item.qc.status)"
                      label
                      class="me-1"
                    >
                      {{ item.qc.status }}
                    </VChip>
                    <VBtn
                      icon="tabler-refresh"
                      size="small"
                      variant="text"
                      @click.stop="recheck(item)"
                    />
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      variant="text"
                      color="error"
                      @click.stop="removeAt(index)"
                    />
                  </template>
                </VListItem>
              </VList>
              <div
                v-if="previewItem?.previewUrl"
                class="mt-3"
              >
                <iframe
                  v-if="previewItem.file.type === 'application/pdf'"
                  :src="previewItem.previewUrl"
                  class="digitize-preview"
                />
                <img
                  v-else
                  :src="previewItem.previewUrl"
                  class="digitize-preview"
                  alt="Aperçu QC"
                >
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <!-- Étape 2 — Constitution -->
    <div v-show="step === 2">
      <VCard class="parapheur-section-card mb-4">
        <VCardTitle class="text-body-1">
          Constitution du dossier / des pièces
        </VCardTitle>
        <VCardText>
          <VSelect
            v-model="form.grouping"
            :items="groupingItems"
            label="Mode"
            class="mb-4"
            style="max-width: 420px"
          />
          <VTable density="compact">
            <thead>
              <tr>
                <th style="width:56px">
                  N°
                </th>
                <th>Intitulé</th>
                <th style="width:150px">
                  Date début
                </th>
                <th style="width:150px">
                  Date fin
                </th>
                <th style="width:120px" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(item, index) in queue"
                :key="item.id"
              >
                <td>{{ index + 1 }}</td>
                <td>
                  <VTextField
                    v-model="item.title"
                    density="compact"
                    hide-details
                    variant="underlined"
                  />
                </td>
                <td>
                  <VTextField
                    v-model="item.date_start"
                    type="date"
                    density="compact"
                    hide-details
                    variant="underlined"
                  />
                </td>
                <td>
                  <VTextField
                    v-model="item.date_end"
                    type="date"
                    density="compact"
                    hide-details
                    variant="underlined"
                  />
                </td>
                <td>
                  <VBtn
                    icon="tabler-arrow-up"
                    size="small"
                    variant="text"
                    :disabled="index === 0"
                    @click="move(index, -1)"
                  />
                  <VBtn
                    icon="tabler-arrow-down"
                    size="small"
                    variant="text"
                    :disabled="index === queue.length - 1"
                    @click="move(index, 1)"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </div>

    <!-- Étape 3 — Description -->
    <div v-show="step === 3">
      <VCard
        class="parapheur-section-card mb-4"
        :loading="loading"
      >
        <VCardTitle class="text-body-1">
          Description et classement
        </VCardTitle>
        <VCardText>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <VBtn
              v-for="p in profiles"
              :key="p.title"
              size="small"
              variant="tonal"
              @click="applyProfile(p)"
            >
              {{ p.title }}
            </VBtn>
          </div>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.title"
                label="Intitulé du versement *"
                class="mb-3"
              />
              <VSelect
                v-model="form.structure_id"
                :items="structureItems"
                label="Structure *"
                class="mb-3"
              />
              <VSelect
                v-model="form.producer_unit_id"
                :items="structureItems"
                label="Unité productrice"
                clearable
                class="mb-3"
              />
              <VSelect
                v-model="form.document_type_id"
                :items="typeItems"
                label="Type de document"
                class="mb-3"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.typology"
                label="Typologie"
                class="mb-3"
              />
              <VSelect
                v-model="form.medium"
                :items="mediumItems"
                label="Support"
                class="mb-3"
              />
              <VSelect
                v-model="form.fonds_id"
                :items="fondsItems"
                label="Fonds"
                clearable
                class="mb-3"
              />
              <VSelect
                v-model="form.series_id"
                :items="seriesItems"
                label="Série"
                clearable
                :disabled="!form.fonds_id"
                class="mb-3"
              />
              <VSelect
                v-model="form.classification_node_id"
                :items="nodeItems"
                label="Nœud de classement"
                clearable
                class="mb-3"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.notes"
                label="Notes de versement"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </div>

    <!-- Étape 4 — Remise -->
    <div v-show="step === 4">
      <VCard class="parapheur-section-card mb-4">
        <VCardTitle class="text-body-1">
          Remise aux archives
        </VCardTitle>
        <VCardText v-if="!createdTransfer">
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            Récapitulatif : <strong>{{ queue.length }}</strong> pièce(s) · canal
            <strong>{{ sourceChannel }}</strong> · mode
            <strong>{{ form.grouping }}</strong> · QC
            <strong>{{ qcSummary.ok }} OK</strong> /
            {{ qcSummary.warns }} avertissement(s)
          </VAlert>
          <div class="text-body-2 mb-2">
            <strong>{{ form.title || '—' }}</strong>
          </div>
          <div class="text-medium-emphasis mb-4">
            Structure #{{ form.structure_id }} · typologie {{ form.typology }} · support {{ form.medium }}
          </div>
          <VCheckbox
            v-model="submitAfter"
            label="Soumettre immédiatement le versement (sinon brouillon)"
            hide-details
            class="mb-4"
          />
          <VBtn
            color="primary"
            size="large"
            :loading="submitting"
            :disabled="!canGoStep4"
            @click="submit"
          >
            Créer le versement et générer le bordereau
          </VBtn>
        </VCardText>
        <VCardText v-else>
          <VAlert
            type="success"
            class="mb-4"
          >
            Versement <strong>{{ createdTransfer.reference }}</strong>
            créé (statut : {{ createdTransfer.status }}). Le bordereau PDF a été téléchargé.
          </VAlert>
          <div class="d-flex flex-wrap gap-2">
            <VBtn
              color="primary"
              @click="router.push({ name: 'archives-versements-id', params: { id: createdTransfer.id } })"
            >
              Ouvrir le versement
            </VBtn>
            <VBtn
              variant="tonal"
              @click="downloadBordereau(createdTransfer.id)"
            >
              Retélécharger le bordereau
            </VBtn>
            <VBtn
              variant="text"
              @click="router.push({ name: 'archives-mes-versements' })"
            >
              Mes versements
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </div>

    <div
      v-if="!createdTransfer"
      class="d-flex justify-space-between mt-2 mb-6"
    >
      <VBtn
        variant="text"
        :disabled="step === 1"
        @click="prev"
      >
        Précédent
      </VBtn>
      <div class="d-flex gap-2">
        <VBtn
          v-if="step === 1 && queue.length"
          variant="text"
          color="error"
          @click="clearQueue"
        >
          Vider la file
        </VBtn>
        <VBtn
          v-if="step < 4"
          color="primary"
          @click="next"
        >
          Suivant
        </VBtn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.digitize-camera-frame {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: #111;
  aspect-ratio: 4 / 3;
}
.digitize-camera-video {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.digitize-preview {
  display: block;
  width: 100%;
  max-height: 280px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  object-fit: contain;
  background: rgba(var(--v-theme-on-surface), 0.03);
}
.digitize-dwt { min-height: 100px; }
</style>
