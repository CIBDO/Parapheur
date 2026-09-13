<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  actionLabels,
  confidentialityOptions,
  folderMeta,
  formatDateFr,
  labelOf,
  priorityColor,
  priorityLabels,
  priorityOptions,
  statusColor,
  statusLabels,
  statusOptions,
} from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Parapheur',
  },
})

interface DocItem {
  id: number
  reference: string
  object: string
  status: string
  priority: string
  expected_action: string
  due_date?: string
  structure?: { code: string; name: string }
  type?: { name: string }
  author?: { name: string }
}

interface StructureOption {
  id: number
  code: string
  name: string
}

interface DocumentTypeOption {
  id: number
  code: string
  name: string
}

interface UserOption {
  id: number
  name: string
}

const route = useRoute()
const router = useRouter()

const primaryFolderKeys = ['a_traiter', 'a_valider', 'a_viser', 'envoyes'] as const

const folderFromQuery = () => {
  const raw = route.query.folder
  const key = Array.isArray(raw) ? raw[0] : raw
  if (typeof key === 'string' && folderMeta.some(f => f.key === key))
    return key

  return 'a_traiter'
}

const activeFolder = ref<string | null>(folderFromQuery())
const counts = ref<Record<string, number>>({})
const documents = ref<DocItem[]>([])
const loading = ref(false)
const countsLoading = ref(true)
const showAdvanced = ref(false)

const structures = ref<StructureOption[]>([])
const documentTypes = ref<DocumentTypeOption[]>([])
const authors = ref<UserOption[]>([])

const filters = ref({
  q: '',
  reference: '',
  object: '',
  document_type_id: null as number | null,
  structure_id: null as number | null,
  author_id: null as number | null,
  status: '',
  priority: '',
  confidentiality: '',
  keywords: '',
  document_date_from: '',
  document_date_to: '',
  due_date_from: '',
  due_date_to: '',
})

const activeFolderMeta = computed(() =>
  folderMeta.find(f => f.key === activeFolder.value) || folderMeta[0],
)

const primaryCards = computed(() => {
  const hints: Record<string, string> = {
    a_traiter: 'Intervention requise',
    a_valider: 'Décision attendue',
    a_viser: 'Visa à apposer',
    envoyes: 'Dossiers transmis',
  }

  return primaryFolderKeys.map((key) => {
    const meta = folderMeta.find(f => f.key === key)!

    return {
      key,
      title: meta.title,
      icon: meta.icon,
      color: meta.color,
      hint: hints[key],
      value: counts.value[key] ?? 0,
    }
  })
})

const secondaryFolders = computed(() =>
  folderMeta.filter(f => !primaryFolderKeys.includes(f.key as typeof primaryFolderKeys[number])),
)

const urgentCount = computed(() => counts.value.urgents ?? 0)

const activeFilterCount = computed(() => {
  let n = 0
  const f = filters.value
  if (f.reference)
    n++
  if (f.object)
    n++
  if (f.document_type_id)
    n++
  if (f.structure_id)
    n++
  if (f.author_id)
    n++
  if (f.status)
    n++
  if (f.priority)
    n++
  if (f.confidentiality)
    n++
  if (f.keywords)
    n++
  if (f.document_date_from)
    n++
  if (f.document_date_to)
    n++
  if (f.due_date_from)
    n++
  if (f.due_date_to)
    n++

  return n
})

const headers = [
  { title: 'Référence', key: 'reference', width: '140px' },
  { title: 'Objet', key: 'object' },
  { title: 'Structure', key: 'structure', width: '110px' },
  { title: 'Action', key: 'expected_action', width: '120px' },
  { title: 'Priorité', key: 'priority', width: '120px' },
  { title: 'Statut', key: 'status', width: '130px' },
  { title: 'Échéance', key: 'due_date', width: '120px' },
]

const loadCounts = async () => {
  countsLoading.value = true
  try {
    counts.value = await $api('/parapheur/counts')
  }
  finally {
    countsLoading.value = false
  }
}

const loadMeta = async () => {
  const [types, structList, usersList] = await Promise.all([
    $api('/meta/document-types'),
    $api('/meta/structures'),
    $api('/meta/users'),
  ])

  documentTypes.value = types as DocumentTypeOption[]
  structures.value = structList as StructureOption[]
  authors.value = usersList as UserOption[]
}

