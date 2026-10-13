<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'PortalApp',
  },
})

interface PortalAppItem {
  id: number
  code: string
  name: string
  url: string
  icon: string | null
  color: string | null
  description: string | null
  category: string | null
  sort_order: number
  is_active: boolean
  open_in_new_tab: boolean
}

const emptyForm = () => ({
  code: '',
  name: '',
  url: '',
  icon: 'tabler-apps',
  color: 'primary',
  description: '',
  category: '',
  sort_order: 0,
  is_active: true,
  open_in_new_tab: true,
})

const colorItems = [
  { title: 'Primaire', value: 'primary' },
  { title: 'Secondaire', value: 'secondary' },
  { title: 'Succès', value: 'success' },
  { title: 'Info', value: 'info' },
  { title: 'Avertissement', value: 'warning' },
  { title: 'Erreur', value: 'error' },
]

const items = ref<PortalAppItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isDialogOpen = ref(false)
const editingId = ref<number | null>(null)
const form = ref(emptyForm())

const dialogTitle = computed(() =>
  editingId.value ? 'Modifier l’application' : 'Nouvelle application',
)

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Échec de l’opération')
}

const load = async () => {
  loading.value = true
  try {
    items.value = await $api('/portal-apps')
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
  form.value = { ...emptyForm(), sort_order: items.value.length + 1 }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const openEdit = (item: PortalAppItem) => {
  editingId.value = item.id
  form.value = {
    code: item.code,
    name: item.name,
    url: item.url,
    icon: item.icon || 'tabler-apps',
    color: item.color || 'primary',
    description: item.description || '',
    category: item.category || '',
    sort_order: item.sort_order,
    is_active: item.is_active,
    open_in_new_tab: item.open_in_new_tab,
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
      await $api(`/portal-apps/${editingId.value}`, { method: 'PUT', body: form.value })
      successMessage.value = 'Application mise à jour'
    }
    else {
      await $api('/portal-apps', { method: 'POST', body: form.value })
      successMessage.value = 'Application créée'
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

const remove = async (item: PortalAppItem) => {
  if (!confirm(`Supprimer l’application « ${item.name} » ?`))
    return
  try {
    await $api(`/portal-apps/${item.id}`, { method: 'DELETE' })
    successMessage.value = 'Application supprimée'
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
      title="Configuration du portail"
      subtitle="Gérez les applications accessibles depuis le portail institutionnel"
      icon="tabler-apps"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          :to="{ name: 'portail' }"
        >
          Voir le portail
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouvelle application
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
          { title: 'Application', key: 'name' },
          { title: 'Code', key: 'code' },
          { title: 'Catégorie', key: 'category' },
          { title: 'URL', key: 'url' },
          { title: 'Ordre', key: 'sort_order' },
          { title: 'Statut', key: 'is_active' },
          { title: '', key: 'actions', sortable: false },
        ]"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar
              :color="item.color || 'primary'"
              variant="tonal"
              size="32"
              rounded
            >
              <VIcon
                :icon="item.icon || 'tabler-apps'"
                size="18"
              />
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.name }}
              </div>
              <div
                v-if="item.description"
                class="text-caption text-medium-emphasis text-truncate"
                style="max-width: 240px"
              >
                {{ item.description }}
              </div>
            </div>
          </div>
        </template>
        <template #item.url="{ item }">
          <a
            :href="item.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-truncate d-inline-block"
            style="max-width: 220px"
            @click.stop
          >
            {{ item.url }}
          </a>
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
      max-width="720"
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
                label="Nom"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.url"
                label="URL"
                hint="Adresse complète de l’application (https://…)"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.category"
                label="Catégorie"
                hint="Ex. Métiers, RH, Communication"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.sort_order"
                type="number"
                label="Ordre d’affichage"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.icon"
                label="Icône"
                hint="Ex. tabler-mail, tabler-building-bank"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.color"
                :items="colorItems"
                label="Couleur"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                rows="2"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                label="Active"
                color="primary"
                hide-details
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.open_in_new_tab"
                label="Ouvrir dans un nouvel onglet"
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
