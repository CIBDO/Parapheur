<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatDateFr } from '@/utils/parapheurUi'
import { workspaceTypeLabels } from '@/utils/workspaceUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const router = useRouter()
const ability = useAbility()
const {
  loading, workspace, storage, recent, folders, storageLabel, storagePct, load,
} = useWorkspaceHome()

const searchLoading = ref(false)
const hasSearched = ref(false)
const showAdvanced = ref(false)
const simpleQuery = ref('')
const searchResults = ref<any[]>([])

const canManageQuotas = computed(() =>
  ability.can('manage', 'WorkspaceAdmin') || ability.can('manage', 'all'),
)
const canReadLibrary = computed(() =>
  ability.can('read', 'Library') || ability.can('manage', 'all') || ability.can('read', 'Workspace'),
)

const filters = ref({
  q: '',
  provenance: null as string | null,
  from: '',
  to: '',
})

const provenanceItems = [
  { value: null, title: 'Toutes provenances' },
  { value: 'personal', title: 'Mon espace' },
  { value: 'shared', title: 'Partagés' },
  { value: 'ged', title: 'GED' },
  { value: 'reference', title: 'Bibliothèque' },
]

const provenanceColor: Record<string, string> = {
  ged: 'primary',
  personal: 'success',
  shared: 'warning',
  reference: 'info',
}

const cards = computed(() => {
  const items = [
    { title: 'Mes fichiers', icon: 'tabler-folder', color: 'primary', to: 'espace-dossiers', value: folders.value.length, hint: 'Explorer' },
    { title: 'Partagés avec moi', icon: 'tabler-share', color: 'success', to: 'espace-partages', value: null, hint: 'Reçus' },
    { title: 'Équipes', icon: 'tabler-users', color: 'secondary', to: 'espace-collaboratifs', value: null, hint: 'Espaces partagés' },
    { title: 'Récents', icon: 'tabler-history', color: 'info', to: 'espace-recents', value: recent.value.length || null, hint: 'Consultés' },
    { title: 'Favoris', icon: 'tabler-star', color: 'warning', to: 'espace-favoris', value: null, hint: 'Marqués' },
    { title: 'Corbeille', icon: 'tabler-trash', color: 'error', to: 'espace-corbeille', value: null, hint: 'Supprimés' },
  ]

  if (canReadLibrary.value) {
    items.push({
      title: 'Bibliothèque',
      icon: 'tabler-books',
      color: 'info',
      to: 'espace-bibliotheque',
      value: null,
      hint: 'Références',
    })
  }

  return items
})

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.provenance)
    n++
  if (filters.value.from)
    n++
  if (filters.value.to)
    n++

  return n
})

const tableItems = computed(() => {
  if (!hasSearched.value)
    return recent.value

  let rows = searchResults.value
  if (filters.value.provenance)
    rows = rows.filter((r: any) => r.provenance === filters.value.provenance)

  if (filters.value.from) {
    const from = new Date(filters.value.from).getTime()
    rows = rows.filter((r: any) => {
      const t = new Date(r.updated_at || r.document_date || 0).getTime()

      return !Number.isNaN(t) && t >= from
    })
  }
  if (filters.value.to) {
    const to = new Date(filters.value.to)
    to.setHours(23, 59, 59, 999)
    const toTs = to.getTime()
    rows = rows.filter((r: any) => {
      const t = new Date(r.updated_at || r.document_date || 0).getTime()

      return !Number.isNaN(t) && t <= toTs
    })
  }

  return rows
})

const tableTitle = computed(() =>
  hasSearched.value
    ? `Résultats de recherche (${tableItems.value.length})`
    : 'Récemment consultés',
)

const quickActions = computed(() => {
  const actions = [
    { title: 'Mes dossiers', icon: 'tabler-folder', color: 'primary', route: 'espace-dossiers' },
    { title: 'Mes documents', icon: 'tabler-files', color: 'info', route: 'espace-documents' },
    { title: 'Partagés', icon: 'tabler-share', color: 'success', route: 'espace-partages' },
    { title: 'Équipes', icon: 'tabler-users', color: 'secondary', route: 'espace-collaboratifs' },
    { title: 'Récents', icon: 'tabler-history', color: 'info', route: 'espace-recents' },
    { title: 'Favoris', icon: 'tabler-star', color: 'warning', route: 'espace-favoris' },
    { title: 'Corbeille', icon: 'tabler-trash', color: 'error', route: 'espace-corbeille' },
    { title: 'Recherche', icon: 'tabler-search', color: 'primary', route: 'espace-recherche' },
  ]

  if (canReadLibrary.value) {
    actions.push({
      title: 'Bibliothèque',
      icon: 'tabler-books',
      color: 'info',
      route: 'espace-bibliotheque',
    })
  }
  if (canManageQuotas.value) {
    actions.push({
      title: 'Quotas',
      icon: 'tabler-database-cog',
      color: 'secondary',
      route: 'espace-admin',
    })
  }

  return actions
})

const recentHeaders = [
  { title: 'Document', key: 'title' },
  { title: 'Provenance', key: 'provenance', width: '140px' },
  { title: 'Date', key: 'updated_at', width: '140px' },
]

watch(simpleQuery, (value) => {
  filters.value.q = value
})

onMounted(load)

