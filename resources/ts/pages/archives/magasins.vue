<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const { loading, fetchLocations, createLocation, getJson } = useArchives()
const items = ref<any[]>([])
const structures = ref<any[]>([])
const dialog = ref(false)
const form = ref({
  structure_id: null as number | null,
  parent_id: null as number | null,
  type: 'room',
  code: '',
  name: '',
  capacity_boxes: null as number | null,
})

const typeLabels: Record<string, string> = {
  site: 'Site',
  building: 'Bâtiment',
  room: 'Salle',
  zone: 'Zone',
  rack: 'Rayonnage',
  bay: 'Travée',
  face: 'Face',
  shelf: 'Tablette',
  slot: 'Emplacement',
  cabinet: 'Armoire',
}

async function load() {
  const [locs, structs] = await Promise.all([
    fetchLocations(),
    getJson('/meta/structures').catch(() => ({ data: [] })),
  ])
  items.value = locs.data || []
  structures.value = structs.data || structs || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
}

async function create() {
  await createLocation({ ...form.value })
  dialog.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Magasins et emplacements"
      subtitle="Hiérarchie physique configurable"
      icon="tabler-building-warehouse"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouvel emplacement
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="`${item.full_code} — ${item.name}`"
          :subtitle="`${typeLabels[item.type] || item.type}`"
        >
          <template #append>
            <div
              class="d-flex align-center gap-3"
              style="min-width: 140px"
            >
              <VProgressLinear
                :model-value="Number(item.occupancy_rate || 0)"
                :color="Number(item.occupancy_rate || 0) >= 90 ? 'error' : Number(item.occupancy_rate || 0) >= 70 ? 'warning' : 'primary'"
                height="6"
                rounded
                style="width: 80px"
              />
              <span class="text-caption text-medium-emphasis">{{ item.occupancy_rate ?? 0 }}%</span>
            </div>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-if="!items.length"
        class="text-medium-emphasis"
      >
        Aucun emplacement.
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Créer un emplacement</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VSelect
            v-model="form.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure"
          />
          <VSelect
            v-model="form.type"
            :items="Object.entries(typeLabels).map(([value, title]) => ({ title, value }))"
            label="Type"
          />
          <VSelect
            v-model="form.parent_id"
            :items="items.map(i => ({ title: i.full_code, value: i.id }))"
            label="Parent"
            clearable
          />
          <VTextField
            v-model="form.code"
            label="Code"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
          />
          <VTextField
            v-model.number="form.capacity_boxes"
            label="Capacité (boîtes)"
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
