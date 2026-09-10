<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { calendarEventColor } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const view = ref<'month' | 'week' | 'day'>('day')
const cursor = ref(new Date())
const events = ref<any[]>([])
const types = ref<any[]>([])
const users = ref<any[]>([])
const structures = ref<any[]>([])
const search = ref('')

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
  const dg = u.find((x: any) => x.email === 'dg@dgtcp.local')
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
        <VBtnToggle
          v-model="view"
          mandatory
          density="compact"
          variant="tonal"
          divided
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
        <VBtn
          icon
          variant="text"
          @click="shift(-1)"
        >
          <VIcon icon="tabler-chevron-left" />
        </VBtn>
        <VBtn
          icon
          variant="text"
          @click="shift(1)"
        >
          <VIcon icon="tabler-chevron-right" />
        </VBtn>
        <VBtn
          variant="tonal"
          @click="cursor = new Date()"
        >
          Aujourd’hui
        </VBtn>
      </template>
    </ParapheurPageHeader>

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
            <VSelect
              v-model="filters.director_id"
              :items="users"
              item-title="name"
              item-value="id"
              density="compact"
              hide-details
              label="Directeur"
              clearable
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

    <VRow>
      <VCol
        v-for="day in days"
        :key="day.toISOString()"
        cols="12"
        :sm="view === 'month' ? 6 : 12"
        :md="view === 'month' ? 3 : 12"
        :lg="view === 'month' ? 12 / 7 : 12"
      >
        <VCard min-height="160">
          <VCardText>
            <div class="text-subtitle-2 mb-3">
              {{ day.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }}
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
              Aucun engagement
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