async function performSearch() {
  const q = (simpleQuery.value || filters.value.q || '').trim()
  filters.value.q = q
  simpleQuery.value = q

  searchLoading.value = true
  hasSearched.value = true
  try {
    const res = await $api('/search', {
      query: {
        q: q || undefined,
        limit: 50,
      },
    })
    searchResults.value = res.data || []
  }
  catch {
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
    provenance: null,
    from: '',
    to: '',
  }
  hasSearched.value = false
  searchResults.value = []
  showAdvanced.value = false
}

function openRecent(item: any) {
  if (item.url) {
    router.push(item.url)

    return
  }
  if (item.id)
    router.push({ name: 'espace-documents-id', params: { id: String(item.id) } })
}

function openFolder(folder: any) {
  router.push({ name: 'espace-dossiers', query: { folder: folder.id } })
}

const workspaceTypeLabel = computed(() => {
  if (!workspace.value?.type)
    return ''
  const t = workspace.value.type

  return workspaceTypeLabels[t] || t
})
</script>

<template>
  <div class="espace-dashboard">
    <ParapheurPageHeader
      title="Mon espace"
      subtitle="Préparer · partager · envoyer au parapheur"
      icon="tabler-cloud"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-folder"
          :to="{ name: 'espace-dossiers' }"
        >
          Mes fichiers
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-share"
          :to="{ name: 'espace-partages' }"
        >
          Partagés
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-users"
          :to="{ name: 'espace-collaboratifs' }"
        >
          Équipes
        </VBtn>
        <VBtn
          v-if="canReadLibrary"
          variant="tonal"
          prepend-icon="tabler-books"
          :to="{ name: 'espace-bibliotheque' }"
        >
          Bibliothèque
        </VBtn>
        <VBtn
          v-if="canManageQuotas"
          variant="tonal"
          prepend-icon="tabler-database-cog"
          :to="{ name: 'espace-admin' }"
        >
          Quotas
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
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
            placeholder="Titre, objet, référence…"
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
                md="4"
              >
                <AppSelect
                  v-model="filters.provenance"
                  :items="provenanceItems"
                  label="Provenance"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <AppTextField
                  v-model="filters.from"
                  type="date"
                  label="Du"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="4"
              >
                <AppTextField
                  v-model="filters.to"
                  type="date"
                  label="Au"
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

    <!-- Stockage -->
    <VCard
      v-if="storage"
      class="mb-4"
    >
      <VCardText>
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="text-body-1 font-weight-medium">
            Stockage utilisé
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ storageLabel }}
          </div>
        </div>
        <VProgressLinear
          :model-value="storagePct"
          :color="storagePct >= (storage.warn_threshold_percent || 80) ? 'warning' : 'primary'"
          height="10"
          rounded
        />
        <div
          v-if="workspace"
          class="text-caption text-medium-emphasis mt-2"
        >
          Espace : {{ workspace.name }}
          <span v-if="workspaceTypeLabel"> · {{ workspaceTypeLabel }}</span>
        </div>
      </VCardText>
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
        :key="card.to"
        cols="12"
        sm="6"
        md="4"
        lg="3"
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
                {{ card.value !== null ? card.value : '→' }}
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

    <VRow dense>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  :icon="hasSearched ? 'tabler-list-search' : 'tabler-history'"
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
                : 'Derniers documents de votre espace' }}
            </VCardSubtitle>
            <template #append>
              <VBtn
                v-if="!hasSearched"
                size="small"
                variant="tonal"
                color="primary"
                class="me-2"
                :to="{ name: 'espace-recents' }"
              >
                Récents
              </VBtn>
              <VBtn
                v-if="!hasSearched"
                size="small"
                variant="tonal"
                :to="{ name: 'espace-documents' }"
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
            :loading="hasSearched ? searchLoading : loading"
            item-value="id"
            hover
            class="text-no-wrap"
            @click:row="(_: any, { item }: any) => openRecent(item)"
          >
            <template #item.title="{ item }">
              <div class="text-wrap subject-cell font-weight-medium">
                {{ item.title || item.object || '—' }}
              </div>
              <div
                v-if="item.subtitle"
                class="text-caption text-medium-emphasis text-wrap"
              >
                {{ item.subtitle }}
              </div>
            </template>

            <template #item.provenance="{ item }">
              <VChip
                v-if="item.provenance || item.provenance_label"
                size="small"
                :color="provenanceColor[item.provenance] || 'secondary'"
                variant="tonal"
              >
                {{ item.provenance_label || item.provenance || 'Espace' }}
              </VChip>
              <span
                v-else
                class="text-medium-emphasis"
              >Mon espace</span>
            </template>

            <template #item.updated_at="{ item }">
              {{ formatDateFr(item.updated_at || item.document_date) }}
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
                    : 'Aucun document récent.' }}
                </div>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="h-100">
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-folder"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Dossiers racine
            </VCardTitle>
            <VCardSubtitle>
              Accès rapide
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VList
            v-if="folders.length"
            lines="one"
          >
            <VListItem
              v-for="f in folders"
              :key="f.id"
              :title="f.name"
              prepend-icon="tabler-folder"
              @click="openFolder(f)"
            >
              <template #append>
                <VIcon
                  icon="tabler-chevron-right"
                  size="18"
                />
              </template>
            </VListItem>
          </VList>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucun dossier pour le moment.' }}
          </VCardText>
          <VCardActions v-if="folders.length">
            <VBtn
              variant="tonal"
              color="primary"
              block
              :to="{ name: 'espace-dossiers' }"
            >
              Explorer
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
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
