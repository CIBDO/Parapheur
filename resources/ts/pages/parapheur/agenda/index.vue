<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { appointmentStatusColor, appointmentStatusLabel, formatAppointmentSlot } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const ability = useAbility()
const router = useRouter()

const stats = ref<any>(null)
const loading = ref(false)
const searchLoading = ref(false)
const showAdvanced = ref(false)
const hasSearched = ref(false)
const searchResults = ref<any[]>([])
const simpleQuery = ref('')

const filters = ref({
  q: '',
  status: null as string | null,
  priority: null as string | null,
  today: false,
  mine: false,
  to_validate: false,
})

const statusItems = [
  { value: null, title: 'Tous les statuts' },
  { value: 'a_examiner', title: 'À examiner' },
  { value: 'en_attente', title: 'En attente' },
  { value: 'a_valider', title: 'À valider' },
  { value: 'confirme', title: 'Confirmé' },
  { value: 'refuse', title: 'Refusé' },
  { value: 'annule', title: 'Annulé' },
]

const priorityItems = [
  { value: null, title: 'Toutes priorités' },
  { value: 'normale', title: 'Normale' },
  { value: 'importante', title: 'Importante' },
  { value: 'urgente', title: 'Urgente' },
]

const cards = computed(() => {
  if (!stats.value)
    return [
      { title: 'RDV aujourd’hui', value: 0, icon: 'tabler-calendar-event', color: 'primary', hint: 'Planifiés ce jour', route: 'parapheur-agenda-calendrier' },
      { title: 'À examiner', value: 0, icon: 'tabler-inbox', color: 'warning', hint: 'File secrétariat', route: 'parapheur-agenda-demandes' },
      { title: 'À valider DG', value: 0, icon: 'tabler-checks', color: 'error', hint: 'Validation requise', route: 'parapheur-agenda-avalider' },
      { title: 'En attente', value: 0, icon: 'tabler-hourglass', color: 'secondary', hint: 'Créneau / confirmation', route: 'parapheur-agenda-demandes' },
    ]

  return [
    { title: 'RDV aujourd’hui', value: stats.value.today?.appointments ?? 0, icon: 'tabler-calendar-event', color: 'primary', hint: 'Planifiés ce jour', route: 'parapheur-agenda-calendrier' },
    { title: 'À examiner', value: stats.value.requests?.to_examine ?? 0, icon: 'tabler-inbox', color: 'warning', hint: 'File secrétariat', route: 'parapheur-agenda-demandes' },
    { title: 'À valider DG', value: stats.value.requests?.to_validate ?? 0, icon: 'tabler-checks', color: 'error', hint: 'Validation requise', route: 'parapheur-agenda-avalider' },
    { title: 'En attente', value: stats.value.requests?.pending ?? 0, icon: 'tabler-hourglass', color: 'secondary', hint: 'Créneau / confirmation', route: 'parapheur-agenda-demandes' },
  ]
})

const secondaryStats = computed(() => {
  if (!stats.value)
    return []

  return [
    { title: 'Audiences', value: stats.value.today?.audiences ?? 0, icon: 'tabler-user-star' },
    { title: 'Réunions', value: stats.value.today?.meetings ?? 0, icon: 'tabler-users-group' },
    { title: 'Mes demandes', value: stats.value.my_requests?.total ?? 0, icon: 'tabler-user-check' },
  ]
})

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.status)
    n++
  if (filters.value.priority)
    n++
  if (filters.value.today)
    n++
  if (filters.value.mine)
    n++
  if (filters.value.to_validate)
    n++

  return n
})

const tableItems = computed(() =>
  hasSearched.value ? searchResults.value : (stats.value?.today_list || []),
)

const tableTitle = computed(() =>
  hasSearched.value
    ? `Résultats de recherche (${searchResults.value.length})`
    : 'Rendez-vous aujourd’hui',
)

const headers = [
  { title: 'Horaire', key: 'start_at', width: '160px' },
  { title: 'Objet', key: 'subject' },
  { title: 'Demandeur', key: 'requester', width: '180px' },
  { title: 'Durée', key: 'duration_minutes', width: '90px' },
  { title: 'Statut', key: 'status', width: '130px' },
]

const quickActions = computed(() => {
  const actions = [
    { title: 'Agenda', icon: 'tabler-calendar', color: 'primary', route: 'parapheur-agenda-calendrier' },
    { title: 'Demandes', icon: 'tabler-inbox', color: 'warning', route: 'parapheur-agenda-demandes' },
    { title: 'Mes RDV', icon: 'tabler-user-check', color: 'info', route: 'parapheur-agenda-mes-rdv' },
  ]
  if (ability.can('validate', 'Appointment')) {
    actions.push({ title: 'Validations DG', icon: 'tabler-checks', color: 'error', route: 'parapheur-agenda-avalider' })
  }

  return actions
})

watch(simpleQuery, (value) => {
  filters.value.q = value
})

const load = async () => {
  loading.value = true
  try {
    stats.value = await $api('/appointments/dashboard')
  }
  finally {
    loading.value = false
  }
}

async function performSearch() {
  const q = (simpleQuery.value || filters.value.q || '').trim()
  filters.value.q = q
  simpleQuery.value = q

  searchLoading.value = true
  hasSearched.value = true
  try {
    const query: Record<string, unknown> = { per_page: 30 }
    if (q)
      query.q = q
    if (filters.value.status)
      query.status = filters.value.status
    if (filters.value.priority)
      query.priority = filters.value.priority
    if (filters.value.today)
      query.today = 1
    if (filters.value.mine)
      query.mine = 1
    if (filters.value.to_validate)
      query.to_validate = 1

    const res = await $api('/appointments', { query })
    searchResults.value = res.data ?? res
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
    status: null,
    priority: null,
    today: false,
    mine: false,
    to_validate: false,
  }
  hasSearched.value = false
  searchResults.value = []
  showAdvanced.value = false
}

