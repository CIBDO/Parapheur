<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'pilot',
    subject: 'Production',
  },
})

const loading = ref(false)
const denied = ref(false)
const stats = ref<any>({})

const cards = [
  { key: 'expected_today', title: 'Attendues', hint: 'Aujourd’hui', color: 'warning', icon: 'tabler-file-time' },
  { key: 'transmitted_today', title: 'Transmises', hint: 'Aujourd’hui', color: 'success', icon: 'tabler-send' },
  { key: 'missing_today', title: 'Manquantes', hint: 'Non déposées', color: 'error', icon: 'tabler-file-off' },
  { key: 'overdue', title: 'En retard', hint: 'Toutes structures', color: 'error', icon: 'tabler-alert-triangle' },
  { key: 'active_obligations', title: 'Obligations', hint: 'Référentiel actif', color: 'info', icon: 'tabler-settings' },
]

const load = async () => {
  loading.value = true
  denied.value = false
  try {
    stats.value = await $api('/productions/dashboard/direction')
  }
  catch (error: any) {
    stats.value = {}
    denied.value = error?.status === 403 || error?.statusCode === 403
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
      title="Pilotage Direction Générale"
      subtitle="Vue consolidée des productions institutionnelles"
      icon="tabler-chart-dots"
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
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="denied"
      type="warning"
      variant="tonal"
      class="mb-4"
      icon="tabler-lock"
    >
      Cette vue est réservée à la Direction Générale et aux profils habilités.
    </VAlert>

    <div
      v-if="loading && !Object.keys(stats).length"
      class="text-center py-8"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="!denied">
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          v-for="card in cards"
          :key="card.key"
          cols="12"
          sm="6"
          lg="4"
        >
          <VCard class="h-100">
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
                  {{ stats[card.key] ?? 0 }}
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
          md="6"
        >
          <VCard>
            <VCardItem>
              <VCardTitle class="text-h6">
                Volume sur 7 jours
              </VCardTitle>
              <VCardSubtitle>
                Occurrences par structure
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VTable>
              <thead>
                <tr>
                  <th>Structure</th>
                  <th class="text-end">
                    Total
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in stats.by_structure || []"
                  :key="row.structure_id || row.id"
                >
                  <td>{{ row.structure?.name || row.structure?.code || `#${row.structure_id}` }}</td>
                  <td class="text-end font-weight-medium">
                    {{ row.total }}
                  </td>
                </tr>
                <tr v-if="!(stats.by_structure || []).length">
                  <td
                    colspan="2"
                    class="text-center text-medium-emphasis py-8"
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
            <VCardItem>
              <VCardTitle class="text-h6">
                Retards par structure
              </VCardTitle>
              <VCardSubtitle>
                Occurrences non closes après l’échéance
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VTable>
              <thead>
                <tr>
                  <th>Structure</th>
                  <th class="text-end">
                    Retards
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in stats.overdue_by_structure || []"
                  :key="`late-${row.structure_id}`"
                >
                  <td>{{ row.structure?.name || row.structure?.code || `#${row.structure_id}` }}</td>
                  <td class="text-end text-error font-weight-medium">
                    {{ row.total }}
                  </td>
                </tr>
                <tr v-if="!(stats.overdue_by_structure || []).length">
                  <td
                    colspan="2"
                    class="text-center text-medium-emphasis py-8"
                  >
                    Aucun retard
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
