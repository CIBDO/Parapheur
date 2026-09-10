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

const loadCounts = async () => {
  counts.value = await $api('/parapheur/counts')
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
  await loadDocuments()
}

onMounted(async () => {
  await Promise.all([loadCounts(), loadMeta(), loadDocuments()])
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mon parapheur"
      :subtitle="activeFolder === 'envoyes'
        ? 'Suivi des dossiers que vous avez transmis'
        : 'Documents nécessitant votre intervention'"
      icon="tabler-briefcase"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-file-plus"
          :to="{ name: 'parapheur-nouveau' }"
        >
          Nouveau document
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow class="mb-6">
      <VCol
        v-for="folder in folderMeta"
        :key="folder.key"
        cols="6"
        sm="4"
        md="3"
        lg="2"
      >
        <VCard
          class="parapheur-folder-tile"
          :class="{ 'parapheur-folder-tile--active': activeFolder === folder.key }"
          @click="selectFolder(folder.key)"
        >
          <VCardText class="py-4 px-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <VAvatar
                :color="folder.color"
                variant="tonal"
                size="36"
                rounded
              >
                <VIcon
                  :icon="folder.icon"
                  size="20"
                />
              </VAvatar>
              <span class="text-h5 font-weight-bold">
                {{ counts[folder.key] ?? 0 }}
              </span>
            </div>
            <div class="text-caption text-medium-emphasis text-truncate">
              {{ folder.title }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        sm="4"
        md="3"
        lg="2"
      >
        <VCard
          color="error"
          variant="tonal"
          class="parapheur-folder-tile"
        >
          <VCardText class="py-4 px-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <VAvatar
                color="error"
                variant="flat"
                size="36"
                rounded
              >
                <VIcon
                  icon="tabler-alert-triangle"
                  size="20"
                />
              </VAvatar>
              <span class="text-h5 font-weight-bold">
                {{ counts.urgents ?? 0 }}
              </span>
            </div>
            <div class="text-caption">
              Urgents
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6 parapheur-section-card">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-search"
            size="22"
          />
          Rechercher
        </VCardTitle>
        <VCardSubtitle>
          Filtrez rapidement votre {{ activeFolderMeta.title.toLowerCase() }}
        </VCardSubtitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche libre"
              placeholder="Référence, objet…"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="loadDocuments"
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
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.priority"
              :items="priorityOptions"
              item-title="title"
              item-value="value"
              label="Priorité"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center gap-2"
          >
            <VBtn
              color="primary"
              :loading="loading"
              block
              @click="loadDocuments"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>

        <div class="d-flex flex-wrap align-center gap-2 mt-3">
          <VBtn
            size="small"
            variant="text"
            :prepend-icon="showAdvanced ? 'tabler-chevron-up' : 'tabler-chevron-down'"
            @click="showAdvanced = !showAdvanced"
          >
            {{ showAdvanced ? 'Masquer les filtres avancés' : 'Filtres avancés' }}
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            color="secondary"
            @click="resetFilters"
          >
            Réinitialiser
          </VBtn>
        </div>

        <VExpandTransition>
          <div v-show="showAdvanced">
            <VRow class="mt-1">
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.reference"
                  label="Référence"
                  clearable
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
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="filters.keywords"
                  label="Mots-clés"
                  placeholder="ex: dette, DGTCP"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.document_date_from"
                  type="date"
                  label="Date dossier depuis"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.document_date_to"
                  type="date"
                  label="Date dossier jusqu’à"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.due_date_from"
                  type="date"
                  label="Échéance depuis"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="filters.due_date_to"
                  type="date"
                  label="Échéance jusqu’à"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            :icon="activeFolderMeta.icon"
            size="22"
          />
          {{ activeFolderMeta.title }}
        </VCardTitle>
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

      <div
        v-if="!loading && !documents.length"
        class="parapheur-empty"
      >
        <VIcon
          icon="tabler-folder-off"
          size="40"
          class="mb-3 text-medium-emphasis"
        />
        <div class="text-h6 mb-1">
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

      <VDataTable
        v-else
        :items="documents"
        :loading="loading"
        :headers="[
          { title: 'Référence', key: 'reference' },
          { title: 'Objet', key: 'object' },
          { title: 'Structure', key: 'structure' },
          { title: 'Action', key: 'expected_action' },
          { title: 'Priorité', key: 'priority' },
          { title: 'Statut', key: 'status' },
          { title: 'Échéance', key: 'due_date' },
          { title: '', key: 'actions', sortable: false, align: 'end' },
        ]"
        item-value="id"
        class="text-no-wrap"
        hover
      >
        <template #item.reference="{ item }">
          <span class="font-weight-medium text-primary">{{ item.reference }}</span>
        </template>
        <template #item.object="{ item }">
          <div class="text-wrap" style="max-inline-size: 280px;">
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
            label
          >
            {{ labelOf(priorityLabels, item.priority) }}
          </VChip>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            label
            variant="tonal"
          >
            {{ labelOf(statusLabels, item.status) }}
          </VChip>
        </template>
        <template #item.due_date="{ item }">
          {{ formatDateFr(item.due_date) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            color="primary"
            variant="tonal"
            :to="{ name: 'parapheur-id', params: { id: item.id } }"
          >
            Ouvrir
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
