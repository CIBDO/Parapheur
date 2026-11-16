<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { calendarEventColor, findDirectorGeneral } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const view = ref<'month' | 'week' | 'day'>('day')
const layoutMode = ref<'cards' | 'grid'>('cards')
const cursor = ref(new Date())
const events = ref<any[]>([])
const types = ref<any[]>([])
const users = ref<any[]>([])
const structures = ref<any[]>([])
const search = ref('')
const hours = Array.from({ length: 12 }, (_, i) => i + 7) // 7h → 18h

const filters = ref({
  appointment_type_id: null as number | null,
  status: null as string | null,
  confidentiality: null as string | null,
  meeting_mode: null as string | null,
  director_id: null as number | null,
  structure_id: null as number | null,
  include_appointments: true,
  include_meetings: true,
  include_unavailabilities: true,
})

const statusItems = [
  { title: 'Confirmé', value: 'confirme' },
  { title: 'Validé', value: 'valide' },
  { title: 'À valider', value: 'a_valider' },
  { title: 'Prêt', value: 'pret' },
  { title: 'En cours', value: 'en_cours' },
  { title: 'Reporté', value: 'reporte' },
]

const confidentialityItems = [
  { title: 'Normal', value: 'normal' },
  { title: 'Restreint', value: 'restreint' },
  { title: 'Confidentiel', value: 'confidentiel' },
  { title: 'Très confidentiel', value: 'tres_confidentiel' },
]

const modeItems = [
  { title: 'Présentiel', value: 'presentiel' },
  { title: 'Visioconférence', value: 'visioconference' },
  { title: 'Téléphone', value: 'telephone' },
  { title: 'Hybride', value: 'hybride' },
  { title: 'Externe', value: 'externe' },
]

const fromTo = computed(() => {
  const start = new Date(cursor.value)
  const end = new Date(cursor.value)
  if (view.value === 'day')
    return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) }

  if (view.value === 'week') {
    const day = start.getDay() || 7
    start.setDate(start.getDate() - day + 1)
    end.setTime(start.getTime())
    end.setDate(start.getDate() + 6)
  }
  else {
    start.setDate(1)
    end.setMonth(end.getMonth() + 1, 0)
  }

  return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) }
})

const days = computed(() => {
  const start = new Date(`${fromTo.value.from}T00:00:00`)
  const end = new Date(`${fromTo.value.to}T00:00:00`)
  const list: Date[] = []
  const cur = new Date(start)
  while (cur <= end) {
    list.push(new Date(cur))
    cur.setDate(cur.getDate() + 1)
  }

  return list
})

const eventsByDay = computed(() => {
  const map: Record<string, any[]> = {}
  for (const event of events.value) {
    const key = event.date
    map[key] = map[key] || []
    map[key].push(event)
  }

  return map
})

const load = async () => {
  const query: Record<string, unknown> = {
    ...fromTo.value,
    include_appointments: filters.value.include_appointments ? 1 : 0,
    include_meetings: filters.value.include_meetings ? 1 : 0,
    include_unavailabilities: filters.value.include_unavailabilities ? 1 : 0,
  }

  if (filters.value.appointment_type_id)
    query.appointment_type_id = filters.value.appointment_type_id
  if (filters.value.status)
    query.status = filters.value.status
  if (filters.value.confidentiality)
    query.confidentiality = filters.value.confidentiality
  if (filters.value.meeting_mode)
    query.meeting_mode = filters.value.meeting_mode
  if (filters.value.director_id)
    query.director_id = filters.value.director_id
  if (filters.value.structure_id)
    query.structure_id = filters.value.structure_id
  if (search.value.trim())
    query.q = search.value.trim()

  events.value = await $api('/appointments/calendar', { query })
}

const shift = (delta: number) => {
  const next = new Date(cursor.value)
  if (view.value === 'day')
    next.setDate(next.getDate() + delta)
  else if (view.value === 'week')
    next.setDate(next.getDate() + (delta * 7))
  else
    next.setMonth(next.getMonth() + delta)
  cursor.value = next
}

