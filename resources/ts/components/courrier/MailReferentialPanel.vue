<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { applyMailPriorities } from '@/utils/courrierUi'

const props = defineProps<{
  resource: 'categories' | 'channels' | 'qualifications' | 'actions' | 'priorities'
  title: string
  hint: string
}>()

const { adminList, adminStore, adminUpdate, adminToggle, adminDelete } = useCorrespondence()

const items = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const showDialog = ref(false)
const editing = ref<any>(null)

const form = ref({
  name: '',
  code: '',
  description: '',
  color: 'info',
  sort_order: 0,
  is_urgent: false,
  is_active: true,
})

const colorItems = [
  { title: 'Info', value: 'info' },
  { title: 'Avertissement', value: 'warning' },
  { title: 'Urgent', value: 'error' },
  { title: 'Succès', value: 'success' },
]

const headers = [
  { title: 'Nom', key: 'name' },
  { title: 'Code', key: 'code' },
  { title: 'Ordre', key: 'sort_order' },
  { title: 'Actif', key: 'is_active' },
  { title: '', key: 'actions', sortable: false, width: '120px' },
]

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await adminList(props.resource)
    items.value = Array.isArray(response) ? response : (response?.data || [])
    if (props.resource === 'priorities')
      applyMailPriorities(items.value)
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Chargement impossible.'
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  editing.value = null
  form.value = {
    name: '',
    code: '',
    description: '',
    color: 'info',
    sort_order: items.value.length + 1,
    is_urgent: false,
    is_active: true,
  }
  showDialog.value = true
}

const openEdit = (item: any) => {
  editing.value = item
  form.value = {
    name: item.name || '',
    code: item.code || '',
    description: item.description || '',
    color: item.color || 'info',
    sort_order: item.sort_order ?? 0,
    is_urgent: Boolean(item.is_urgent),
    is_active: item.is_active !== false,
  }
  showDialog.value = true
}

const save = async () => {
  saving.value = true
  errorMessage.value = ''
  try {
    const body: Record<string, any> = {
      name: form.value.name.trim(),
      code: form.value.code.trim(),
      sort_order: Number(form.value.sort_order) || 0,
      is_active: form.value.is_active,
    }
    if (props.resource === 'priorities') {
      body.color = form.value.color
      body.is_urgent = form.value.is_urgent
    }
    else {
      body.description = form.value.description.trim() || null
    }

    if (editing.value)
      await adminUpdate(props.resource, editing.value.id, body)
    else
      await adminStore(props.resource, body)

    showDialog.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Enregistrement impossible.'
  }
  finally {
    saving.value = false
  }
}

const toggle = async (item: any) => {
  errorMessage.value = ''
  try {
    await adminToggle(props.resource, item.id)
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Modification impossible.'
  }
}

const remove = async (item: any) => {
  if (!confirm(`Supprimer « ${item.name} » ?`))
    return
  errorMessage.value = ''
  try {
    await adminDelete(props.resource, item.id)
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Suppression impossible.'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-4">
      <div>
        <h3 class="text-h6">
          {{ title }}
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ hint }}
        </p>
      </div>
      <VBtn
        size="small"
        variant="tonal"
        prepend-icon="tabler-plus"
        @click="openCreate"
      >
        Ajouter
      </VBtn>
    </div>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
    >
      <template #item.is_active="{ item }">
        <VChip
          size="small"
          :color="item.is_active ? 'success' : 'default'"
          variant="tonal"
        >
          {{ item.is_active ? 'Actif' : 'Inactif' }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          size="x-small"
          icon="tabler-edit"
          variant="text"
          @click="openEdit(item)"
        />
        <VBtn
          size="x-small"
          :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
          variant="text"
          @click="toggle(item)"
        />
        <VBtn
          size="x-small"
          icon="tabler-trash"
          variant="text"
          color="error"
          @click="remove(item)"
        />
      </template>
    </VDataTable>

    <VDialog
      v-model="showDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>
          {{ editing ? 'Modifier' : 'Ajouter' }}
        </VCardTitle>
        <VCardText>
          <VTextField
            v-model="form.name"
            label="Nom"
            class="mb-3"
          />
          <VTextField
            v-model="form.code"
            label="Code"
            :disabled="Boolean(editing)"
            class="mb-3"
          />
          <VTextField
            v-model.number="form.sort_order"
            label="Ordre"
            type="number"
            class="mb-3"
          />
          <VTextarea
            v-if="resource !== 'priorities'"
            v-model="form.description"
            label="Description"
            rows="2"
            class="mb-3"
          />
          <VSelect
            v-if="resource === 'priorities'"
            v-model="form.color"
            :items="colorItems"
            label="Couleur"
            class="mb-3"
          />
          <VCheckbox
            v-if="resource === 'priorities'"
            v-model="form.is_urgent"
            label="Compter comme urgent"
            hide-details
          />
          <VCheckbox
            v-model="form.is_active"
            label="Actif"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
