<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'Announcement',
  },
})

const categories = ref<any[]>([])
const importances = ref<any[]>([])
const dialog = ref(false)
const error = ref('')
const form = reactive({
  id: null as number | null,
  code: '',
  name: '',
  description: '',
  validation_circuit: 'direct',
  default_importance_id: null as number | null,
  acknowledgement_required: false,
  revalidate_on_change: false,
  is_active: true,
})

const circuits = [
  { title: 'Publication directe', value: 'direct' },
  { title: 'Validation de structure', value: 'structure' },
  { title: 'Circuit institutionnel', value: 'institutional' },
]

async function load() {
  ;[categories.value, importances.value] = await Promise.all([
    $api('/announcement-categories'),
    $api('/announcement-importances'),
  ])
}

function edit(category?: any) {
  form.id = category?.id ?? null
  form.code = category?.code ?? ''
  form.name = category?.name ?? ''
  form.description = category?.description ?? ''
  form.validation_circuit = category?.validation_circuit ?? 'direct'
  form.default_importance_id = category?.default_importance_id ?? null
  form.acknowledgement_required = category?.acknowledgement_required ?? false
  form.revalidate_on_change = category?.revalidate_on_change ?? false
  form.is_active = category?.is_active ?? true
  dialog.value = true
}

async function save() {
  error.value = ''
  try {
    const body = { ...form }
    if (form.id)
      await $api(`/announcement-categories/${form.id}`, { method: 'PUT', body })
    else
      await $api('/announcement-categories', { method: 'POST', body })
    dialog.value = false
    await load()
  }
  catch (err: any) {
    error.value = err?.data?.message ?? 'Enregistrement impossible.'
  }
}

onMounted(load)
</script>

<template>
  <ParapheurPageHeader
    title="Catégories d'annonces"
    subtitle="Référentiel configurable"
    icon="tabler-category"
  >
    <template #actions>
      <VBtn @click="edit()">
        Nouvelle catégorie
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <VCard>
    <VTable>
      <thead>
        <tr>
          <th>Libellé</th>
          <th>Circuit</th>
          <th>Actif</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="category in categories" :key="category.id">
          <td>{{ category.name }}</td>
          <td>{{ category.validation_circuit }}</td>
          <td>{{ category.is_active ? 'Oui' : 'Non' }}</td>
          <td class="text-end">
            <VBtn size="small" variant="text" @click="edit(category)">
              Modifier
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>

  <VDialog v-model="dialog" max-width="560">
    <VCard>
      <VCardTitle>{{ form.id ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</VCardTitle>
      <VCardText class="d-flex flex-column gap-3">
        <VAlert v-if="error" type="error" variant="tonal">
          {{ error }}
        </VAlert>
        <AppTextField v-model="form.code" label="Code" />
        <AppTextField v-model="form.name" label="Libellé" />
        <AppTextarea v-model="form.description" label="Description" />
        <AppSelect v-model="form.validation_circuit" :items="circuits" label="Circuit" />
        <AppSelect v-model="form.default_importance_id" :items="importances" item-title="name" item-value="id" label="Importance par défaut" clearable />
        <VSwitch v-model="form.acknowledgement_required" label="Accusé par défaut" />
        <VSwitch v-model="form.revalidate_on_change" label="Nouvelle validation si le texte change" />
        <VSwitch v-model="form.is_active" label="Active" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="dialog = false">
          Annuler
        </VBtn>
        <VBtn @click="save">
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