function openItem(item: any) {
  router.push({ name: 'parapheur-agenda-id', params: { id: item.id } })
}

function formatTime(value?: string | null) {
  if (!value)
    return '—'

  return new Date(value).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>

<template>
  <div class="agenda-dashboard">
    <ParapheurPageHeader
      title="Agenda & Rendez-vous"
      subtitle="Pilotage, recherche et suivi des audiences et créneaux"
      icon="tabler-calendar-event"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-calendar"
          :to="{ name: 'parapheur-agenda-calendrier' }"
        >
          Agenda
        </VBtn>
        <VBtn
          v-if="ability.can('create', 'Appointment') || ability.can('manage', 'Appointment')"
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'parapheur-agenda-nouveau' }"
        >
          Enregistrer / Demander
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
            placeholder="Objet, référence, demandeur…"
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
                  v-model="filters.status"
                  :items="statusItems"
                  label="Statut"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
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
                md="4"
                class="d-flex flex-wrap align-center gap-3"
              >
                <VCheckbox
                  v-model="filters.today"
                  label="Aujourd’hui"
                  hide-details
                  density="compact"
                />
                <VCheckbox
                  v-model="filters.mine"
                  label="Mes demandes"
                  hide-details
                  density="compact"
                />
                <VCheckbox
                  v-model="filters.to_validate"
                  label="À valider"
                  hide-details
                  density="compact"
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

    <VAlert
      v-if="stats?.dg"
      type="info"
      variant="tonal"
      class="mb-4"
      title="Agenda du Directeur"
    >
      Aujourd’hui : {{ stats.dg.today }} · Prochain RDV : {{ stats.dg.next_appointment_at || '—' }} · À valider : {{ stats.dg.to_validate }}
    </VAlert>

    <!-- Actions + stats secondaires -->
    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap align-center gap-2 py-3">
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
        <VChip
          v-for="item in secondaryStats"
          :key="item.title"
          variant="tonal"
          size="small"
          :prepend-icon="item.icon"
        >
          {{ item.title }} : {{ item.value }}
        </VChip>
      </VCardText>
    </VCard>

    <!-- KPI -->
    <div
      v-if="loading && !stats"
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

    <VRow dense>
      <VCol
        cols="12"
        lg="8"
      >
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
                  :icon="hasSearched ? 'tabler-list-search' : 'tabler-calendar-time'"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              {{ tableTitle }}
            </VCardTitle>
            <VCardSubtitle>
              {{ hasSearched
                ? 'Rendez-vous trouvés selon vos critères'
                : 'Créneaux planifiés pour aujourd’hui' }}
            </VCardSubtitle>
            <template #append>
              <VBtn
                v-if="!hasSearched"
                size="small"
                variant="tonal"
                color="primary"
                :to="{ name: 'parapheur-agenda-mes-rdv' }"
              >
                Voir mon suivi
              </VBtn>
              <VBtn
                v-else
                size="small"
                variant="text"
                @click="resetSearch"
              >
                Revenir à aujourd’hui
              </VBtn>
            </template>
          </VCardItem>

          <VDivider />

          <VDataTable
            :headers="headers"
            :items="tableItems"
            :loading="hasSearched ? searchLoading : loading"
            item-value="id"
            hover
            class="text-no-wrap"
            @click:row="(_: any, { item }: any) => openItem(item)"
          >
            <template #item.start_at="{ item }">
              <div class="font-weight-medium">
                {{ hasSearched ? formatAppointmentSlot(item.start_at, item.end_at) : formatTime(item.start_at) }}
              </div>
            </template>

            <template #item.subject="{ item }">
              <div class="text-wrap subject-cell font-weight-medium">
                {{ item.subject || '—' }}
              </div>
            </template>

            <template #item.requester="{ item }">
              {{ item.requester_name || item.requester_organization || '—' }}
            </template>

            <template #item.duration_minutes="{ item }">
              {{ item.duration_minutes ? `${item.duration_minutes} min` : '—' }}
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="appointmentStatusColor(item.status)"
                variant="tonal"
              >
                {{ appointmentStatusLabel(item.status) }}
              </VChip>
            </template>

            <template #no-data>
              <div class="text-center py-10 text-medium-emphasis">
                <VIcon
                  icon="tabler-calendar-off"
                  size="40"
                  class="mb-2"
                />
                <div>
                  {{ hasSearched
                    ? 'Aucun rendez-vous ne correspond à votre recherche.'
                    : 'Aucun rendez-vous planifié aujourd’hui.' }}
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
            <VCardTitle class="text-h6">
              Mes demandes
            </VCardTitle>
            <VCardSubtitle>
              Synthèse personnelle
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex justify-space-between mb-3">
              <span>Total</span>
              <strong>{{ stats?.my_requests?.total ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-3">
              <span>Confirmées</span>
              <strong class="text-success">{{ stats?.my_requests?.confirmed ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-3">
              <span>En attente</span>
              <strong class="text-warning">{{ stats?.my_requests?.pending ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-4">
              <span>Refusées</span>
              <strong class="text-error">{{ stats?.my_requests?.rejected ?? 0 }}</strong>
            </div>
            <VBtn
              block
              variant="tonal"
              color="primary"
              :to="{ name: 'parapheur-agenda-mes-rdv' }"
            >
              Voir mon suivi
            </VBtn>
          </VCardText>
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
  max-inline-size: 360px;
  white-space: normal;
}
</style>
