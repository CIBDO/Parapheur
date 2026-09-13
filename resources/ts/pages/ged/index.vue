<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { originLabels, originOptions } from '@/utils/gedUi'
import {
  confidentialityOptions,
  formatDateFr,
  labelOf,
  statusColor,
  statusLabels,
  statusOptions,
} from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Ged',
  },
})

const router = useRouter()
const loading = ref(true)
const recentLoading = ref(true)
const searchLoading = ref(false)
const counts = ref<Record<string, number>>({})
const recent = ref<any[]>([])
const searchResults = ref<any[]>([])
const hasSearched = ref(false)
const showAdvanced = ref(false)

const simpleQuery = ref('')
const filters = ref({
  q: '',
  document_type_id: null as number | null,
  category_id: null as number | null,
  structure_id: null as number | null,
  status: null as string | null,
  confidentiality: null as string | null,
  origin: null as string | null,
  document_date_from: '',
  document_date_to: '',
  tag: '',
})

const documentTypes = ref<Array<{ id: number; name: string }>>([])
const structures = ref<Array<{ id: number; name: string }>>([])
const categories = ref<Array<{ id: number; name: string }>>([])

const cards = computed(() => [
  { key: 'actifs', title: 'Documents actifs', icon: 'tabler-folder', color: 'primary', to: 'ged-documents', hint: 'Patrimoine vivant' },
  { key: 'a_traiter', title: 'À traiter', icon: 'tabler-inbox', color: 'warning', to: 'ged-a-traiter', hint: 'En attente d\'action' },
  { key: 'non_classes', title: 'Non classés', icon: 'tabler-folder-question', color: 'secondary', to: 'ged-documents', hint: 'Sans plan de classement' },
  { key: 'mes_documents', title: 'Mes documents', icon: 'tabler-folder-user', color: 'success', to: 'ged-mes-documents', hint: 'Mon portefeuille' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.document_type_id)
    n++
  if (filters.value.category_id)
    n++
  if (filters.value.structure_id)
    n++
  if (filters.value.status)
    n++
  if (filters.value.confidentiality)
    n++
  if (filters.value.origin)
    n++
  if (filters.value.document_date_from)
    n++
  if (filters.value.document_date_to)
    n++
  if (filters.value.tag)
    n++

  return n
})

const tableItems = computed(() =>
  hasSearched.value ? searchResults.value : recent.value,
)

const tableTitle = computed(() =>
  hasSearched.value
    ? `Résultats de recherche (${searchResults.value.length})`
    : 'Récemment ajoutés',
)

const quickActions = [
  { title: 'Tous les documents', icon: 'tabler-files', color: 'primary', route: 'ged-documents' },
  { title: 'Mes documents', icon: 'tabler-folder-user', color: 'success', route: 'ged-mes-documents' },
  { title: 'Plan de classement', icon: 'tabler-sitemap', color: 'info', route: 'ged-classification' },
  { title: 'Archives', icon: 'tabler-archive', color: 'secondary', route: 'ged-archives' },
]

const recentHeaders = [
  { title: 'Référence', key: 'reference', width: '140px' },
  { title: 'Objet', key: 'object' },
  { title: 'Origine', key: 'origin', width: '120px' },
  { title: 'Statut', key: 'status', width: '130px' },
  { title: 'Date', key: 'document_date', width: '120px' },
]

watch(simpleQuery, (value) => {
  filters.value.q = value
})

onMounted(async () => {
  await Promise.all([loadDashboard(), loadMeta()])
})

async function loadDashboard() {
  loading.value = true
  recentLoading.value = true
  try {
    const res = await $api('/ged/dashboard')
    counts.value = res.counts || {}
    recent.value = res.recent || []
  }
  catch (error) {
    console.error(error)
    recent.value = []
  }
  finally {
    loading.value = false
    recentLoading.value = false
  }
}

async function loadMeta() {
  try {
    const [types, structs, cats] = await Promise.all([
      $api('/meta/document-types'),
      $api('/meta/structures'),
      $api('/meta/document-categories'),
    ])
    documentTypes.value = types || []
    structures.value = structs || []
    categories.value = cats?.data || cats || []
  }
  catch {
    documentTypes.value = []
    structures.value = []
    categories.value = []
  }
}

async function performSearch() {
  const q = (simpleQuery.value || filters.value.q || '').trim()
  filters.value.q = q
  simpleQuery.value = q

  searchLoading.value = true
  hasSearched.value = true
  try {
    const params: Record<string, string | number> = { per_page: 30, page: 1 }
    Object.entries(filters.value).forEach(([k, v]) => {
      if (v !== null && v !== undefined && v !== '')
        params[k] = v as string | number
    })
    const res = await $api('/ged/documents', { query: params })
    searchResults.value = res.data || []
  }
  catch (error) {
    console.error(error)
    searchResults.value = []
  }
  finally {
    searchLoading.value = false
  }
}

