<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
  },
})

const loading = ref(false)
const dashboard = ref<any>({})
const decisionStats = ref<any>({})

const load = async () => {
  loading.value = true
  try {
    const [dash, decisions] = await Promise.all([
      $api('/meetings/dashboard'),
      $api('/meetings/decisions', { query: { per_page: 1 } }),
    ])
    dashboard.value = dash
    decisionStats.value = decisions.stats ?? {}
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Rapports réunions"
      subtitle="Indicateurs de pilotage — permissions meetings.view_reports"
      icon="tabler-chart-bar"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          to="/parapheur/reunions/decisions"
        >
          Suivi des décisions
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          href="/api/meetings/decisions/export"
        >
          Export CSV
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow :class="{ 'opacity-60': loading }">
      <VCol
        v-for="item in [
          { title: 'À venir', value: dashboard.upcoming },
          { title: 'Aujourd’hui', value: dashboard.today },
          { title: 'Cette semaine', value: dashboard.this_week },
          { title: 'En préparation', value: dashboard.in_preparation },
          { title: 'En cours', value: dashboard.in_progress },
          { title: 'CR à rédiger', value: dashboard.minutes_to_draft },
          { title: 'CR à valider', value: dashboard.minutes_to_validate },
          { title: 'Décisions ouvertes', value: dashboard.decisions_open ?? decisionStats.en_cours },
          { title: 'Décisions en retard', value: dashboard.decisions_late ?? decisionStats.en_retard },
          { title: 'Décisions exécutées', value: decisionStats.executee },
        ]"
        :key="item.title"
        cols="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ item.title }}
            </div>
            <div class="text-h5">
              {{ item.value ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
