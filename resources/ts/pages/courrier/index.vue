<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CorrespondenceCreateDialog from '@/components/courrier/CorrespondenceCreateDialog.vue'
import CorrespondenceListActions from '@/components/courrier/CorrespondenceListActions.vue'
import { $api } from '@/utils/api'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { listItems } from '@/utils/listItems'
import { applyMailPriorities, correspondenceDirectionLabels, correspondencePriorityColors, correspondencePriorityLabels, correspondenceStatusColors, correspondenceStatusLabels, detailRouteName, formatCorrespondenceNumber, formatCourrierDate, formatCourrierDateTime, isCorrespondenceAssigned, partiesByRole, partyDisplayName, statusLabel } from '@/utils/courrierUi'

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
const listScope = ref<'entrant' | 'sortant'>('entrant')
const showAdvanced = ref(false)
const hasSearched = ref(false)
const recentIncoming = ref<any[]>([])
const recentOutgoing = ref<any[]>([])
const searchResults = ref<any[]>([])
const showCreate = ref(false)
const createDirection = ref<'entrant' | 'sortant'>('entrant')

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

const cards = computed(() => {
  if (dashboardType.value === 'dg') {
    return [
      { title: 'Pour moi', value: stats.value.for_me || 0, icon: 'tabler-user', color: 'primary', route: 'courrier-a-traiter', hint: 'Affectés à vous' },
      { title: 'Urgents', value: stats.value.urgent || 0, icon: 'tabler-flame', color: 'error', route: 'courrier-recherche', hint: 'Priorité haute' },
      { title: 'À valider / viser', value: stats.value.to_validate || 0, icon: 'tabler-stamp', color: 'warning', route: 'courrier-a-traiter', hint: 'Décision requise' },
      { title: 'À signer', value: stats.value.to_sign || 0, icon: 'tabler-writing-sign', color: 'info', route: 'courrier-sortants', hint: 'Signature' },
      { title: 'En retard', value: stats.value.overdue || 0, icon: 'tabler-alert-triangle', color: 'error', route: 'courrier-en-retard', hint: 'Échéance dépassée' },
      { title: 'Expédiés (7 j)', value: stats.value.recent_dispatched || 0, icon: 'tabler-send', color: 'success', route: 'courrier-sortants', hint: 'Départs récents' },
    ]
  }

  if (dashboardType.value === 'direction') {
    return [
      { title: 'Arrivés', value: stats.value.received || 0, icon: 'tabler-inbox', color: 'info', route: 'courrier-entrants', hint: 'Courriers entrants' },
      { title: 'Départs', value: stats.value.outgoing || 0, icon: 'tabler-send', color: 'success', route: 'courrier-sortants', hint: 'Courriers sortants' },
      { title: 'Internes', value: stats.value.internal || 0, icon: 'tabler-mail-forward', color: 'primary', route: 'courrier-internes', hint: 'Notes / internes' },
      { title: 'Non pris', value: stats.value.not_taken || 0, icon: 'tabler-user-question', color: 'warning', route: 'courrier-a-affecter', hint: 'À prendre en charge' },
      { title: 'En traitement', value: stats.value.in_processing || 0, icon: 'tabler-clock', color: 'primary', route: 'courrier-a-traiter', hint: 'En cours' },
      { title: 'En retard', value: stats.value.overdue || 0, icon: 'tabler-alert-triangle', color: 'error', route: 'courrier-en-retard', hint: 'Échéance dépassée' },
    ]
  }

  return [
    { title: 'Reçus aujourd\'hui', value: stats.value.received_today || 0, icon: 'tabler-inbox', color: 'info', route: 'courrier-entrants', hint: 'Arrivées du jour' },
    { title: 'À affecter', value: stats.value.to_assign || 0, icon: 'tabler-user-plus', color: 'warning', route: 'courrier-a-affecter', hint: 'Sans destinataire' },
    { title: 'En traitement', value: stats.value.in_processing || 0, icon: 'tabler-clock', color: 'primary', route: 'courrier-a-traiter', hint: 'En cours' },
    { title: 'À expédier', value: stats.value.to_dispatch || 0, icon: 'tabler-package', color: 'secondary', route: 'courrier-sortants', hint: 'Départs prêts' },
    { title: 'Expédiés aujourd\'hui', value: stats.value.dispatched_today || 0, icon: 'tabler-send', color: 'success', route: 'courrier-sortants', hint: 'Départs du jour' },
    { title: 'En retard', value: stats.value.overdue || 0, icon: 'tabler-alert-triangle', color: 'error', route: 'courrier-en-retard', hint: 'Échéance dépassée' },
  ]
})

