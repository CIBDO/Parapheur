<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'DocumentType',
  },
})

interface DocumentTypeItem {
  id: number
  code: string
  name: string
  is_active: boolean
  sort_order: number
}

const items = ref<DocumentTypeItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isDialogOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  code: '',
  name: '',
  is_active: true,
  sort_order: 0,
})

const dialogTitle = computed(() =>
  editingId.value ? 'Modifier le type' : 'Nouveau type de document',
)

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Échec de l’opération')
}

const load = async () => {
  loading.value = true
  try {
    items.value = await $api('/document-types')
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = { code: '', name: '', is_active: true, sort_order: items.value.length + 1 }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const openEdit = (item: DocumentTypeItem) => {
  editingId.value = item.id
  form.value = {
    code: item.code,
    name: item.name,
    is_active: item.is_active,
    sort_order: item.sort_order,
  }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const save = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    if (editingId.value) {
      await $api(`/document-types/${editingId.value}`, { method: 'PUT', body: form.value })
      successMessage.value = 'Type mis à jour'
    }
    else {
      await $api('/document-types', { method: 'POST', body: form.value })
      successMessage.value = 'Type créé'
    }
    isDialogOpen.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    saving.value = false
  }
}

const remove = async (item: DocumentTypeItem) => {
  if (!confirm(`Supprimer le type « ${item.code} » ?`))
    return
  try {
    await $api(`/document-types/${item.id}`, { method: 'DELETE' })
    successMessage.value = 'Type supprimé'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Types de documents"
      subtitle="Référentiel des natures de pièces du parapheur"
      icon="tabler-file-description"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouveau type
        </VBtn>
      </template>
    </ParapheurPageHeader>


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
      v-if="errorMessage && !isDialogOpen"
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
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Libellé', key: 'name' },
          { title: 'Ordre', key: 'sort_order' },
          { title: 'Statut', key: 'is_active' },
          { title: '', key: 'actions', sortable: false },
        ]"
      >
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            label
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Actif' : 'Inactif' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <IconBtn @click="openEdit(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn
              color="error"
              @click="remove(item)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="isDialogOpen"
      max-width="520"
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
              />
            </VCol>
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="form.name"
                label="Libellé"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.sort_order"
                type="number"
                label="Ordre"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                label="Actif"
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
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
