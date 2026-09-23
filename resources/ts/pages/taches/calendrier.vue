<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import { formatTaskDue, taskPriorityColor, taskStatusColor } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Task',
    navActiveLink: 'taches-calendrier',
  },
})

const router = useRouter()
const { fetchCalendar } = useTasks()

const view = ref<'month' | 'week'>('month')
const cursor = ref(new Date())
const events = ref<any[]>([])
const loading = ref(false)

const fromTo = computed(() => {
  const start = new Date(cursor.value)
  const end = new Date(cursor.value)
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
    const key = (event.start || '').slice(0, 10)
    if (!key)
      continue
    map[key] = map[key] || []
    map[key].push(event)
  }

  return map
})

const load = async () => {
  loading.value = true
  try {
    const res = await fetchCalendar(fromTo.value.from, fromTo.value.to)
    events.value = res?.events || []
  }
  finally {
    loading.value = false
  }
}

const shift = (delta: number) => {
  const next = new Date(cursor.value)
  if (view.value === 'week')
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
      title="Calendrier des tâches"
      subtitle="Échéances selon vos droits d’accès"
      icon="tabler-calendar"
    >
      <template #actions>
        <VBtnToggle
          v-model="view"
          mandatory
          density="compact"
          color="primary"
        >
          <VBtn value="month">
            Mois
          </VBtn>
          <VBtn value="week">
            Semaine
          </VBtn>
        </VBtnToggle>
        <VBtn
          icon="tabler-chevron-left"
          variant="tonal"
          @click="shift(-1)"
        />
        <VBtn
          icon="tabler-chevron-right"
          variant="tonal"
          @click="shift(1)"
        />
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText>
        <div
          v-if="loading"
          class="text-center py-6"
        >
          <VProgressCircular indeterminate />
        </div>
        <VRow
          v-else
          dense
        >
          <VCol
            v-for="day in days"
            :key="day.toISOString()"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <VSheet
              border
              rounded
              class="pa-3 h-100"
            >
              <div class="text-caption font-weight-medium mb-2">
                {{ day.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' }) }}
              </div>
              <div
                v-for="ev in (eventsByDay[day.toISOString().slice(0, 10)] || [])"
                :key="ev.id"
                class="mb-2 pa-2 rounded cursor-pointer"
                style="background: rgba(var(--v-theme-surface-variant), 0.35);"
                @click="router.push(ev.url || `/taches/${ev.id}`)"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ ev.reference }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ ev.title }}
                </div>
                <div class="d-flex gap-1 mt-1">
                  <VChip
                    size="x-small"
                    :color="taskStatusColor(ev.status)"
                  >
                    {{ ev.assignee?.name || '—' }}
                  </VChip>
                  <VChip
                    size="x-small"
                    :color="taskPriorityColor(ev.priority)"
                    variant="tonal"
                  >
                    {{ formatTaskDue(ev.start) }}
                  </VChip>
                </div>
              </div>
              <div
                v-if="!(eventsByDay[day.toISOString().slice(0, 10)] || []).length"
                class="text-caption text-medium-emphasis"
              >
                —
              </div>
            </VSheet>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>
