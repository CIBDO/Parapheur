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
const scope = ref('upcoming')
const search = ref('')
const statusFilter = ref<string | null>(null)

const scopes = [
  { value: 'upcoming', title: 'À venir' },
  { value: 'today', title: 'Aujourd’hui' },
  { value: 'week', title: 'Cette semaine' },
  { value: 'preparation', title: 'À préparer' },
  { value: 'in_progress', title: 'En cours' },
  { value: 'minutes', title: 'Comptes rendus' },
  { value: 'mine', title: 'Mes réunions' },
]

const kpi = computed(() => {
  if (!stats.value)
    return []

  return [
    { title: 'Aujourd’hui', value: stats.value.today, icon: 'tabler-calendar-event', color: 'primary' },
    { title: 'Cette semaine', value: stats.value.this_week, icon: 'tabler-calendar-week', color: 'info' },
    { title: 'À préparer', value: stats.value.in_preparation, icon: 'tabler-clipboard-list', color: 'warning' },
    { title: 'CR à valider', value: stats.value.minutes_to_validate, icon: 'tabler-file-check', color: 'secondary' },
    { title: 'Décisions ouvertes', value: stats.value.decisions_open, icon: 'tabler-gavel', color: 'primary' },
    { title: 'Décisions en retard', value: stats.value.decisions_late, icon: 'tabler-alert-triangle', color: 'error' },
  ]
})

const load = async () => {
  loading.value = true
  try {
    const query: Record<string, unknown> = {}
    if (scope.value === 'mine')
      query.mine = 1
    else if (scope.value)
      query.scope = scope.value
    if (search.value)
      query.q = search.value
    if (statusFilter.value)
      query.status = statusFilter.value

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

watch([scope, statusFilter], load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Réunions"
      subtitle="Préparation, séance, décisions et suivi d’exécution"
      icon="tabler-users-group"
    >
      <template #actions>
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

    <VRow class="mb-6">
      <VCol
        v-for="card in kpi"
        :key="card.title"
        cols="6"
        md="2"
      >
        <VCard>
          <VCardText>
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="36"
              class="mb-2"
            >
              <VIcon
                :icon="card.icon"
                size="20"
              />
            </VAvatar>
            <div class="text-caption text-medium-emphasis">
              {{ card.title }}
            </div>
            <div class="text-h5">
              {{ card.value ?? '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap gap-3 align-center">
        <VChipGroup
          v-model="scope"
          selected-class="text-primary"
          mandatory
        >
          <VChip
            v-for="item in scopes"
            :key="item.value"
            :value="item.value"
            filter
            variant="tonal"
          >
            {{ item.title }}
          </VChip>
        </VChipGroup>
        <VSpacer />
        <AppTextField
          v-model="search"
          density="compact"
          placeholder="Référence, objet…"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 260px"
          @keyup.enter="load"
        />
      </VCardText>
    </VCard>

    <VRow v-if="loading && !meetings.length">
      <VCol
        cols="12"
        class="text-center py-8"
      >
        <VProgressCircular indeterminate />
      </VCol>
    </VRow>

    <VRow v-else-if="meetings.length">
      <VCol
        v-for="meeting in meetings"
        :key="meeting.id"
        cols="12"
        md="6"
        lg="4"
      >
        <VCard
          class="parapheur-folder-tile"
          @click="router.push({ name: 'parapheur-reunions-id', params: { id: meeting.id } })"
        >
          <VCardText>
            <div class="d-flex justify-space-between align-start mb-2">
              <VChip
                size="small"
                :color="meetingStatusColor(meeting.status)"
                variant="tonal"
              >
                {{ meeting.status_label || meetingStatusLabels[meeting.status] || meeting.status }}
              </VChip>
              <span class="text-caption text-medium-emphasis">{{ meeting.reference }}</span>
            </div>
            <h6 class="text-h6 mb-1">
              {{ meeting.object || meeting.title }}
            </h6>
            <div class="text-body-2 text-medium-emphasis mb-3">
              {{ formatDateFr(meeting.meeting_date) }}
              {{ meeting.meeting_time || '' }}
              · {{ meeting.location || 'Lieu non précisé' }}
            </div>
            <div class="d-flex gap-4 text-caption">
              <span>{{ meeting.participants_count || 0 }} participant(s)</span>
              <span>{{ meeting.decisions_count || 0 }} décision(s)</span>
            </div>
            <div class="text-caption mt-2">
              Président : {{ meeting.chair?.name || '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-else
      class="parapheur-empty"
    >
      Aucune réunion pour ce filtre.
    </div>
  </div>
</template>
