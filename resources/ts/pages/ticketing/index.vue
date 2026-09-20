<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import {
  formatTicketDateTime,
  formatTicketNumber,
  listItems,
  slaBadge,
  ticketPriorityColor,
  ticketPriorityLabel,
  ticketStatusColor,
  ticketStatusLabel,
  ticketStatusLabels,
} from '@/utils/ticketingUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Ticketing',
  },
})

const router = useRouter()
const {
  fetchDashboardRequester,
  fetchDashboardAgent,
  fetchMeta,
} = useTicketing()

const loading = ref(true)
const recentLoading = ref(true)
const searchLoading = ref(false)
const hasAgentView = ref(false)
const showAdvanced = ref(false)
const hasSearched = ref(false)

const requesterStats = ref<Record<string, number>>({})
const agentStats = ref<Record<string, number>>({})
const recentTickets = ref<any[]>([])
const searchResults = ref<any[]>([])
const priorities = ref<any[]>([])

const simpleQuery = ref('')
const filters = ref({
  q: '',
  status: null as string | null,
  priority_id: null as number | null,
  from: '',
  to: '',
})

const statusItems = computed(() => [
  { value: null, title: 'Tous les statuts' },
  ...Object.entries(ticketStatusLabels).map(([value, title]) => ({ value, title })),
])

const priorityItems = computed(() => [
  { value: null, title: 'Toutes priorités' },
  ...priorities.value.map(p => ({ value: p.id, title: p.name })),
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.status)
    n++
  if (filters.value.priority_id)
    n++
  if (filters.value.from)
    n++
  if (filters.value.to)
    n++

  return n
})

const requesterCards = computed(() => [
  { title: 'Ouverts', value: requesterStats.value.open || 0, icon: 'tabler-ticket', color: 'primary', route: 'ticketing-mes-tickets', hint: 'Mes tickets actifs' },
  { title: 'En cours', value: requesterStats.value.in_progress || 0, icon: 'tabler-loader', color: 'warning', route: 'ticketing-mes-tickets', hint: 'Traitement en cours' },
  { title: 'En attente de moi', value: requesterStats.value.waiting_on_me || 0, icon: 'tabler-user-question', color: 'info', route: 'ticketing-mes-tickets', hint: 'Réponse requise' },
  { title: 'Résolus (14 j)', value: requesterStats.value.resolved_recent || 0, icon: 'tabler-circle-check', color: 'success', route: 'ticketing-mes-tickets', hint: 'Récemment résolus' },
])

const agentCards = computed(() => [
  { title: 'Affectés à moi', value: agentStats.value.assigned || 0, icon: 'tabler-user-check', color: 'primary', route: 'ticketing-file', hint: 'Ma file' },
  { title: 'Non pris en charge', value: agentStats.value.not_taken || 0, icon: 'tabler-hand-click', color: 'warning', route: 'ticketing-file', hint: 'À démarrer' },
  { title: 'SLA en alerte', value: agentStats.value.sla_warning || 0, icon: 'tabler-alert-triangle', color: 'orange', route: 'ticketing-file', hint: 'Délai proche' },
  { title: 'SLA dépassé', value: agentStats.value.sla_breached || 0, icon: 'tabler-alert-octagon', color: 'error', route: 'ticketing-recherche', hint: 'À prioriser' },
])

const quickActions = [
  { title: 'Nouvel incident', icon: 'tabler-alert-triangle', color: 'primary', route: 'ticketing-nouveau' },
  { title: 'Nouvelle demande', icon: 'tabler-file-plus', color: 'success', route: 'ticketing-demande' },
  { title: 'Catalogue', icon: 'tabler-category', color: 'info', route: 'ticketing-catalogue' },
  { title: 'Kanban', icon: 'tabler-layout-kanban', color: 'warning', route: 'ticketing-kanban' },
]

const tableItems = computed(() =>
  hasSearched.value ? searchResults.value : recentTickets.value,
)

const tableTitle = computed(() =>
  hasSearched.value
    ? `Résultats (${searchResults.value.length})`
    : 'Tickets récents',
)

const recentHeaders = [
  { title: 'N°', key: 'number', width: '140px' },
  { title: 'Titre', key: 'title' },
  { title: 'Statut', key: 'status', width: '150px' },
  { title: 'Priorité', key: 'priority', width: '120px' },
  { title: 'SLA', key: 'sla', width: '120px' },
  { title: 'Créé le', key: 'created_at', width: '150px' },
]

watch(simpleQuery, (value) => {
  filters.value.q = value
})

onMounted(async () => {
  await Promise.all([loadDashboard(), loadRecent(), loadMeta()])
})

async function loadMeta() {
  try {
    const meta = await fetchMeta()
    priorities.value = meta?.priorities || []
  }
  catch {
    priorities.value = []
  }
}

async function loadDashboard() {
  loading.value = true
  try {
    const [requester, agent] = await Promise.allSettled([
      fetchDashboardRequester(),
      fetchDashboardAgent(),
    ])
    if (requester.status === 'fulfilled')
      requesterStats.value = requester.value || {}
    if (agent.status === 'fulfilled') {
      agentStats.value = agent.value || {}
      hasAgentView.value = true
    }
  }
  finally {
    loading.value = false
  }
}

