<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing' },
})

const {
  fetchDashboardManagement,
  fetchReportVolume,
  fetchReportSla,
} = useTicketing()

const loading = ref(true)
const management = ref<Record<string, any>>({})
const volume = ref<any[]>([])
const sla = ref<Record<string, any>>({})
const satisfaction = ref<Record<string, any>>({})
const byCategory = ref<any[]>([])
const errorMsg = ref('')

const cards = computed(() => [
  { title: 'Volume 30 j', value: management.value.volume_30d ?? '—', icon: 'tabler-chart-bar', color: 'primary' },
  { title: 'Ouverts', value: management.value.open ?? '—', icon: 'tabler-ticket', color: 'info' },
  { title: 'SLA respecté %', value: management.value.sla_met_percent_30d ?? sla.value.met_percent ?? '—', icon: 'tabler-clock-check', color: 'success' },
  { title: 'Délai moyen (h)', value: management.value.avg_resolution_hours_30d ?? '—', icon: 'tabler-hourglass', color: 'warning' },
  { title: 'Satisfaction moy.', value: satisfaction.value.average ?? management.value.avg_satisfaction_30d ?? '—', icon: 'tabler-star', color: 'orange' },
  { title: 'Avis reçus', value: satisfaction.value.count ?? '—', icon: 'tabler-message-star', color: 'secondary' },
])

const satisfactionDist = computed(() => {
  const dist = satisfaction.value.distribution || {}
  return [1, 2, 3, 4, 5].map(score => ({
    score,
    total: Number(dist[score] ?? dist[String(score)] ?? 0),
  }))
})

onMounted(async () => {
  loading.value = true
  try {
    const [mgmt, vol, slaRes, sat, cat] = await Promise.allSettled([
      fetchDashboardManagement(),
      fetchReportVolume(),
      fetchReportSla(),
      $api('/ticketing/reports/satisfaction'),
      $api('/ticketing/reports/by-category'),
    ])
    if (mgmt.status === 'fulfilled')
      management.value = mgmt.value || {}
    if (vol.status === 'fulfilled')
      volume.value = listItems(vol.value).length ? listItems(vol.value) : (Array.isArray(vol.value) ? vol.value : [])
    if (slaRes.status === 'fulfilled')
      sla.value = slaRes.value || {}
    if (sat.status === 'fulfilled')
      satisfaction.value = sat.value || {}
    if (cat.status === 'fulfilled') {
      const raw = cat.value
      byCategory.value = listItems(raw).length ? listItems(raw) : (Array.isArray(raw) ? raw : [])
    }
    if (mgmt.status === 'rejected' && vol.status === 'rejected')
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
      title="Rapports"
      subtitle="Pilotage et indicateurs de performance"
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
                <div class="text-caption">
                  {{ card.title }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow dense>
        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Volume
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VDataTable
              :headers="[
                { title: 'Jour', key: 'day' },
                { title: 'Total', key: 'total' },
              ]"
              :items="volume"
              density="compact"
            >
              <template #no-data>
                <div class="text-center py-6 text-medium-emphasis">
                  Aucune donnée de volume.
                </div>
              </template>
            </VDataTable>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Par catégorie
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VDataTable
              :headers="[
                { title: 'Catégorie', key: 'category' },
                { title: 'Total', key: 'total' },
              ]"
              :items="byCategory"
              density="compact"
            >
              <template #item.category="{ item }">
                {{ item.category || item.name || 'Sans catégorie' }}
              </template>
              <template #no-data>
                <div class="text-center py-6 text-medium-emphasis">
                  Aucune répartition.
                </div>
              </template>
            </VDataTable>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Rapport SLA
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <VList density="compact">
                <VListItem>
                  <VListItemTitle>Tickets avec SLA</VListItemTitle>
                  <template #append>
                    <span class="font-weight-medium">{{ sla.total_with_sla ?? '—' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Respectés</VListItemTitle>
                  <template #append>
                    <span class="font-weight-medium">{{ sla.met ?? '—' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Dépassés</VListItemTitle>
                  <template #append>
                    <span class="font-weight-medium">{{ sla.breached ?? '—' }}</span>
                  </template>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Taux de respect</VListItemTitle>
                  <template #append>
                    <span class="font-weight-medium">{{ sla.met_percent != null ? `${sla.met_percent} %` : '—' }}</span>
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Satisfaction
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VDataTable
              :headers="[
                { title: 'Note', key: 'score' },
                { title: 'Nombre', key: 'total' },
              ]"
              :items="satisfactionDist"
              density="compact"
            >
              <template #no-data>
                <div class="text-center py-6 text-medium-emphasis">
                  Aucun avis pour le moment.
                </div>
              </template>
            </VDataTable>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
