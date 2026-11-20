<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'manage', subject: 'ArchivesAdmin' } })

const {
  loading,
  error,
  fetchLocations,
  createLocation,
  updateLocation,
  deleteLocation,
  fetchMetaOptions,
  getJson,
} = useArchives()

const items = ref<any[]>([])
const structures = ref<any[]>([])
const typeFilter = ref<string | null>(null)
const dialog = ref(false)
const editDialog = ref(false)
const snack = ref('')
const typeItems = ref<{ title: string; value: string }[]>([])
const statusItems = ref<{ title: string; value: string }[]>([])

const form = ref({
  structure_id: null as number | null,
  parent_id: null as number | null,
  type: 'room',
  code: '',
  name: '',
  capacity_boxes: null as number | null,
  capacity_linear_meters: null as number | null,
})

const editForm = ref({
  id: 0,
  name: '',
  status: 'disponible',
  capacity_boxes: null as number | null,
  capacity_linear_meters: null as number | null,
  width_cm: null as number | null,
  height_cm: null as number | null,
  depth_cm: null as number | null,
  notes: '',
  is_active: true,
})

function depthOf(item: any, byId: Map<number, any>, memo = new Map<number, number>()): number {
  if (memo.has(item.id))
    return memo.get(item.id)!
  if (!item.parent_id || !byId.has(item.parent_id)) {
    memo.set(item.id, 0)
    return 0
  }
  const d = depthOf(byId.get(item.parent_id), byId, memo) + 1
  memo.set(item.id, d)
  return d
}

const treeItems = computed(() => {
  let list = [...items.value]
  if (typeFilter.value)
    list = list.filter(i => i.type === typeFilter.value)
  const byId = new Map(items.value.map(i => [i.id, i]))
  return list
    .map(i => ({ ...i, _depth: depthOf(i, byId) }))
    .sort((a, b) => String(a.full_code).localeCompare(String(b.full_code)))
})

const typeLabel = (value: string) => typeItems.value.find(i => i.value === value)?.title || value
const statusLabel = (value: string) => statusItems.value.find(i => i.value === value)?.title || value

async function load() {
  const [locs, structs, meta] = await Promise.all([
    fetchLocations(),
    getJson('/meta/structures').catch(() => ({ data: [] })),
    fetchMetaOptions(),
  ])
  items.value = locs.data || []
  structures.value = structs.data || structs || []
  typeItems.value = meta.data?.location_types || []
  statusItems.value = meta.data?.location_statuses || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
  if (!form.value.type && typeItems.value[0])
    form.value.type = typeItems.value.find(t => t.value === 'room')?.value || typeItems.value[0].value
}

async function create() {
  try {
    await createLocation({ ...form.value })
    dialog.value = false
    form.value = {
      structure_id: form.value.structure_id,
      parent_id: null,
      type: form.value.type,
      code: '',
      name: '',
      capacity_boxes: null,
      capacity_linear_meters: null,
    }
    snack.value = 'Emplacement créé'
    await load()
  }
  catch (e: any) {
    snack.value = e?.message || error.value || 'Échec'
  }
}

function openEdit(item: any) {
  editForm.value = {
    id: item.id,
    name: item.name || '',
    status: item.status || 'disponible',
    capacity_boxes: item.capacity_boxes ?? null,
    capacity_linear_meters: item.capacity_linear_meters ?? null,
    width_cm: item.width_cm ?? null,
    height_cm: item.height_cm ?? null,
    depth_cm: item.depth_cm ?? null,
    notes: item.notes || '',
    is_active: item.is_active !== false,
  }
  editDialog.value = true
}

async function saveEdit() {
  try {
    const { id, ...body } = editForm.value
    await updateLocation(id, body)
    editDialog.value = false
    snack.value = 'Emplacement mis à jour'
    await load()
  }
  catch (e: any) {
    snack.value = e?.message || error.value || 'Échec'
  }
}

