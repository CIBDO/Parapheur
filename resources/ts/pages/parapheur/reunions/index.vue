<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'
import { meetingStatusColor, meetingStatusLabels } from '@/utils/meetingsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
  },
})

const ability = useAbility()
const router = useRouter()

const meetings = ref<any[]>([])
const stats = ref<any>(null)
const loading = ref(false)
const showAdvanced = ref(false)
const scope = ref('upcoming')
const simpleQuery = ref('')
const statusFilter = ref<string | null>(null)
const dateFrom = ref('')
const dateTo = ref('')

const primaryScopes = [
  { value: 'upcoming', title: 'À venir', icon: 'tabler-calendar-event', color: 'primary', hint: 'Prochaines séances' },
  { value: 'today', title: 'Aujourd’hui', icon: 'tabler-calendar-time', color: 'info', hint: 'Séances du jour' },
  { value: 'preparation', title: 'À préparer', icon: 'tabler-clipboard-list', color: 'warning', hint: 'Convocations / prep.' },
  { value: 'minutes', title: 'Comptes rendus', icon: 'tabler-file-check', color: 'secondary', hint: 'CR en cours' },
] as const

const secondaryScopes = [
  { value: 'week', title: 'Cette semaine' },
  { value: 'in_progress', title: 'En cours' },
  { value: 'mine', title: 'Mes réunions' },
]

const statusItems = computed(() => [
  { value: null, title: 'Tous les statuts' },
  ...Object.entries(meetingStatusLabels).map(([value, title]) => ({ value, title })),
])

const cards = computed(() => {
  const countFor = (key: string) => {
    if (!stats.value)
      return 0
    if (key === 'upcoming')
      return stats.value.this_week ?? 0
    if (key === 'today')
      return stats.value.today ?? 0
    if (key === 'preparation')
      return stats.value.in_preparation ?? 0
    if (key === 'minutes')
      return stats.value.minutes_to_validate ?? 0

    return 0
  }

  return primaryScopes.map(item => ({
    key: item.value,
    title: item.title,
    icon: item.icon,
    color: item.color,
    hint: item.hint,
    value: countFor(item.value),
  }))
})

const secondaryStats = computed(() => {
  if (!stats.value)
    return []

  return [
    { title: 'Décisions ouvertes', value: stats.value.decisions_open ?? 0, icon: 'tabler-gavel', color: 'primary' },
    { title: 'Décisions en retard', value: stats.value.decisions_late ?? 0, icon: 'tabler-alert-triangle', color: 'error' },
  ]
})

const activeFilterCount = computed(() => {
  let n = 0
  if (statusFilter.value)
    n++
  if (dateFrom.value)
    n++
  if (dateTo.value)
    n++

  return n
})

const headers = [
  { title: 'Référence', key: 'reference', width: '130px' },
  { title: 'Objet', key: 'object' },
  { title: 'Date', key: 'meeting_date', width: '140px' },
  { title: 'Lieu', key: 'location', width: '140px' },
  { title: 'Statut', key: 'status', width: '140px' },
  { title: 'Participants', key: 'participants_count', width: '110px' },
]

const load = async () => {
  loading.value = true
  try {
    const query: Record<string, unknown> = { per_page: 30 }
    if (scope.value === 'mine')
      query.mine = 1
    else if (scope.value)
      query.scope = scope.value
    if (simpleQuery.value.trim())
      query.q = simpleQuery.value.trim()
    if (statusFilter.value)
      query.status = statusFilter.value
    if (dateFrom.value)
      query.from = dateFrom.value
    if (dateTo.value)
      query.to = dateTo.value

    const [list, dash] = await Promise.all([
      $api('/meetings', { query }),
      $api('/meetings/dashboard'),
    ])
    meetings.value = list.data ?? list
    stats.value = dash
  }
  finally {
    loading.value = false
  }
}

function selectScope(value: string) {
  scope.value = value
}

function resetFilters() {
  simpleQuery.value = ''
  statusFilter.value = null
  dateFrom.value = ''
  dateTo.value = ''
  showAdvanced.value = false
  load()
}

function openMeeting(item: any) {
  router.push({ name: 'parapheur-reunions-id', params: { id: item.id } })
}

watch([scope], load)
onMounted(load)
</script>

