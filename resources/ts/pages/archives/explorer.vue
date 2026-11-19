<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, recordStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchRecords, getJson } = useArchives()
const items = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const q = ref('')
const status = ref<string | null>(null)
const typology = ref('')
const structureId = ref<number | null>(null)
const structures = ref<any[]>([])

const headers = [
  { title: 'Identifiant', key: 'permanent_id' },
  { title: 'Titre', key: 'title' },
  { title: 'Structure', key: 'structure' },
  { title: 'Typologie', key: 'typology' },
  { title: 'Emplacement', key: 'location' },
  { title: 'Statut', key: 'status' },
  { title: 'Conservé le', key: 'conserved_at' },
  { title: '', key: 'actions', sortable: false },
]

const statusItems = Object.entries(recordStatusLabels).map(([value, title]) => ({ title, value }))

async function load() {
  const params: Record<string, string | number | boolean> = {
    page: page.value,
    per_page: perPage.value,
  }
  if (q.value)
    params.q = q.value
  if (status.value)
    params.status = status.value
  if (typology.value.trim())
    params.typology = typology.value.trim()
  if (structureId.value)
    params.structure_id = structureId.value

  const data = await fetchRecords(params)
  items.value = data.data || []
  total.value = data.total || 0
}

watch([page, perPage], () => load())

onMounted(async () => {
  const structs = await getJson('/meta/structures').catch(() => ({ data: [] }))
  structures.value = structs.data || structs || []
  await load()
})
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
        <VRow
          dense
          class="mb-4"
        >
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="q"
              label="Rechercher"
              prepend-inner-icon="tabler-search"
              hide-details
              clearable
              @keyup.enter="() => { page = 1; load() }"
              @click:clear="() => { q = ''; page = 1; load() }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="status"
              :items="statusItems"
              label="Statut"
              clearable
              hide-details
              @update:model-value="() => { page = 1; load() }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="structureId"
              :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
              label="Structure"
              clearable
              hide-details
              @update:model-value="() => { page = 1; load() }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VTextField
              v-model="typology"
              label="Typologie"
              hide-details
              clearable
              @keyup.enter="() => { page = 1; load() }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="1"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              block
              :loading="loading"
              @click="() => { page = 1; load() }"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
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
          <template #item.typology="{ item }">
            {{ item.typology || '—' }}
          </template>
          <template #item.location="{ item }">
            {{ item.location?.full_code || item.box?.code || '—' }}
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