async function remove(item: any) {
  if (!confirm(`Supprimer « ${item.full_code} » ?`))
    return
  try {
    await deleteLocation(item.id)
    snack.value = 'Emplacement supprimé'
    await load()
  }
  catch (e: any) {
    snack.value = e?.message || 'Suppression impossible'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Magasins et emplacements"
      subtitle="Hiérarchie physique — création, édition et suppression"
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

    <VSnackbar
      v-model="snack"
      :timeout="4000"
    >
      {{ snack }}
    </VSnackbar>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VSelect
          v-model="typeFilter"
          :items="typeItems"
          item-title="title"
          item-value="value"
          label="Filtrer par type"
          clearable
          hide-details
          style="max-width: 280px"
        />
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="item in treeItems"
          :key="item.id"
          :style="{ paddingInlineStart: `${12 + item._depth * 20}px` }"
          @click="openEdit(item)"
        >
          <template #prepend>
            <VIcon
              icon="tabler-building-warehouse"
              size="20"
              class="me-2"
            />
          </template>
          <VListItemTitle>
            {{ item.full_code }} — {{ item.name }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ typeLabel(item.type) }}
            · {{ item.occupied_boxes ?? 0 }}/{{ item.capacity_boxes ?? '∞' }} boîtes
            <template v-if="item.capacity_linear_meters != null">
              · {{ item.occupied_linear_meters ?? 0 }}/{{ item.capacity_linear_meters }} ml
            </template>
            · {{ statusLabel(item.status) }}
          </VListItemSubtitle>
          <template #append>
            <div
              class="d-flex align-center gap-2"
              style="min-width: 180px"
            >
              <VProgressLinear
                :model-value="Number(item.occupancy_rate || 0)"
                :color="Number(item.occupancy_rate || 0) >= 90 ? 'error' : Number(item.occupancy_rate || 0) >= 70 ? 'warning' : 'primary'"
                height="6"
                rounded
                style="width: 70px"
              />
              <span class="text-caption text-medium-emphasis">{{ item.occupancy_rate ?? 0 }}%</span>
              <VBtn
                size="small"
                variant="tonal"
                @click.stop="openEdit(item)"
              >
                Éditer
              </VBtn>
              <VBtn
                size="small"
                color="error"
                variant="text"
                @click.stop="remove(item)"
              >
                ×
              </VBtn>
            </div>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-if="!treeItems.length"
        class="text-medium-emphasis"
      >
        Aucun emplacement en base. Créez le premier magasin.
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
            :items="typeItems"
            item-title="title"
            item-value="value"
            label="Type"
          />
          <VSelect
            v-model="form.parent_id"
            :items="items.map(i => ({ title: `${i.full_code} — ${i.name}`, value: i.id }))"
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
          <VTextField
            v-model.number="form.capacity_linear_meters"
            label="Capacité (mètres linéaires)"
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
            :loading="loading"
            @click="create"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="editDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Modifier l’emplacement</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="editForm.name"
            label="Libellé"
          />
          <VSelect
            v-model="editForm.status"
            :items="statusItems"
            item-title="title"
            item-value="value"
            label="Statut"
          />
          <VTextField
            v-model.number="editForm.capacity_boxes"
            label="Capacité (boîtes)"
            type="number"
          />
          <VTextField
            v-model.number="editForm.capacity_linear_meters"
            label="Capacité (mètres linéaires)"
            type="number"
          />
          <div class="d-flex gap-3">
            <VTextField
              v-model.number="editForm.width_cm"
              label="Largeur (cm)"
              type="number"
            />
            <VTextField
              v-model.number="editForm.height_cm"
              label="Hauteur (cm)"
              type="number"
            />
            <VTextField
              v-model.number="editForm.depth_cm"
              label="Profondeur (cm)"
              type="number"
            />
          </div>
          <VTextarea
            v-model="editForm.notes"
            label="Notes"
            rows="2"
          />
          <VSwitch
            v-model="editForm.is_active"
            label="Actif"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="editDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            @click="saveEdit"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