<template>
  <div class="reunions-dashboard">
    <ParapheurPageHeader
      title="Réunions"
      subtitle="Pilotage, recherche et suivi des séances et décisions"
      icon="tabler-users-group"
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
          :to="{ name: 'parapheur-reunions-calendrier' }"
          prepend-icon="tabler-calendar"
        >
          Calendrier
        </VBtn>
        <VBtn
          variant="tonal"
          :to="{ name: 'parapheur-reunions-decisions' }"
          prepend-icon="tabler-gavel"
        >
          Décisions
        </VBtn>
        <VBtn
          v-if="ability.can('create', 'Meeting') || ability.can('manage', 'Meeting')"
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'parapheur-reunions-nouvelle' }"
        >
          Nouvelle réunion
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
            placeholder="Référence, objet, titre…"
            prepend-inner-icon="tabler-search"
            hide-details
            @keyup.enter="load"
          />
          <VBtn
            color="primary"
            prepend-icon="tabler-search"
            :loading="loading"
            @click="load"
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
            v-if="simpleQuery || activeFilterCount"
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
                md="4"
              >
                <AppSelect
                  v-model="statusFilter"
                  :items="statusItems"
                  label="Statut"
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
                  v-model="dateFrom"
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
                  v-model="dateTo"
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
                :loading="loading"
                @click="load"
              >
                Appliquer les filtres
              </VBtn>
            </div>
          </VCardText>
        </div>
      </VExpandTransition>
    </VCard>

    <!-- Scopes secondaires + décisions -->
    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap align-center gap-2 py-3">
        <VChip
          v-for="item in secondaryScopes"
          :key="item.value"
          :color="scope === item.value ? 'primary' : undefined"
          :variant="scope === item.value ? 'flat' : 'tonal'"
          class="cursor-pointer"
          @click="selectScope(item.value)"
        >
          {{ item.title }}
        </VChip>
        <VSpacer />
        <VChip
          v-for="item in secondaryStats"
          :key="item.title"
          :color="item.color"
          variant="tonal"
          size="small"
          :prepend-icon="item.icon"
          class="cursor-pointer"
          :to="{ name: 'parapheur-reunions-decisions' }"
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
        :key="card.key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="kpi-card cursor-pointer h-100"
          :class="{ 'kpi-card--active': scope === card.key }"
          @click="selectScope(card.key)"
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
            color="primary"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              icon="tabler-users-group"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          {{ primaryScopes.find(s => s.value === scope)?.title
            || secondaryScopes.find(s => s.value === scope)?.title
            || 'Réunions' }}
        </VCardTitle>
        <VCardSubtitle>
          {{ meetings.length }} réunion{{ meetings.length > 1 ? 's' : '' }}
        </VCardSubtitle>
        <template #append>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            :to="{ name: 'parapheur-reunions-calendrier' }"
          >
            Voir le calendrier
          </VBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VDataTable
        :headers="headers"
        :items="meetings"
        :loading="loading"
        item-value="id"
        hover
        class="text-no-wrap"
        @click:row="(_: any, { item }: any) => openMeeting(item)"
      >
        <template #item.reference="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: 'parapheur-reunions-id', params: { id: item.id } }"
            @click.stop
          >
            {{ item.reference || `#${item.id}` }}
          </RouterLink>
        </template>

        <template #item.object="{ item }">
          <div class="text-wrap subject-cell font-weight-medium">
            {{ item.object || item.title || '—' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            Président : {{ item.chair?.name || '—' }}
          </div>
        </template>

        <template #item.meeting_date="{ item }">
          {{ formatDateFr(item.meeting_date) }}
          <span
            v-if="item.meeting_time"
            class="text-medium-emphasis"
          > · {{ item.meeting_time }}</span>
        </template>

        <template #item.location="{ item }">
          {{ item.location || '—' }}
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="meetingStatusColor(item.status)"
            variant="tonal"
          >
            {{ item.status_label || meetingStatusLabels[item.status] || item.status }}
          </VChip>
        </template>

        <template #item.participants_count="{ item }">
          {{ item.participants_count || 0 }}
          <span class="text-caption text-medium-emphasis">
            · {{ item.decisions_count || 0 }} déc.
          </span>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon
              icon="tabler-calendar-off"
              size="40"
              class="mb-2"
            />
            <div>
              Aucune réunion pour ce filtre.
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
  max-inline-size: 360px;
  white-space: normal;
}
</style>