const loadDocuments = async () => {
  loading.value = true
  try {
    const query: Record<string, unknown> = {
      folder: activeFolder.value || undefined,
    }

    if (filters.value.q)
      query.q = filters.value.q
    if (filters.value.reference)
      query.reference = filters.value.reference
    if (filters.value.object)
      query.object = filters.value.object
    if (filters.value.document_type_id)
      query.document_type_id = filters.value.document_type_id
    if (filters.value.structure_id)
      query.structure_id = filters.value.structure_id
    if (filters.value.author_id)
      query.author_id = filters.value.author_id
    if (filters.value.status)
      query.status = filters.value.status
    if (filters.value.priority)
      query.priority = filters.value.priority
    if (filters.value.confidentiality)
      query.confidentiality = filters.value.confidentiality
    if (filters.value.keywords)
      query.keywords = filters.value.keywords
    if (filters.value.document_date_from)
      query.document_date_from = filters.value.document_date_from
    if (filters.value.document_date_to)
      query.document_date_to = filters.value.document_date_to
    if (filters.value.due_date_from)
      query.due_date_from = filters.value.due_date_from
    if (filters.value.due_date_to)
      query.due_date_to = filters.value.due_date_to

    const res = await $api('/parapheur/documents', { query })
    documents.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

const selectFolder = async (key: string) => {
  activeFolder.value = key
  await router.replace({ query: { ...route.query, folder: key } })
  await loadDocuments()
}

const refreshAll = async () => {
  await Promise.all([loadCounts(), loadDocuments()])
}

watch(
  () => route.query.folder,
  async () => {
    const next = folderFromQuery()
    if (activeFolder.value === next)
      return
    activeFolder.value = next
    await loadDocuments()
  },
)

const resetFilters = async () => {
  filters.value = {
    q: '',
    reference: '',
    object: '',
    document_type_id: null,
    structure_id: null,
    author_id: null,
    status: '',
    priority: '',
    confidentiality: '',
    keywords: '',
    document_date_from: '',
    document_date_to: '',
    due_date_from: '',
    due_date_to: '',
  }
  showAdvanced.value = false
  await loadDocuments()
}

const openDoc = (item: DocItem) => {
  router.push({ name: 'parapheur-id', params: { id: item.id } })
}

onMounted(async () => {
  await Promise.all([loadCounts(), loadMeta(), loadDocuments()])
})
</script>

<template>
  <div class="parapheur-dashboard">
    <ParapheurPageHeader
      title="Mon parapheur"
      subtitle="Pilotage, recherche et traitement de vos dossiers"
      icon="tabler-briefcase"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading || countsLoading"
          @click="refreshAll"
        >
          Actualiser
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-file-plus"
          :to="{ name: 'parapheur-nouveau' }"
        >
          Nouveau document
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <!-- Recherche -->
    <VCard class="mb-4 search-panel">
      <VCardText class="pb-2">
        <div class="d-flex flex-wrap align-center gap-3">
          <AppTextField
            v-model="filters.q"
            class="search-field flex-grow-1"
            label="Recherche rapide"
            placeholder="Référence, objet…"
            prepend-inner-icon="tabler-search"
            hide-details
            clearable
            @keyup.enter="loadDocuments"
          />
          <VBtn
            color="primary"
            prepend-icon="tabler-search"
            :loading="loading"
            @click="loadDocuments"
          >
            Rechercher
          </VBtn>
          <VBtn
            :variant="showAdvanced ? 'flat' : 'tonal'"
            :color="showAdvanced || activeFilterCount ? 'primary' : 'default'"
            prepend-icon="tabler-adjustments-horizontal"
            @click="showAdvanced = !showAdvanced"
          >
            Avancée
            <VChip
              v-if="activeFilterCount"
              class="ms-2"
              size="x-small"
              color="primary"
              variant="elevated"
            >
              {{ activeFilterCount }}
            </VChip>
          </VBtn>
          <VBtn
            v-if="filters.q || activeFilterCount"
            variant="text"
            color="secondary"
            prepend-icon="tabler-x"
            @click="resetFilters"
          >
            Effacer
          </VBtn>
        </div>
      </VCardText>

      <VExpandTransition>
        <div v-show="showAdvanced">
          <VDivider />
          <VCardText>
            <VRow dense>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.reference"
                  label="Référence"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.object"
                  label="Objet"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.document_type_id"
                  :items="documentTypes"
                  :item-title="(i: DocumentTypeOption) => `${i.code} — ${i.name}`"
                  item-value="id"
                  label="Type"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.structure_id"
                  :items="structures"
                  :item-title="(i: StructureOption) => `${i.code} — ${i.name}`"
                  item-value="id"
                  label="Structure"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.author_id"
                  :items="authors"
                  item-title="name"
                  item-value="id"
                  label="Auteur"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.status"
                  :items="statusOptions"
                  item-title="title"
                  item-value="value"
                  label="Statut"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.priority"
                  :items="priorityOptions"
                  item-title="title"
                  item-value="value"
                  label="Priorité"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="filters.confidentiality"
                  :items="confidentialityOptions"
                  item-title="title"
                  item-value="value"
                  label="Confidentialité"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="filters.keywords"
                  label="Mots-clés"
                  placeholder="ex: dette, DGTCP"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="2"
              >
                <AppTextField
                  v-model="filters.document_date_from"
                  type="date"
                  label="Dossier du"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="2"
              >
                <AppTextField
                  v-model="filters.document_date_to"
                  type="date"
                  label="Dossier au"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="2"
              >
                <AppTextField
                  v-model="filters.due_date_from"
                  type="date"
                  label="Échéance du"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="2"
              >
                <AppTextField
                  v-model="filters.due_date_to"
                  type="date"
                  label="Échéance au"
                  hide-details
                />
              </VCol>
            </VRow>
            <div class="d-flex justify-end gap-2 mt-4">
              <VBtn
                variant="text"
                @click="showAdvanced = false"
              >
                Masquer
              </VBtn>
              <VBtn
                color="primary"
                prepend-icon="tabler-filter"
                :loading="loading"
                @click="loadDocuments"
              >
                Appliquer les filtres
              </VBtn>
            </div>
          </VCardText>
        </div>
      </VExpandTransition>
    </VCard>

    <!-- Autres dossiers + urgents -->
    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap align-center gap-2 py-3">
        <VChip
          v-for="folder in secondaryFolders"
          :key="folder.key"
          :color="activeFolder === folder.key ? folder.color : undefined"
          :variant="activeFolder === folder.key ? 'flat' : 'tonal'"
          class="cursor-pointer"
          prepend-icon="tabler-folder"
          @click="selectFolder(folder.key)"
        >
          {{ folder.title }}
          <span class="ms-1 font-weight-bold">{{ counts[folder.key] ?? 0 }}</span>
        </VChip>
        <VChip
          color="error"
          :variant="urgentCount > 0 ? 'flat' : 'tonal'"
          prepend-icon="tabler-alert-triangle"
        >
          Urgents
          <span class="ms-1 font-weight-bold">{{ urgentCount }}</span>
        </VChip>
      </VCardText>
    </VCard>

    <!-- KPI principaux -->
    <div
      v-if="countsLoading && !Object.keys(counts).length"
      class="text-center py-8"
    >
      <VProgressCircular indeterminate />
    </div>
    <VRow
      v-else
      dense
      class="mb-4"
    >
      <VCol
        v-for="card in primaryCards"
        :key="card.key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="kpi-card cursor-pointer h-100"
          :class="{ 'kpi-card--active': activeFolder === card.key }"
          @click="selectFolder(card.key)"
        >
          <VCardText class="d-flex align-center gap-4 pa-4">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="48"
            >
              <VIcon
                :icon="card.icon"
                size="26"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-h4 font-weight-semibold lh-1 mb-1">
                {{ card.value }}
              </div>
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ card.title }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ card.hint }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Liste -->
    <VCard>
      <VCardItem>
        <template #prepend>
          <VAvatar
            :color="activeFolderMeta.color"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              :icon="activeFolderMeta.icon"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ activeFolderMeta.title }}
        </VCardTitle>
        <VCardSubtitle>
          {{ activeFolder === 'envoyes'
            ? 'Dossiers que vous avez transmis'
            : 'Documents nécessitant votre intervention' }}
        </VCardSubtitle>
        <template #append>
          <VChip
            label
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ documents.length }} document{{ documents.length > 1 ? 's' : '' }}
          </VChip>
        </template>
      </VCardItem>

      <VDivider />

      <VDataTable
        :headers="headers"
        :items="documents"
        :loading="loading"
        item-value="id"
        hover
        class="text-no-wrap"
        @click:row="(_: any, { item }: any) => openDoc(item)"
      >
        <template #item.reference="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: 'parapheur-id', params: { id: item.id } }"
            @click.stop
          >
            {{ item.reference }}
          </RouterLink>
        </template>

        <template #item.object="{ item }">
          <div class="text-wrap subject-cell">
            {{ item.object }}
            <div
              v-if="item.type?.name"
              class="text-caption text-medium-emphasis"
            >
              {{ item.type.name }}
            </div>
          </div>
        </template>

        <template #item.structure="{ item }">
          <VChip
            size="small"
            label
            variant="tonal"
          >
            {{ item.structure?.code || '—' }}
          </VChip>
        </template>

        <template #item.expected_action="{ item }">
          {{ labelOf(actionLabels, item.expected_action) }}
        </template>

        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="priorityColor(item.priority)"
            variant="tonal"
          >
            {{ labelOf(priorityLabels, item.priority) }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            variant="tonal"
          >
            {{ labelOf(statusLabels, item.status) }}
          </VChip>
        </template>

        <template #item.due_date="{ item }">
          {{ formatDateFr(item.due_date) }}
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon
              icon="tabler-folder-off"
              size="40"
              class="mb-2"
            />
            <div class="text-h6 mb-1 text-high-emphasis">
              Aucun document dans ce dossier
            </div>
            <p class="mb-4">
              Changez de corbeille ou créez un nouveau document.
            </p>
            <VBtn
              color="primary"
              variant="tonal"
              :to="{ name: 'parapheur-nouveau' }"
            >
              Nouveau document
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.search-panel {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.search-field {
  min-inline-size: min(100%, 320px);
}

.kpi-card {
  transition: box-shadow 0.18s ease, transform 0.18s ease, outline 0.18s ease;
}

.kpi-card:hover {
  box-shadow: 0 6px 18px rgba(var(--v-theme-on-surface), 0.08);
  transform: translateY(-1px);
}

.kpi-card--active {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 1px;
}

.subject-cell {
  max-inline-size: 320px;
  white-space: normal;
}
</style>