function resetSearch() {
  simpleQuery.value = ''
  filters.value = {
    q: '',
    document_type_id: null,
    category_id: null,
    structure_id: null,
    status: null,
    confidentiality: null,
    origin: null,
    document_date_from: '',
    document_date_to: '',
    tag: '',
  }
  hasSearched.value = false
  searchResults.value = []
  showAdvanced.value = false
}

function openDoc(id: number) {
  router.push({ name: 'ged-id', params: { id: String(id) } })
}
</script>

<template>
  <div class="ged-dashboard">
    <ParapheurPageHeader
      title="GED — Tableau de bord"
      subtitle="Pilotage, recherche et suivi du patrimoine documentaire"
      icon="tabler-folders"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading || recentLoading"
          @click="loadDashboard"
        >
          Actualiser
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-file-plus"
          :to="{ name: 'ged-nouveau' }"
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
            v-model="simpleQuery"
            class="search-field flex-grow-1"
            label="Recherche rapide"
            placeholder="Titre, objet, référence, n° dossier…"
            prepend-inner-icon="tabler-search"
            hide-details
            @keyup.enter="performSearch"
          />
          <VBtn
            color="primary"
            prepend-icon="tabler-search"
            :loading="searchLoading"
            @click="performSearch"
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
            v-if="hasSearched || activeFilterCount || simpleQuery"
            variant="text"
            color="secondary"
            prepend-icon="tabler-x"
            @click="resetSearch"
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
                <AppSelect
                  v-model="filters.document_type_id"
                  :items="documentTypes"
                  item-title="name"
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
                  v-model="filters.category_id"
                  :items="categories"
                  item-title="name"
                  item-value="id"
                  label="Catégorie"
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
                  item-title="name"
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
                md="3"
              >
                <AppSelect
                  v-model="filters.origin"
                  :items="originOptions"
                  item-title="title"
                  item-value="value"
                  label="Origine"
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
                  label="Du"
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
                  label="Au"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="2"
              >
                <AppTextField
                  v-model="filters.tag"
                  label="Tag"
                  placeholder="#Budget"
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
                :loading="searchLoading"
                @click="performSearch"
              >
                Appliquer les filtres
              </VBtn>
            </div>
          </VCardText>
        </div>
      </VExpandTransition>
    </VCard>

    <!-- Actions rapides -->
    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap gap-2 py-3">
        <VBtn
          v-for="action in quickActions"
          :key="action.route"
          size="small"
          variant="tonal"
          :color="action.color"
          :prepend-icon="action.icon"
          :to="{ name: action.route }"
        >
          {{ action.title }}
        </VBtn>
      </VCardText>
    </VCard>

    <!-- KPI -->
    <div
      v-if="loading"
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
        v-for="card in cards"
        :key="card.key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="kpi-card cursor-pointer h-100"
          @click="router.push({ name: card.to })"
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
                {{ counts[card.key] ?? 0 }}
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
            color="primary"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              :icon="hasSearched ? 'tabler-list-search' : 'tabler-files'"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ tableTitle }}
        </VCardTitle>
        <VCardSubtitle>
          {{ hasSearched
            ? 'Documents trouvés selon vos critères'
            : 'Derniers documents ajoutés au patrimoine' }}
        </VCardSubtitle>
        <template #append>
          <VBtn
            v-if="!hasSearched"
            size="small"
            variant="tonal"
            color="primary"
            :to="{ name: 'ged-documents' }"
          >
            Voir tous
          </VBtn>
          <VBtn
            v-else
            size="small"
            variant="text"
            @click="resetSearch"
          >
            Revenir aux récents
          </VBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VDataTable
        :headers="recentHeaders"
        :items="tableItems"
        :loading="hasSearched ? searchLoading : recentLoading"
        item-value="id"
        hover
        class="text-no-wrap"
        @click:row="(_: any, { item }: any) => openDoc(item.id)"
      >
        <template #item.reference="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: 'ged-id', params: { id: String(item.id) } }"
            @click.stop
          >
            {{ item.reference || `#${item.id}` }}
          </RouterLink>
        </template>

        <template #item.object="{ item }">
          <div class="text-wrap subject-cell">
            {{ item.title || item.object || '—' }}
          </div>
        </template>

        <template #item.origin="{ item }">
          {{ labelOf(originLabels, item.origin) }}
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

        <template #item.document_date="{ item }">
          {{ formatDateFr(item.document_date || item.created_at) }}
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon
              icon="tabler-folder-off"
              size="40"
              class="mb-2"
            />
            <div>
              {{ hasSearched
                ? 'Aucun document ne correspond à votre recherche.'
                : 'Aucun document récent accessible.' }}
            </div>
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
  transition: box-shadow 0.18s ease, transform 0.18s ease;
}

.kpi-card:hover {
  box-shadow: 0 6px 18px rgba(var(--v-theme-on-surface), 0.08);
  transform: translateY(-1px);
}

.subject-cell {
  max-inline-size: 420px;
  white-space: normal;
}
</style>
