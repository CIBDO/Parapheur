<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { listItems } from '@/utils/listItems'
import { correspondenceStatusColors, formatCorrespondenceNumber, statusLabel } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const { correspondences, fetchCorrespondences, loading } = useCorrespondence()
const search = ref('')

async function load() {
  await fetchCorrespondences({
    direction: 'interne',
    search: search.value || undefined,
    per_page: 30,
  })
}

onMounted(load)
const items = computed(() => listItems(correspondences.value))
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Courriers internes"
      subtitle="Correspondance entre structures"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'courrier-internes-nouveau' }"
        >
          Nouveau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText class="d-flex gap-3">
        <AppTextField
          v-model="search"
          label="Recherche"
          style="max-inline-size: 280px"
          @keyup.enter="load"
        />
        <VBtn
          variant="tonal"
          color="primary"
          @click="load"
        >
          Filtrer
        </VBtn>
      </VCardText>
      <VDataTable
        :headers="[
          { title: 'Référence', key: 'arrival_number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Structure', key: 'structure' },
          { title: 'Statut', key: 'status' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.arrival_number="{ item }">
          <RouterLink :to="{ name: 'courrier-internes-id', params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.structure="{ item }">
          {{ item.structure?.name || '—' }}
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
      </VDataTable>
    </VCard>
  </div>
</template>
