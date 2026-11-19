<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'ArchivesBoxes' },
})

const router = useRouter()
const { loading, fetchBoxes, createBox, fetchLocations, getJson } = useArchives()
const items = ref<any[]>([])
const locations = ref<any[]>([])
const structures = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const dialog = ref(false)
const form = ref({
  structure_id: null as number | null,
  location_id: null as number | null,
  code: '',
  label: '',
  linear_meters: null as number | null,
})

const headers = [
  { title: 'Code', key: 'code' },
  { title: 'Intitulé', key: 'label' },
  { title: 'Emplacement', key: 'location' },
  { title: 'Statut', key: 'status' },
  { title: '', key: 'actions', sortable: false },
]

async function load() {
  const [boxes, locs, structs] = await Promise.all([
    fetchBoxes({ page: page.value, per_page: perPage.value }),
    fetchLocations(),
    getJson('/meta/structures').catch(() => ({ data: [] })),
  ])
  items.value = boxes.data || []
  total.value = boxes.total || 0
  locations.value = locs.data || []
  structures.value = structs.data || structs || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
}

async function create() {
  await createBox({ ...form.value })
  dialog.value = false
  await load()
}

watch([page, perPage], () => load())
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Boîtes d’archives"
      subtitle="Unités physiques et contenu hybride"
      icon="tabler-box"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouvelle boîte
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">
      <VCardText>
        <VDataTableServer
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="items"
          :items-length="total"
          :loading="loading"
          class="text-no-wrap"
          @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage }"
        >
          <template #item.location="{ item }">
            {{ item.location?.full_code || '—' }}
          </template>
          <template #item.actions="{ item }">
            <VBtn
              size="small"
              variant="tonal"
              @click="router.push({ name: 'archives-boites-id', params: { id: item.id } })"
            >
              Ouvrir
            </VBtn>
          </template>
          <template #no-data>
            <div class="text-medium-emphasis pa-4">
              Aucune boîte.
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Créer une boîte</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VSelect
            v-model="form.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure"
          />
          <VSelect
            v-model="form.location_id"
            :items="locations.map(l => ({ title: l.full_code, value: l.id }))"
            label="Emplacement"
            clearable
          />
          <VTextField
            v-model="form.code"
            label="Code (auto si vide)"
          />
          <VTextField
            v-model="form.label"
            label="Intitulé"
          />
          <VTextField
            v-model.number="form.linear_meters"
            label="Mètres linéaires"
            type="number"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="create"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
