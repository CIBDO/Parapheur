<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import { taskPriorityLabels, taskSourceLabels } from '@/utils/tasksUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Task', navActiveLink: 'taches-rapports' },
})

const {
  fetchReportOverview,
  fetchReportByStructure,
  fetchReportByPriority,
  fetchReportBySource,
  fetchReportWorkload,
} = useTasks()

const loading = ref(true)
const errorMsg = ref('')
const overview = ref<Record<string, any>>({})
const byStructure = ref<any[]>([])
const byPriority = ref<any[]>([])
const bySource = ref<any[]>([])
const workload = ref<any[]>([])

const cards = computed(() => [
  { title: 'Créées (période)', value: overview.value.volume_created ?? '—', icon: 'tabler-plus', color: 'primary' },
  { title: 'Validées', value: overview.value.volume_validated ?? '—', icon: 'tabler-check', color: 'success' },
  { title: 'Ouvertes', value: overview.value.open ?? '—', icon: 'tabler-folder-open', color: 'info' },
  { title: 'En retard', value: overview.value.overdue ?? '—', icon: 'tabler-alert-triangle', color: 'error' },
  { title: 'Taux réalisation %', value: overview.value.completion_rate_percent ?? '—', icon: 'tabler-percentage', color: 'success' },
  { title: 'Délai moyen (h)', value: overview.value.avg_completion_hours ?? '—', icon: 'tabler-hourglass', color: 'warning' },
])

onMounted(async () => {
  loading.value = true
  try {
    const [ov, st, pr, so, wl] = await Promise.allSettled([
      fetchReportOverview(30),
      fetchReportByStructure(30),
      fetchReportByPriority(),
      fetchReportBySource(30),
      fetchReportWorkload(),
    ])
    if (ov.status === 'fulfilled')
      overview.value = ov.value || {}
    if (st.status === 'fulfilled')
      byStructure.value = Array.isArray(st.value) ? st.value : []
    if (pr.status === 'fulfilled')
      byPriority.value = Array.isArray(pr.value) ? pr.value : []
    if (so.status === 'fulfilled')
      bySource.value = Array.isArray(so.value) ? so.value : []
    if (wl.status === 'fulfilled')
      workload.value = Array.isArray(wl.value) ? wl.value : []
    if (ov.status === 'rejected')
      errorMsg.value = 'Accès aux rapports non autorisé ou indisponible.'
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Rapports — Tâches"
      subtitle="Volumes, retards et charge (pilotage Direction)"
      icon="tabler-chart-bar"
    />

    <VAlert
      v-if="errorMsg"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMsg }}
    </VAlert>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else>
      <VRow
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
          <VCard class="h-100">
            <VCardText class="d-flex align-center gap-3 pa-4">
              <VAvatar
                :color="card.color"
                variant="tonal"
                rounded
                size="40"
              >
                <VIcon :icon="card.icon" />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-semibold lh-1">
                  {{ card.value }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ card.title }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem><VCardTitle>Par structure</VCardTitle></VCardItem>
            <VDivider />
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Structure</th>
                  <th>Total</th>
                  <th>Validées</th>
                  <th>Retard</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in byStructure"
                  :key="row.structure_id ?? row.structure_code"
                >
                  <td>{{ row.structure_code }} — {{ row.structure_name }}</td>
                  <td>{{ row.total }}</td>
                  <td>{{ row.validated }}</td>
                  <td>{{ row.overdue }}</td>
                </tr>
                <tr v-if="!byStructure.length">
                  <td
                    colspan="4"
                    class="text-medium-emphasis"
                  >
                    Aucune donnée
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem><VCardTitle>Charge par agent</VCardTitle></VCardItem>
            <VDivider />
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Agent</th>
                  <th>Ouvertes</th>
                  <th>Retard</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in workload"
                  :key="row.assignee_id"
                >
                  <td>{{ row.assignee_name }}</td>
                  <td>{{ row.open_total }}</td>
                  <td>{{ row.overdue }}</td>
                </tr>
                <tr v-if="!workload.length">
                  <td
                    colspan="3"
                    class="text-medium-emphasis"
                  >
                    Aucune donnée
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard>
            <VCardItem><VCardTitle>Par priorité (ouvertes)</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="row in byPriority"
                :key="row.priority"
                :title="taskPriorityLabels[row.priority] || row.priority"
                :subtitle="`${row.total} tâche(s)`"
              />
              <VListItem v-if="!byPriority.length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune donnée
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard>
            <VCardItem><VCardTitle>Par source (30 j)</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="row in bySource"
                :key="row.source_kind"
                :title="taskSourceLabels[row.source_kind] || row.source_kind"
                :subtitle="`${row.total} tâche(s)`"
              />
              <VListItem v-if="!bySource.length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune donnée
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
