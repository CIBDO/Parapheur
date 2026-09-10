<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'all',
  },
})

interface StepForm {
  step_order: number
  name: string
  role_name: string | null
  expected_action: string
  is_optional: boolean
}

interface WorkflowItem {
  id: number
  code: string
  name: string
  description?: string
  is_active: boolean
  structure_id?: number | null
  steps?: StepForm[]
}

const workflows = ref<WorkflowItem[]>([])
const roles = ref<string[]>([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const errorMessage = ref('')

const form = ref({
  id: null as number | null,
  code: '',
  name: '',
  description: '',
  is_active: true,
  steps: [] as StepForm[],
})

const expectedActions = [
  { title: 'Information', value: 'information' },
  { title: 'Consultation', value: 'consultation' },
  { title: 'Avis', value: 'avis' },
  { title: 'Observations', value: 'observations' },
  { title: 'Instruction', value: 'instruction' },
  { title: 'Visa', value: 'visa' },
  { title: 'Validation', value: 'validation' },
]

const load = async () => {
  loading.value = true
  try {
    const [list, roleList] = await Promise.all([
      $api('/workflows'),
      $api('/users/roles').catch(() => []),
    ])
    workflows.value = list
    roles.value = Array.isArray(roleList)
      ? roleList.map((r: any) => (typeof r === 'string' ? r : r.name)).filter(Boolean)
      : []
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const openCreate = () => {
  form.value = {
    id: null,
    code: '',
    name: '',
    description: '',
    is_active: true,
    steps: [
      { step_order: 1, name: 'Étape 1', role_name: 'Agent', expected_action: 'consultation', is_optional: false },
      { step_order: 2, name: 'Étape 2', role_name: 'Directeur', expected_action: 'validation', is_optional: false },
    ],
  }
  errorMessage.value = ''
  dialog.value = true
}

const openEdit = (wf: WorkflowItem) => {
  form.value = {
    id: wf.id,
    code: wf.code,
    name: wf.name,
    description: wf.description || '',
    is_active: wf.is_active,
    steps: (wf.steps || []).map(s => ({
      step_order: s.step_order,
      name: s.name,
      role_name: s.role_name,
      expected_action: s.expected_action,
      is_optional: !!s.is_optional,
    })),
  }
  errorMessage.value = ''
  dialog.value = true
}

const addStep = () => {
  form.value.steps.push({
    step_order: form.value.steps.length + 1,
    name: `Étape ${form.value.steps.length + 1}`,
    role_name: roles.value[0] || 'Agent',
    expected_action: 'consultation',
    is_optional: false,
  })
}

const removeStep = (index: number) => {
  form.value.steps.splice(index, 1)
  form.value.steps.forEach((s, i) => { s.step_order = i + 1 })
}

const save = async () => {
  saving.value = true
  errorMessage.value = ''
  try {
    const payload = {
      code: form.value.code,
      name: form.value.name,
      description: form.value.description || null,
      is_active: form.value.is_active,
      steps: form.value.steps,
    }
    if (form.value.id) {
      await $api(`/workflows/${form.value.id}`, { method: 'PUT', body: payload })
    }
    else {
      await $api('/workflows', { method: 'POST', body: payload })
    }
    dialog.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Échec de l\'enregistrement'
  }
  finally {
    saving.value = false
  }
}

const remove = async (wf: WorkflowItem) => {
  if (!confirm(`Supprimer le circuit « ${wf.name} » ?`))
    return
  await $api(`/workflows/${wf.id}`, { method: 'DELETE' })
  await load()
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Circuits prédéfinis"
      subtitle="Configuration des workflows de circulation"
      icon="tabler-git-branch"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouveau circuit
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">

      <VDataTable
        :items="workflows"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Étapes', key: 'steps' },
          { title: 'Actif', key: 'is_active' },
          { title: '', key: 'actions', sortable: false },
        ]"
      >
        <template #item.steps="{ item }">
          {{ item.steps?.length ?? 0 }}
        </template>
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            label
          >
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="text"
            @click="openEdit(item)"
          >
            Modifier
          </VBtn>
          <VBtn
            size="small"
            variant="text"
            color="error"
            @click="remove(item)"
          >
            Supprimer
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="720"
    >
      <VCard>
        <VCardTitle>
          {{ form.id ? 'Modifier le circuit' : 'Nouveau circuit' }}
        </VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
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
              <AppTextarea
                v-model="form.description"
                label="Description"
                rows="2"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Circuit actif"
                color="primary"
              />
            </VCol>
          </VRow>

          <div class="d-flex justify-space-between align-center my-4">
            <div class="text-subtitle-1">
              Étapes
            </div>
            <VBtn
              size="small"
              variant="tonal"
              @click="addStep"
            >
              Ajouter une étape
            </VBtn>
          </div>

          <VCard
            v-for="(step, index) in form.steps"
            :key="index"
            variant="outlined"
            class="mb-3 pa-3"
          >
            <VRow dense>
              <VCol
                cols="12"
                md="1"
              >
                <AppTextField
                  v-model.number="step.step_order"
                  label="#"
                  type="number"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="step.name"
                  label="Nom"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="step.role_name"
                  :items="roles"
                  label="Rôle"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppSelect
                  v-model="step.expected_action"
                  :items="expectedActions"
                  label="Action"
                />
              </VCol>
              <VCol
                cols="12"
                md="1"
                class="d-flex align-center"
              >
                <VBtn
                  icon
                  variant="text"
                  color="error"
                  @click="removeStep(index)"
                >
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </VCol>
            </VRow>
          </VCard>
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
