<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import { taskPriorityLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'create',
    subject: 'Task',
  },
})

const router = useRouter()
const { create } = useTasks()

const users = ref<{ id: number; name: string }[]>([])
const saving = ref(false)
const showAdvanced = ref(false)

const form = ref({
  title: '',
  description: '',
  assignee_id: null as number | null,
  priority: 'normale',
  due_at: '',
  as_draft: false,
  contributor_ids: [] as number[],
  confidentiality: 'normal',
})

const loadUsers = async () => {
  const res = await $api('/meta/users')
  users.value = res.data ?? res
}

const submit = async () => {
  saving.value = true
  try {
    const task = await create({
      ...form.value,
      due_at: form.value.due_at || null,
    })
    router.push(`/taches/${task.id}`)
  }
  finally {
    saving.value = false
  }
}

onMounted(loadUsers)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouvelle tâche"
      subtitle="Création rapide — les options avancées restent optionnelles"
      icon="tabler-plus"
    />

    <VCard max-width="720">
      <VCardText>
        <VForm @submit.prevent="submit">
          <AppTextField
            v-model="form.title"
            class="mb-4"
            label="Objet *"
            required
          />
          <AppSelect
            v-model="form.assignee_id"
            class="mb-4"
            label="Responsable"
            :items="users"
            item-title="name"
            item-value="id"
            clearable
          />
          <AppSelect
            v-model="form.priority"
            class="mb-4"
            label="Priorité"
            :items="Object.entries(taskPriorityLabels).map(([value, title]) => ({ title, value }))"
          />
          <AppTextField
            v-model="form.due_at"
            class="mb-4"
            label="Échéance"
            type="datetime-local"
          />
          <AppTextarea
            v-model="form.description"
            class="mb-4"
            label="Description"
            rows="4"
          />

          <VSwitch
            v-model="showAdvanced"
            class="mb-4"
            label="Options avancées"
            color="primary"
            hide-details
          />

          <template v-if="showAdvanced">
            <AppSelect
              v-model="form.contributor_ids"
              class="mb-4"
              label="Contributeurs"
              :items="users"
              item-title="name"
              item-value="id"
              multiple
              chips
            />
            <AppSelect
              v-model="form.confidentiality"
              class="mb-4"
              label="Confidentialité"
              :items="[
                { title: 'Normal', value: 'normal' },
                { title: 'Restreint', value: 'restreint' },
                { title: 'Confidentiel', value: 'confidentiel' },
              ]"
            />
            <VSwitch
              v-model="form.as_draft"
              class="mb-4"
              label="Enregistrer comme brouillon"
              color="warning"
              hide-details
            />
          </template>

          <div class="d-flex gap-3">
            <VBtn
              type="submit"
              color="primary"
              :loading="saving"
            >
              Créer
            </VBtn>
            <VBtn
              variant="tonal"
              @click="router.back()"
            >
              Annuler
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