async function loadRecent() {
  recentLoading.value = true
  try {
    const response = await $api('/ticketing/tickets', {
      query: { mine: 1, per_page: 10 },
    })
    recentTickets.value = listItems(response)
  }
  catch {
    recentTickets.value = []
  }
  finally {
    recentLoading.value = false
  }
}

async function refreshAll() {
  await Promise.all([loadDashboard(), loadRecent()])
}

async function performSearch() {
  const q = (simpleQuery.value || filters.value.q || '').trim()
  filters.value.q = q
  simpleQuery.value = q
  searchLoading.value = true
  hasSearched.value = true
  try {
    const response = await $api('/ticketing/tickets/search', {
      query: {
        q: q || undefined,
        status: filters.value.status || undefined,
        priority_id: filters.value.priority_id || undefined,
        created_from: filters.value.from || undefined,
        created_to: filters.value.to || undefined,
        per_page: 30,
      },
    })
    searchResults.value = listItems(response)
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
    status: null,
    priority_id: null,
    from: '',
    to: '',
  }
  hasSearched.value = false
  showAdvanced.value = false
  searchResults.value = []
}

function openTicket(item: any) {
  router.push({ name: 'ticketing-id', params: { id: item.id } })
}
</script>

<template>
  <div class="ticketing-dashboard">
    <ParapheurPageHeader
      title="Centre de services"
      subtitle="Pilotage, recherche et suivi des incidents et demandes"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading || recentLoading"
          @click="refreshAll"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          :to="{ name: 'ticketing-demande' }"
        >
          Nouvelle demande
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'ticketing-nouveau' }"
        >
          Nouvel incident
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
            placeholder="N° ticket, titre, description…"
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
                  v-model="filters.status"
                  :items="statusItems"
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
                  v-model="filters.priority_id"
                  :items="priorityItems"
                  label="Priorité"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="3"
              >
                <AppTextField
                  v-model="filters.from"
                  type="date"
                  label="Créé du"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                md="3"
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
        <VSpacer />
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'ticketing-mes-tickets' }"
        >
          Mes tickets
        </VBtn>
        <VBtn
          v-if="hasAgentView"
          size="small"
          variant="text"
          :to="{ name: 'ticketing-file' }"
        >
          Ma file
        </VBtn>
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'ticketing-recherche' }"
        >
          Recherche avancée
        </VBtn>
      </VCardText>
    </VCard>

    <!-- KPI demandeur -->
    <div
      v-if="loading"
      class="text-center py-8"
    >
      <VProgressCircular indeterminate />
    </div>
    <template v-else>
      <div class="text-subtitle-2 text-medium-emphasis mb-3">
        Mes indicateurs
      </div>
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          v-for="card in requesterCards"
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

      <template v-if="hasAgentView">
        <div class="text-subtitle-2 text-medium-emphasis mb-3">
          File de travail
        </div>
        <VRow
          dense
          class="mb-4"
        >
          <VCol
            v-for="card in agentCards"
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
      </template>
    </template>

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
              :icon="hasSearched ? 'tabler-list-search' : 'tabler-ticket'"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ tableTitle }}
        </VCardTitle>
        <VCardSubtitle>
          {{ hasSearched
            ? 'Tickets trouvés selon vos critères'
            : 'Vos 10 derniers tickets (demandeur ou assigné)' }}
        </VCardSubtitle>
        <template #append>
          <VBtn
            v-if="!hasSearched"
            size="small"
            variant="tonal"
            color="primary"
            :to="{ name: 'ticketing-mes-tickets' }"
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
        @click:row="(_: any, { item }: any) => openTicket(item)"
      >
        <template #item.number="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: 'ticketing-id', params: { id: item.id } }"
            @click.stop
          >
            {{ formatTicketNumber(item) }}
          </RouterLink>
        </template>
        <template #item.title="{ item }">
          <div class="text-body-2 font-weight-medium text-truncate" style="max-inline-size: 320px">
            {{ item.title }}
          </div>
          <div
            v-if="item.requester?.name || item.assignee?.name"
            class="text-caption text-medium-emphasis"
          >
            {{ item.requester?.name || '—' }}
            <template v-if="item.assignee?.name">
              → {{ item.assignee.name }}
            </template>
          </div>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="ticketStatusColor(item.status)"
            variant="tonal"
          >
            {{ item.status_label || ticketStatusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.priority="{ item }">
          <VChip
            v-if="item.priority"
            size="small"
            :color="ticketPriorityColor(item.priority)"
            variant="tonal"
          >
            {{ ticketPriorityLabel(item.priority) }}
          </VChip>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>
        <template #item.sla="{ item }">
          <VChip
            size="small"
            :color="slaBadge(item.sla).color"
            variant="tonal"
          >
            {{ slaBadge(item.sla).label }}
          </VChip>
        </template>
        <template #item.created_at="{ item }">
          {{ formatTicketDateTime(item.created_at) }}
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            {{ hasSearched ? 'Aucun ticket ne correspond à votre recherche.' : 'Aucun ticket pour le moment.' }}
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
  min-inline-size: 220px;
}

.kpi-card {
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.kpi-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  border-color: rgba(var(--v-theme-primary), 0.35);
}
</style>
