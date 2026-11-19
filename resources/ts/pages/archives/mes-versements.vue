<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchTransfers, submitTransfer } = useArchives()
const items = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)

const headers = [
  { title: 'Référence', key: 'reference' },
  { title: 'Intitulé', key: 'title' },
  { title: 'Statut', key: 'status' },
  { title: 'Créé le', key: 'created_at' },
  { title: '', key: 'actions', sortable: false },
]

async function load() {
  const data = await fetchTransfers({ mine: 1, page: page.value, per_page: perPage.value })
  items.value = data.data || []
  total.value = data.total || 0
}

async function submit(id: number) {
  await submitTransfer(id)
  await load()
}

watch([page, perPage], () => load())
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes versements"
      subtitle="Bordereaux que vous avez initiés"
      icon="tabler-upload"
    />
    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage }"
      >
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="transferStatusColor(item.status)"
            label
          >
            {{ transferStatusLabels[item.status] || item.status }}
          </VChip>
        </template>
        <template #item.created_at="{ item }">
          {{ formatDateFr(item.created_at, true) }}
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex gap-2 justify-end">
            <VBtn
              v-if="item.status === 'brouillon' || item.status === 'rejete'"
              size="small"
              color="primary"
              @click="submit(item.id)"
            >
              Soumettre
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              @click="router.push({ name: 'archives-versements-id', params: { id: item.id } })"
            >
              Ouvrir
            </VBtn>
          </div>
        </template>
        <template #no-data>
          <div class="text-medium-emphasis pa-4">
            Aucun versement.
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
