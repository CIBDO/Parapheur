<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondenceDirectionLabels,
  correspondencePriorityColors,
  correspondencePriorityLabels,
  correspondenceStatusColors,
  correspondenceStatusLabels,
  detailRouteName,
  formatCorrespondenceNumber,
  formatCourrierDate,
  formatCourrierDateTime,
  listItems,
  partiesByRole,
  partyDisplayName,
  statusLabel,
} from '@/utils/courrierUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Courrier',
  },
})

const router = useRouter()
const { fetchDashboard, fetchDashboardDg, fetchDashboardDirection } = useCorrespondence()

const stats = ref<Record<string, number>>({})
const loading = ref(true)
const recentLoading = ref(true)
const searchLoading = ref(false)
const dashboardType = ref<'order-office' | 'dg' | 'direction'>('order-office')
const showAdvanced = ref(false)
const hasSearched = ref(false)
const recentIncoming = ref<any[]>([])
const searchResults = ref<any[]>([])

const simpleQuery = ref('')
const filters = ref({
  q: '',
  direction: null as string | null,
  status: null as string | null,
  priority: null as string | null,
  from: '',
  to: '',
})

const directionItems = computed(() => [
  { value: null, title: 'Toutes directions' },
  ...Object.entries(correspondenceDirectionLabels).map(([value, title]) => ({ value, title })),
])

const statusItems = computed(() => [
  { value: null, title: 'Tous les statuts' },
  ...Object.entries(correspondenceStatusLabels).map(([value, title]) => ({ value, title })),
])

const priorityItems = computed(() => [
  { value: null, title: 'Toutes priorités' },
  ...Object.entries(correspondencePriorityLabels).map(([value, title]) => ({ value, title })),
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.direction)
    n++
  if (filters.value.status)
    n++
  if (filters.value.priority)
    n++
  if (filters.value.from)
    n++
  if (filters.value.to)
    n++

  return n
})

const cards = computed(() => [
  { title: 'Reçus aujourd\'hui', value: stats.value.received_today || 0, icon: 'tabler-inbox', color: 'info', route: 'courrier-entrants', hint: 'Arrivées du jour' },
  { title: 'À affecter', value: stats.value.to_assign || 0, icon: 'tabler-user-plus', color: 'warning', route: 'courrier-a-affecter', hint: 'Sans destinataire' },
  { title: 'En traitement', value: stats.value.in_processing || 0, icon: 'tabler-clock', color: 'primary', route: 'courrier-a-traiter', hint: 'En cours' },
  { title: 'En retard', value: stats.value.overdue || 0, icon: 'tabler-alert-triangle', color: 'error', route: 'courrier-en-retard', hint: 'Échéance dépassée' },
])

const tableItems = computed(() =>
  hasSearched.value ? searchResults.value : recentIncoming.value,
)

const tableTitle = computed(() =>
  hasSearched.value
    ? `Résultats de recherche (${searchResults.value.length})`
    : 'Derniers courriers entrants',
)

const recentHeaders = [
  { title: 'N°', key: 'number', width: '140px' },
  { title: 'Objet', key: 'subject' },
  { title: 'Expéditeur', key: 'sender', width: '180px' },
  { title: 'Priorité', key: 'priority', width: '120px' },
  { title: 'Statut', key: 'status', width: '140px' },
  { title: 'Reçu le', key: 'received_at', width: '150px' },
]

const quickActions = [
  { title: 'Enregistrer un entrant', icon: 'tabler-mail-plus', color: 'primary', route: 'courrier-entrants-nouveau' },
  { title: 'Nouveau sortant', icon: 'tabler-send', color: 'success', route: 'courrier-sortants-nouveau' },
  { title: 'Note interne', icon: 'tabler-arrows-exchange', color: 'warning', route: 'courrier-internes-nouveau' },
  { title: 'Bordereau', icon: 'tabler-clipboard-list', color: 'info', route: 'courrier-bordereaux' },
]

onMounted(async () => {
  await Promise.all([loadDashboard(), loadRecentIncoming()])
})

watch(simpleQuery, (value) => {
  filters.value.q = value
})

async function loadDashboard() {
  loading.value = true
  try {
    if (dashboardType.value === 'dg')
      stats.value = await fetchDashboardDg()
    else if (dashboardType.value === 'direction')
      stats.value = await fetchDashboardDirection()
    else
      stats.value = await fetchDashboard()
  }
  catch (error) {
    console.error(error)
  }
  finally {
    loading.value = false
  }
}

async function switchDashboard(type: 'order-office' | 'dg' | 'direction') {
  dashboardType.value = type
  await loadDashboard()
}

async function loadRecentIncoming() {
  recentLoading.value = true
  try {
    const response = await $api('/mail/correspondences', {
      query: {
        direction: 'entrant',
        per_page: 10,
      },
    })
    recentIncoming.value = listItems(response)
  }
  catch (error) {
    console.error(error)
    recentIncoming.value = []
  }
  finally {
    recentLoading.value = false
  }
}

