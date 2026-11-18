<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const { loading, fetchOccupation } = useArchives()
const rooms = ref<any[]>([])

onMounted(async () => {
  const data = await fetchOccupation()
  rooms.value = data.data || []
})

function slotColor(rate: number | null | undefined) {
  if (rate == null)
    return 'secondary'
  if (rate >= 100)
    return 'error'
  if (rate >= 80)
    return 'warning'
  if (rate > 0)
    return 'success'
  return 'info'
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Plan d’occupation"
      subtitle="Vue 2D des salles et emplacements"
      icon="tabler-layout-grid"
    />

    <VRow :loading="loading">
      <VCol
        v-for="block in rooms"
        :key="block.room.id"
        cols="12"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>{{ block.room.full_code }} — {{ block.room.name }}</VCardTitle>
            <VCardSubtitle>Occupation {{ block.room.occupancy_rate ?? 0 }}%</VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex flex-wrap gap-2">
            <VChip
              v-for="slot in block.slots"
              :key="slot.id"
              :color="slotColor(slot.occupancy_rate)"
              label
              class="ma-1"
            >
              {{ slot.code }} · {{ slot.boxes_count || 0 }}/{{ slot.capacity_boxes ?? '∞' }}
            </VChip>
            <div
              v-if="!(block.slots || []).length"
              class="text-medium-emphasis"
            >
              Aucun emplacement enfant.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
