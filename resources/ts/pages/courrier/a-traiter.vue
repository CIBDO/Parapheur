<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { listItems } from '@/utils/listItems'
import { correspondenceStatusColors, detailRouteName, formatCorrespondenceNumber, statusLabel } from '@/utils/courrierUi'

definePage({
  name: 'courrier-a-traiter',
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const { correspondences, fetchCorrespondences, loading, takeCharge } = useCorrespondence()

onMounted(() => fetchCorrespondences({ mine: 1, per_page: 50 }))

const items = computed(() => listItems(correspondences.value))

async function doTakeCharge(item: any) {
  const assignment = item.assignments?.find((a: any) => ['transmis', 'recu'].includes(a.status))
  if (!assignment)
    return
  await takeCharge(item.id, assignment.id)
  await fetchCorrespondences({ mine: 1, per_page: 50 })
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="À traiter"
      subtitle="Courriers qui me sont affectés"
    />
    <VCard>
      <VDataTable
        :headers="[
          { title: 'N°', key: 'number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Statut', key: 'status' },
          { title: 'Échéance', key: 'due_date' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.number="{ item }">
          <RouterLink :to="{ name: detailRouteName(item.direction), params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            @click="doTakeCharge(item)"
          >
            Prendre en charge
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