async function performSearch() {
  const q = (simpleQuery.value || filters.value.q || '').trim()
  filters.value.q = q
  simpleQuery.value = q

  searchLoading.value = true
  hasSearched.value = true
  try {
    const response = await $api('/mail/search', {
      query: {
        q: q || undefined,
        direction: filters.value.direction || undefined,
        status: filters.value.status || undefined,
        priority: filters.value.priority || undefined,
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
        per_page: 30,
      },
    })
    searchResults.value = listItems(response)
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
    direction: null,
    status: null,
    priority: null,
    from: '',
    to: '',
  }
  hasSearched.value = false
  searchResults.value = []
  showAdvanced.value = false
}

function senderLabel(item: any) {
  const from = partiesByRole(item.parties, 'from')[0]
  if (from)
    return partyDisplayName(from) || from.organization || '—'

  return item.structure?.name || item.external_reference || '—'
}

function openItem(item: any) {
  router.push({ name: detailRouteName(item.direction), params: { id: item.id } })
}
</script>

<template>
  <div class="courrier-dashboard">
    <ParapheurPageHeader
      title="Bureau d'ordre — Courrier"
      subtitle="Pilotage, recherche et suivi des flux entrants / sortants"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading || recentLoading"
          @click="() => Promise.all([loadDashboard(), loadRecentIncoming()])"
        >
          Actualiser
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'courrier-entrants-nouveau' }"
        >
          Enregistrer un entrant
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
            placeholder="N° ARR/DEP, objet, référence…"
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
                  v-model="filters.direction"
                  :items="directionItems"
                  label="Direction"
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
                  :items="statusItems"
                  label="Statut"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="2"
              >
                <AppSelect
                  v-model="filters.priority"
                  :items="priorityItems"
                  label="Priorité"
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
                  v-model="filters.from"
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

    <!-- Vues + actions rapides -->
    <VRow
      dense
      class="mb-4"
    >
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardText class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
            <div class="d-flex flex-wrap gap-2">
              <VBtn
                size="small"
                :variant="dashboardType === 'order-office' ? 'flat' : 'tonal'"
                :color="dashboardType === 'order-office' ? 'primary' : 'default'"
                @click="switchDashboard('order-office')"
              >
                Bureau d'ordre
              </VBtn>
              <VBtn
                size="small"
                :variant="dashboardType === 'dg' ? 'flat' : 'tonal'"
                :color="dashboardType === 'dg' ? 'primary' : 'default'"
                @click="switchDashboard('dg')"
              >
                Vue DG
              </VBtn>
              <VBtn
                size="small"
                :variant="dashboardType === 'direction' ? 'flat' : 'tonal'"
                :color="dashboardType === 'direction' ? 'primary' : 'default'"
                @click="switchDashboard('direction')"
              >
                Vue Direction
              </VBtn>
            </div>
            <span class="text-caption text-medium-emphasis">
              Indicateurs opérationnels du périmètre sélectionné
            </span>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
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
      </VCol>
    </VRow>

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
        :key="card.title"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="kpi-card cursor-pointer h-100"
          @click="router.push({ name: card.route })"
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
            color="info"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              :icon="hasSearched ? 'tabler-list-search' : 'tabler-mail-opened'"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ tableTitle }}
        </VCardTitle>
        <VCardSubtitle>
          {{ hasSearched
            ? 'Correspondances trouvées selon vos critères'
            : 'Les 10 derniers enregistrements d\'arrivée' }}
        </VCardSubtitle>
        <template #append>
          <VBtn
            v-if="!hasSearched"
            size="small"
            variant="tonal"
            color="primary"
            :to="{ name: 'courrier-entrants' }"
          >
            Voir tous les entrants
          </VBtn>
          <VBtn
            v-else
            size="small"
            variant="text"
            @click="resetSearch"
          >
            Revenir aux derniers
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
        @click:row="(_: any, { item }: any) => openItem(item)"
      >
        <template #item.number="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: detailRouteName(item.direction), params: { id: item.id } }"
            @click.stop
          >
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
          <div
            v-if="hasSearched && item.direction"
            class="text-caption text-medium-emphasis"
          >
            {{ correspondenceDirectionLabels[item.direction] || item.direction }}
          </div>
        </template>

        <template #item.subject="{ item }">
          <div class="text-wrap subject-cell">
            {{ item.subject || '—' }}
          </div>
        </template>

        <template #item.sender="{ item }">
          <span class="text-body-2">{{ senderLabel(item) }}</span>
        </template>

        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="correspondencePriorityColors[item.priority] || 'default'"
            variant="tonal"
          >
            {{ correspondencePriorityLabels[item.priority] || item.priority || '—' }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status, item.direction) }}
          </VChip>
        </template>

        <template #item.received_at="{ item }">
          {{ item.received_at
            ? formatCourrierDateTime(item.received_at)
            : formatCourrierDate(item.correspondence_date) }}
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon
              icon="tabler-mail-off"
              size="40"
              class="mb-2"
            />
            <div>
              {{ hasSearched
                ? 'Aucun courrier ne correspond à votre recherche.'
                : 'Aucun courrier entrant récent.' }}
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
