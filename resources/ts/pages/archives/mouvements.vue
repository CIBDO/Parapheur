<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'ArchivesBoxes' } })

const { loading, fetchMovements } = useArchives()
const items = ref<any[]>([])

onMounted(async () => {
  const data = await fetchMovements()
  items.value = data.data || []
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mouvements"
      subtitle="Historique des déplacements physiques"
      icon="tabler-arrows-exchange"
    />
    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="m in items"
          :key="m.id"
          :title="`${m.reference} · ${m.movement_type}`"
          :subtitle="`${m.box?.code || '—'} · ${m.from_location?.full_code || '∅'} → ${m.to_location?.full_code || '∅'} · ${m.motif || ''}`"
        />
        <VListItem
          v-if="!items.length"
          title="Aucun mouvement"
        />
      </VList>
    </VCard>
  </div>
</template>
