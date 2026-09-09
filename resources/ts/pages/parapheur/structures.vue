<script setup lang="ts">
definePage({
  meta: {
    action: 'manage',
    subject: 'Structure',
  },
})

interface StructureType {
  id: number
  code: string
  name: string
  sort_order: number
}

interface StructureItem {
  id: number
  code: string
  name: string
  structure_type_id: number | null
  parent_id: number | null
  is_active: boolean
  sort_order: number
  type?: StructureType | null
  parent?: { id: number; code: string; name: string } | null
  users_count?: number
  children_count?: number
}

const structures = ref<StructureItem[]>([])
const structureTypes = ref<StructureType[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isDialogOpen = ref(false)
const isTypeDialogOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  code: '',
  name: '',
  structure_type_id: null as number | null,
  parent_id: null as number | null,
  is_active: true,
  sort_order: 0,
})

const typeForm = ref({
  code: '',
  name: '',
  sort_order: 0,
})

const parentOptions = computed(() =>
  structures.value.filter(item => item.id !== editingId.value),
)

const dialogTitle = computed(() =>
  editingId.value ? 'Modifier la structure' : 'Nouvelle structure',
)

const resetForm = () => {
  form.value = {
    code: '',
    name: '',
    structure_type_id: structureTypes.value[0]?.id ?? null,
    parent_id: null,
    is_active: true,
    sort_order: structures.value.length + 1,
  }
  editingId.value = null
  errorMessage.value = ''
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const [list, types] = await Promise.all([
      $api('/structures'),
      $api('/structure-types'),
    ])
    structures.value = list
    structureTypes.value = types
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Impossible de charger les structures'
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  isDialogOpen.value = true
}

const openEdit = (item: StructureItem) => {
  editingId.value = item.id
  form.value = {
    code: item.code,
    name: item.name,
    structure_type_id: item.structure_type_id,
    parent_id: item.parent_id,
    is_active: item.is_active,
    sort_order: item.sort_order,
  }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const saveStructure = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (editingId.value) {
      await $api(`/structures/${editingId.value}`, {
        method: 'PUT',
        body: form.value,
      })
      successMessage.value = 'Structure mise à jour'
    }
    else {
      await $api('/structures', {
        method: 'POST',
        body: form.value,
      })
      successMessage.value = 'Structure créée'
    }

    isDialogOpen.value = false
    await load()
  }
  catch (e: any) {
    const errors = e?.data?.errors
    const firstError = errors
      ? Object.values(errors).flat()[0]
      : null
    errorMessage.value = String(firstError || e?.data?.message || 'Échec de l’enregistrement')
  }
  finally {
    saving.value = false
  }
}

const removeStructure = async (item: StructureItem) => {
  if (!confirm(`Supprimer la structure « ${item.code} — ${item.name} » ?`))
    return

  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api(`/structures/${item.id}`, { method: 'DELETE' })
    successMessage.value = 'Structure supprimée'
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Suppression impossible'
  }
}

const saveType = async () => {
  saving.value = true
  errorMessage.value = ''

  try {
    const type = await $api('/structure-types', {
      method: 'POST',
      body: typeForm.value,
    })
    structureTypes.value.push(type)
    form.value.structure_type_id = type.id
    typeForm.value = { code: '', name: '', sort_order: 0 }
    isTypeDialogOpen.value = false
    successMessage.value = 'Type de structure créé'
  }
  catch (e: any) {
    const errors = e?.data?.errors
    const firstError = errors
      ? Object.values(errors).flat()[0]
      : null
    errorMessage.value = String(firstError || e?.data?.message || 'Échec de création du type')
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          Structures
        </h4>
        <p class="text-body-1 mb-0 text-medium-emphasis">
          Organigramme des services et directions
        </p>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-category"
          @click="isTypeDialogOpen = true"
        >
          Nouveau type
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouvelle structure
        </VBtn>
      </div>
    </div>

    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMessage = ''"
    >
      {{ successMessage }}
    </VAlert>

    <VAlert
      v-if="errorMessage && !isDialogOpen && !isTypeDialogOpen"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VDataTable
        :items="structures"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Libellé', key: 'name' },
          { title: 'Type', key: 'type' },
          { title: 'Parent', key: 'parent' },
          { title: 'Utilisateurs', key: 'users_count' },
          { title: 'Statut', key: 'is_active' },
          { title: 'Ordre', key: 'sort_order' },
          { title: '', key: 'actions', sortable: false },
        ]"
        item-value="id"
      >
        <template #item.type="{ item }">
          {{ item.type?.name || '—' }}
        </template>

        <template #item.parent="{ item }">
          <span v-if="item.parent">
            {{ item.parent.code }}
          </span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <template #item.users_count="{ item }">
          {{ item.users_count ?? 0 }}
        </template>

        <template #item.is_active="{ item }">
          <VChip
            size="small"
            label
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <IconBtn @click="openEdit(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn
              color="error"
              @click="removeStructure(item)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Dialog structure -->
    <VDialog
      v-model="isDialogOpen"
      max-width="640"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ dialogTitle }}</VCardTitle>
        </VCardItem>

        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="form.code"
                label="Code"
                placeholder="DSI"
                hint="Identifiant court unique"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="form.name"
                label="Libellé"
                placeholder="Direction des Systèmes d'Information"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.structure_type_id"
                :items="structureTypes"
                item-title="name"
                item-value="id"
                label="Type de structure"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.parent_id"
                :items="parentOptions"
                :item-title="(i: StructureItem) => `${i.code} — ${i.name}`"
                item-value="id"
                label="Structure parente"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.sort_order"
                type="number"
                label="Ordre d'affichage"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                label="Structure active"
                color="primary"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveStructure"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog type -->
    <VDialog
      v-model="isTypeDialogOpen"
      max-width="480"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Nouveau type de structure</VCardTitle>
        </VCardItem>

        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="typeForm.code"
                label="Code"
                placeholder="DIR"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="typeForm.name"
                label="Libellé"
                placeholder="Direction"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model.number="typeForm.sort_order"
                type="number"
                label="Ordre"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isTypeDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveType"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