const openEvent = (event: any) => {
  if (!event.url || event.masked)
    return
  window.location.href = event.url
}

const createAt = (day: Date, hour?: number) => {
  const d = new Date(day)
  d.setHours(hour ?? 9, 0, 0, 0)
  const pad = (n: number) => String(n).padStart(2, '0')
  const start = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
  window.location.href = `/parapheur/agenda/nouveau?start_at=${encodeURIComponent(start)}`
}

const eventHour = (event: any) => {
  if (!event.time)
    return 9
  const [h] = String(event.time).split(':')
  const n = Number(h)

  return Number.isFinite(n) ? n : 9
}

const eventsForHour = (dayKey: string, hour: number) =>
  (eventsByDay.value[dayKey] || []).filter((e: any) => eventHour(e) === hour)

const downloadFeed = async () => {
  const res = await fetch('/api/calendar/ical', {
    credentials: 'include',
    headers: { Accept: 'text/calendar', 'X-Requested-With': 'XMLHttpRequest' },
  })
  if (!res.ok)
    return
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'agenda-parapheur.ics'
  a.click()
  URL.revokeObjectURL(url)
}

const resetFilters = () => {
  filters.value = {
    appointment_type_id: null,
    status: null,
    confidentiality: null,
    meeting_mode: null,
    director_id: null,
    structure_id: null,
    include_appointments: true,
    include_meetings: true,
    include_unavailabilities: true,
  }
  search.value = ''
}

watch([view, cursor, filters], load, { deep: true })