const tableItems = computed(() => {
  if (hasSearched.value)
    return searchResults.value

  return listScope.value === 'sortant' ? recentOutgoing.value : recentIncoming.value
})

const tableTitle = computed(() => {
  if (hasSearched.value)
    return `Résultats de recherche (${searchResults.value.length})`

  return listScope.value === 'sortant'
    ? 'Derniers courriers de départ'
    : 'Derniers courriers arrivés'
})

const tableSubtitle = computed(() => {
  if (hasSearched.value)
    return 'Correspondances trouvées selon vos critères'

  return listScope.value === 'sortant'
    ? 'Les 10 derniers enregistrements de départ'
    : 'Les 10 derniers enregistrements d\'arrivée'
})

const partyColumnTitle = computed(() =>
  listScope.value === 'sortant' ? 'Destinataire' : 'Expéditeur',
)

const dateColumnTitle = computed(() =>
  listScope.value === 'sortant' ? 'Date' : 'Reçu le',
)

const recentHeaders = computed(() => {
  const headers = [
    { title: 'N°', key: 'number', width: '140px' },
    { title: 'Objet', key: 'subject' },
    { title: partyColumnTitle.value, key: 'sender', width: '180px' },
    { title: 'Priorité', key: 'priority', width: '120px' },
    { title: 'Statut', key: 'status', width: '140px' },
  ]

  if (listScope.value !== 'sortant' || hasSearched.value)
    headers.push({ title: 'Affectation', key: 'assignment', width: '140px' })

  headers.push(
    { title: dateColumnTitle.value, key: 'received_at', width: '150px' },
    { title: '', key: 'actions', width: '110px', sortable: false },
  )

  return headers
})

function openCreate(direction: 'entrant' | 'sortant') {
  createDirection.value = direction
  showCreate.value = true
}

async function onCreated() {
  showCreate.value = false
  await Promise.all([loadDashboard(), loadRecentLists()])
}

