<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useApi } from '@/composables/useApi'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'create', subject: 'Archives' },
})

const router = useRouter()
const { getJson, fetchFonds, fetchNodes, loading } = useArchives()

const structures = ref<any[]>([])
const fonds = ref<any[]>([])
const nodes = ref<any[]>([])
const documentTypes = ref<any[]>([])
const queue = ref<File[]>([])
const submitting = ref(false)
const error = ref<string | null>(null)

const form = ref({
  structure_id: null as number | null,
  producer_unit_id: null as number | null,
  document_type_id: null as number | null,
  title: '',
  typology: '',
  medium: 'electronique',
  fonds_id: null as number | null,
  series_id: null as number | null,
  classification_node_id: null as number | null,
  notes: '',
})

const structureItems = computed(() =>
  structures.value.map(s => ({ title: s.name || s.code, value: s.id })),
)
const typeItems = computed(() =>
  documentTypes.value.map(t => ({ title: t.name || t.code, value: t.id })),
)
const fondsItems = computed(() =>
  fonds.value.map(f => ({ title: `${f.code} — ${f.name}`, value: f.id })),
)
const seriesItems = computed(() => {
  const selected = fonds.value.find(f => f.id === form.value.fonds_id)
  return (selected?.series || []).map((s: any) => ({
    title: `${s.code} — ${s.name}`,
    value: s.id,
  }))
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

function onFiles(files: File[] | File | null) {
  const list = !files ? [] : Array.isArray(files) ? files : [files]
  queue.value = [...queue.value, ...list]
}

function removeAt(index: number) {
  queue.value.splice(index, 1)
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
    if (form.value.title)
      body.append('title', form.value.title)
    if (form.value.typology)
      body.append('typology', form.value.typology)
    if (form.value.notes)
      body.append('notes', form.value.notes)
    body.append('medium', form.value.medium)
    if (form.value.fonds_id)
      body.append('fonds_id', String(form.value.fonds_id))
    if (form.value.series_id)
      body.append('series_id', String(form.value.series_id))
    if (form.value.classification_node_id)
      body.append('classification_node_id', String(form.value.classification_node_id))
    queue.value.forEach(f => body.append('files[]', f))

    const { data, error: err, response } = await useApi('/archives/digitize/batch').post(body).json()
    if (err.value || (response.value && response.value.status >= 400))
      throw new Error(data.value?.message || 'Numérisation impossible')

    const id = data.value?.id
    if (id)
      await router.push({ name: 'archives-versements-id', params: { id } })
  }
  catch (e: any) {
    error.value = e?.message || 'Erreur'
  }
  finally {
    submitting.value = false
  }
}

onMounted(loadMeta)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Numérisation"
      subtitle="Déposer des fichiers, classer, créer un versement SAE"
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

    <VRow>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="parapheur-section-card mb-4">
          <VCardTitle class="text-body-1">
            Fichiers
          </VCardTitle>
          <VCardText>
            <VFileInput
              label="PDF, images…"
              multiple
              show-size
              prepend-icon="tabler-upload"
              accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.gif,.webp"
              @update:model-value="onFiles"
            />

            <div
              v-if="!queue.length"
              class="text-medium-emphasis mt-4"
            >
              Aucun fichier dans la file.
            </div>

            <VList
              v-else
              class="mt-2"
              density="compact"
            >
              <VListItem
                v-for="(file, index) in queue"
                :key="`${file.name}-${index}`"
              >
                <VListItemTitle class="text-truncate">
                  {{ file.name }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ Math.round(file.size / 1024) }} Ko
                </VListItemSubtitle>
                <template #append>
                  <VBtn
                    icon="tabler-arrow-up"
                    variant="text"
                    size="small"
                    :disabled="index === 0"
                    @click="move(index, -1)"
                  />
                  <VBtn
                    icon="tabler-arrow-down"
                    variant="text"
                    size="small"
                    :disabled="index === queue.length - 1"
                    @click="move(index, 1)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    variant="text"
                    size="small"
                    color="error"
                    @click="removeAt(index)"
                  />
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="7"
      >
        <VCard
          class="parapheur-section-card mb-4"
          :loading="loading"
        >
          <VCardTitle class="text-body-1">
            Métadonnées et classement
          </VCardTitle>
          <VCardText class="d-flex flex-column gap-3">
            <VTextField
              v-model="form.title"
              label="Intitulé du versement"
            />
            <VSelect
              v-model="form.structure_id"
              :items="structureItems"
              label="Structure"
            />
            <VSelect
              v-model="form.producer_unit_id"
              :items="structureItems"
              label="Unité productrice"
              clearable
            />
            <VSelect
              v-model="form.document_type_id"
              :items="typeItems"
              label="Type de document"
            />
            <VTextField
              v-model="form.typology"
              label="Typologie"
            />
            <VSelect
              v-model="form.medium"
              :items="mediumItems"
              label="Support"
            />
            <VSelect
              v-model="form.fonds_id"
              :items="fondsItems"
              label="Fonds"
              clearable
            />
            <VSelect
              v-model="form.series_id"
              :items="seriesItems"
              label="Série"
              clearable
              :disabled="!form.fonds_id"
            />
            <VSelect
              v-model="form.classification_node_id"
              :items="nodeItems"
              label="Nœud de classement"
              clearable
            />
            <VTextarea
              v-model="form.notes"
              label="Notes"
              rows="2"
            />
          </VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn
              color="primary"
              :loading="submitting"
              :disabled="!queue.length || !form.structure_id"
              @click="submit"
            >
              Créer le versement
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