onMounted(async () => {
  const [t, u, s] = await Promise.all([
    $api('/appointments/types'),
    $api('/meta/users'),
    $api('/meta/structures'),
  ])
  types.value = t
  users.value = u
  structures.value = s
  const dg = findDirectorGeneral(u)
  if (dg)
    filters.value.director_id = dg.id
  await load()
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Agenda du Directeur Général"
      subtitle="Rendez-vous, audiences, réunions et indisponibilités — vue unifiée"
      icon="tabler-calendar"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          to="/parapheur/agenda/nouveau"
        >
          Nouveau RDV
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          @click="downloadFeed"
        >
          iCal
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap align-center gap-3 py-3">
        <VBtnToggle
          v-model="view"
          mandatory
          density="comfortable"
          variant="tonal"
          divided
          class="flex-shrink-0"
        >
          <VBtn value="day">
            Jour
          </VBtn>
          <VBtn value="week">
            Semaine
          </VBtn>
          <VBtn value="month">
            Mois
          </VBtn>
        </VBtnToggle>

        <VBtnToggle
          v-if="view !== 'month'"
          v-model="layoutMode"
          mandatory
          density="comfortable"
          variant="outlined"
          divided
          class="agenda-layout-toggle flex-shrink-0"
        >
          <VBtn
            value="cards"
            prepend-icon="tabler-list"
          >
            Liste
          </VBtn>
          <VBtn
            value="grid"
            prepend-icon="tabler-calendar-time"
          >
            Grille
          </VBtn>
        </VBtnToggle>

        <div class="d-flex align-center gap-1 flex-shrink-0">
          <VBtn
            icon
            variant="text"
            @click="shift(-1)"
          >
            <VIcon icon="tabler-chevron-left" />
          </VBtn>
          <VBtn
            variant="tonal"
            @click="cursor = new Date()"
          >
            Aujourd’hui
          </VBtn>
          <VBtn
            icon
            variant="text"
            @click="shift(1)"
          >
            <VIcon icon="tabler-chevron-right" />
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VCard class="mb-4">
      <VCardText>
        <VRow dense>
          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              v-model="search"
              density="compact"
              hide-details
              label="Recherche"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
              @click:clear="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="filters.appointment_type_id"
              :items="types"
              item-title="name"
              item-value="id"
              density="compact"
              hide-details
              label="Type"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="filters.status"
              :items="statusItems"
              density="compact"
              hide-details
              label="Statut"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="filters.confidentiality"
              :items="confidentialityItems"
              density="compact"
              hide-details
              label="Confidentialité"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="filters.meeting_mode"
              :items="modeItems"
              density="compact"
              hide-details
              label="Mode"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <UserAutocomplete
              v-model="filters.director_id"
              :items="users"
              density="compact"
              hide-details
              label="Directeur"
              placeholder="Rechercher…"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="filters.structure_id"
              :items="structures"
              item-title="name"
              item-value="id"
              density="compact"
              hide-details
              label="Structure"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
            class="d-flex flex-wrap align-center gap-2"
          >
            <VCheckbox
              v-model="filters.include_appointments"
              density="compact"
              hide-details
              label="RDV"
            />
            <VCheckbox
              v-model="filters.include_meetings"
              density="compact"
              hide-details
              label="Réunions"
            />
            <VCheckbox
              v-model="filters.include_unavailabilities"
              density="compact"
              hide-details
              label="Indispo."
            />
            <VBtn
              size="small"
              variant="text"
              @click="resetFilters"
            >
              Réinitialiser
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div
      v-if="layoutMode === 'grid' && view !== 'month'"
      class="agenda-time-grid mb-4"
    >
      <div
        v-for="day in days"
        :key="`grid-${day.toISOString()}`"
        class="agenda-time-grid__day"
      >
        <div class="text-subtitle-2 mb-2 px-2">
          {{ day.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) }}
        </div>
        <div
          v-for="hour in hours"
          :key="`${day.toISOString()}-${hour}`"
          class="agenda-time-grid__slot"
          @click="createAt(day, hour)"
        >
          <div class="agenda-time-grid__hour">
            {{ String(hour).padStart(2, '0') }}:00
          </div>
          <div class="agenda-time-grid__events">
            <VChip
              v-for="event in eventsForHour(day.toISOString().slice(0, 10), hour)"
              :key="event.id"
              size="small"
              class="ma-1"
              :color="calendarEventColor(event.source, event.masked)"
              @click.stop="openEvent(event)"
            >
              {{ event.time }} {{ event.title }}
            </VChip>
          </div>
        </div>
      </div>
    </div>

    <VRow v-else>
      <VCol
        v-for="day in days"
        :key="day.toISOString()"
        cols="12"
        :sm="view === 'month' ? 6 : 12"
        :md="view === 'month' ? 3 : 12"
        :lg="view === 'month' ? 12 / 7 : 12"
      >
        <VCard
          min-height="160"
          class="agenda-day-card"
        >
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="text-subtitle-2">
                {{ day.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }}
              </div>
              <VBtn
                size="x-small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="createAt(day)"
              >
                Créneau
              </VBtn>
            </div>
            <div
              v-for="event in eventsByDay[day.toISOString().slice(0, 10)] || []"
              :key="event.id"
              class="mb-2"
            >
              <VChip
                size="small"
                class="me-2"
                :color="calendarEventColor(event.source, event.masked)"
                @click="openEvent(event)"
              >
                {{ event.time }}
              </VChip>
              <span
                class="text-body-2"
                :class="{ 'text-medium-emphasis': event.masked }"
              >
                {{ event.title }}
                <span
                  v-if="event.location && !event.masked"
                  class="text-caption text-medium-emphasis"
                >
                  · {{ event.location }}
                </span>
              </span>
            </div>
            <div
              v-if="!(eventsByDay[day.toISOString().slice(0, 10)] || []).length"
              class="text-caption text-medium-emphasis"
            >
              Aucun engagement — cliquez « Créneau » pour planifier
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped lang="scss">
.agenda-layout-toggle {
  overflow: visible;

  :deep(.v-btn) {
    min-inline-size: 6.5rem;
    white-space: nowrap;
  }
}

.agenda-time-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.agenda-time-grid__slot {
  display: grid;
  grid-template-columns: 52px 1fr;
  min-block-size: 44px;
  border-block-end: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

.agenda-time-grid__hour {
  padding: 0.35rem 0.25rem;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.75rem;
}

.agenda-time-grid__events {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
}
</style>