onMounted(async () => {
  $api('/mail/meta/priorities')
    .then(items => applyMailPriorities(Array.isArray(items) ? items : []))
    .catch(() => undefined)
  await Promise.all([loadDashboard(), loadRecentLists()])
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
  catch {
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
  try {
    const response = await $api('/mail/correspondences', {
      query: {
        direction: 'entrant',
        per_page: 10,
      },
    })
    recentIncoming.value = listItems(response)
  }
  catch {
    recentIncoming.value = []
  }
}

async function loadRecentOutgoing() {
  try {
    const response = await $api('/mail/correspondences', {
      query: {
        direction: 'sortant',
        per_page: 10,
      },
    })
    recentOutgoing.value = listItems(response)
  }
  catch {
    recentOutgoing.value = []
  }
}

async function loadRecentLists() {
  recentLoading.value = true
  try {
    await Promise.all([loadRecentIncoming(), loadRecentOutgoing()])
  }
  finally {
    recentLoading.value = false
  }
}

function setListScope(scope: unknown) {
  if (scope !== 'entrant' && scope !== 'sortant')
    return
  listScope.value = scope
  if (hasSearched.value)
    resetSearch()
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
  const role = item.direction === 'sortant' || listScope.value === 'sortant' ? 'to' : 'from'
  const party = partiesByRole(item.parties, role)[0]
  if (party)
    return partyDisplayName(party) || party.organization || '—'

  return item.structure?.name || item.external_reference || '—'
}

function openItem(item: any) {
  router.push({ name: detailRouteName(item.direction), params: { id: item.id } })
}

function rowProps({ item }: { item: any }) {
  const row = item?.raw ?? item
  if ((row?.direction === 'sortant' || listScope.value === 'sortant') && !hasSearched.value)
    return {}

  return {
    class: isCorrespondenceAssigned(row) ? 'courrier-row--assigned' : 'courrier-row--unassigned',
  }
}

async function refreshTable() {
  if (hasSearched.value)
    await performSearch()
  else
    await loadRecentLists()
}
</script>

<template>
  <div class="courrier-dashboard">
    <ParapheurPageHeader
      title="Bureau d'ordre — Courrier"
      subtitle="Pilotage, recherche et suivi des courriers arrivés et de départs"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading || recentLoading"
          @click="() => Promise.all([loadDashboard(), loadRecentLists()])"
        >
          Actualiser
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate('entrant')"
        >
          Courrier arrivé
        </VBtn>
        <VBtn
          color="success"
          prepend-icon="tabler-send"
          @click="openCreate('sortant')"
        >
          Courrier de départ
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
              size="small"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-mail-opened"
              :to="{ name: 'courrier-entrants' }"
            >
              Tous les arrivés
            </VBtn>
            <VBtn
              size="small"
              color="success"
              variant="tonal"
              prepend-icon="tabler-send"
              :to="{ name: 'courrier-sortants' }"
            >
              Tous les départs
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              prepend-icon="tabler-mail-forward"
              :to="{ name: 'courrier-internes' }"
            >
              Internes
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              prepend-icon="tabler-clipboard-list"
              :to="{ name: 'courrier-bordereaux' }"
            >
              Bordereaux
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
        md="4"
        lg="2"
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
            :color="hasSearched ? 'primary' : (listScope === 'sortant' ? 'success' : 'info')"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              :icon="hasSearched ? 'tabler-list-search' : (listScope === 'sortant' ? 'tabler-send' : 'tabler-mail-opened')"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ tableTitle }}
        </VCardTitle>
        <VCardSubtitle>
          {{ tableSubtitle }}
        </VCardSubtitle>
        <template
          v-if="hasSearched"
          #append
        >
          <VBtn
            size="small"
            variant="text"
            prepend-icon="tabler-arrow-left"
            @click="resetSearch"
          >
            Revenir aux derniers
          </VBtn>
        </template>
      </VCardItem>

      <template v-if="!hasSearched">
        <VDivider />
        <div class="list-scope-bar px-4">
          <VTabs
            :model-value="listScope"
            color="primary"
            density="comfortable"
            class="list-scope-tabs"
            @update:model-value="setListScope"
          >
            <VTab
              value="entrant"
              prepend-icon="tabler-mail-opened"
            >
              Arrivés
            </VTab>
            <VTab
              value="sortant"
              prepend-icon="tabler-send"
            >
              Départs
            </VTab>
          </VTabs>
        </div>
      </template>

      <VDivider />

      <VDataTable
        :headers="recentHeaders"
        :items="tableItems"
        :loading="hasSearched ? searchLoading : recentLoading"
        item-value="id"
        hover
        class="text-no-wrap courrier-list"
        :row-props="rowProps"
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

        <template #item.assignment="{ item }">
          <VChip
            size="small"
            :color="isCorrespondenceAssigned(item) ? 'success' : 'warning'"
            :prepend-icon="isCorrespondenceAssigned(item) ? 'tabler-user-check' : 'tabler-user-question'"
            variant="flat"
          >
            {{ isCorrespondenceAssigned(item) ? 'Affecté' : 'Non affecté' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <CorrespondenceListActions
            :item="item"
            @imputed="refreshTable"
          />
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
                : (listScope === 'sortant'
                  ? 'Aucun courrier de départ récent.'
                  : 'Aucun courrier entrant récent.') }}
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <CorrespondenceCreateDialog
      v-model="showCreate"
      :direction="createDirection"
      @created="onCreated"
    />
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

.list-scope-bar {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.list-scope-tabs {
  min-block-size: 48px;
}

.list-scope-tabs :deep(.v-btn) {
  letter-spacing: normal;
  text-transform: none;
  font-weight: 600;
}

.courrier-list :deep(tr.courrier-row--assigned) {
  background: rgba(var(--v-theme-success), 0.1);
  box-shadow: inset 4px 0 0 rgb(var(--v-theme-success));
}

.courrier-list :deep(tr.courrier-row--unassigned) {
  background: rgba(var(--v-theme-warning), 0.14);
  box-shadow: inset 4px 0 0 rgb(var(--v-theme-warning));
}
</style>
