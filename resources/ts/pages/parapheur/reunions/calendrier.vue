<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { meetingStatusColor } from '@/utils/meetingsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
  },
})

const view = ref<'month' | 'week' | 'day'>('month')
const cursor = ref(new Date())
const events = ref<any[]>([])

const fromTo = computed(() => {
  const start = new Date(cursor.value)
  const end = new Date(cursor.value)
  if (view.value === 'day') {
    return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) }
  }
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
    const key = event.meeting_date
    map[key] = map[key] || []
    map[key].push(event)
  }

  return map
})

const load = async () => {
  events.value = await $api('/meetings/calendar', { query: fromTo.value })
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

watch([view, cursor], load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Calendrier des réunions"
      subtitle="Vue interne autonome — sans dépendance agenda externe"
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
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        v-for="day in days"
        :key="day.toISOString()"
        cols="12"
        :sm="view === 'month' ? 6 : 12"
        :md="view === 'month' ? 3 : 12"
        :lg="view === 'month' ? 12 / 7 : 12"
      >
        <VCard min-height="140">
          <VCardText>
            <div class="text-caption mb-2">
              {{ day.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }) }}
            </div>
            <VChip
              v-for="event in eventsByDay[day.toISOString().slice(0, 10)] || []"
              :key="event.id"
              class="mb-1 me-1"
              size="small"
              :color="meetingStatusColor(event.status)"
              :to="{ name: 'parapheur-reunions-id', params: { id: event.id } }"
            >
              {{ event.meeting_time || '' }} {{ event.title }}
            </VChip>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
