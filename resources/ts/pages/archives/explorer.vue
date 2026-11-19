<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, recordStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchRecords } = useArchives()
const items = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const q = ref('')

const headers = [
  { title: 'Identifiant', key: 'permanent_id' },
  { title: 'Titre', key: 'title' },
  { title: 'Structure', key: 'structure' },
  { title: 'Statut', key: 'status' },
  { title: 'Conservé le', key: 'conserved_at' },
  { title: '', key: 'actions', sortable: false },
]

async function load() {
  const data = await fetchRecords({ page: page.value, per_page: perPage.value, q: q.value })
  items.value = data.data || []
  total.value = data.total || 0
}

watch([page, perPage], () => load())
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Explorer les archives"
      subtitle="Fonds conservés accessibles selon vos habilitations"
      icon="tabler-folder-open"
    />
    <VCard class="parapheur-section-card">
      <VCardText>
        <VTextField
          v-model="q"
          label="Rechercher"
          prepend-inner-icon="tabler-search"
          hide-details
          clearable
          class="mb-4"
          style="max-width: 420px"
          @keyup.enter="load"
          @click:clear="() => { q = ''; load() }"
        />
        <VDataTableServer
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="items"
          :items-length="total"
          :loading="loading"
          @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage }"
        >
          <template #item.structure="{ item }">
            {{ item.structure?.name || item.structure?.code || '—' }}
          </template>
          <template #item.status="{ item }">
            <VChip
              size="small"
              label
              color="secondary"
            >
              {{ recordStatusLabels[item.status] || item.status }}
            </VChip>
          </template>
          <template #item.conserved_at="{ item }">
            {{ formatDateFr(item.conserved_at) }}
          </template>
          <template #item.actions="{ item }">
            <VBtn
              size="small"
              variant="tonal"
              @click="router.push({ name: 'archives-records-id', params: { id: item.id } })"
            >
              Ouvrir
            </VBtn>
          </template>
          <template #no-data>
            <div class="text-medium-emphasis pa-4">
              Aucune archive.
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>
