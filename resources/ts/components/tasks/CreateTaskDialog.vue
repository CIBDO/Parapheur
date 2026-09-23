<script setup lang="ts">
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { useTasks } from '@/composables/useTasks'
import { taskPriorityLabels } from '@/utils/tasksUi'

interface Props {
  isDialogVisible: boolean
  redirectOnCreate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  redirectOnCreate: true,
})

const emit = defineEmits<{
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'created', task: any): void
}>()

const router = useRouter()
const { create } = useTasks()

const users = ref<{ id: number; name: string }[]>([])
const saving = ref(false)
const showAdvanced = ref(false)
const usersLoaded = ref(false)

const emptyForm = () => ({
  title: '',
  description: '',
  assignee_id: null as number | null,
  priority: 'normale',
  due_at: '',
  as_draft: false,
  contributor_ids: [] as number[],
  confidentiality: 'normal',
})

const form = ref(emptyForm())

const resetForm = () => {
  form.value = emptyForm()
  showAdvanced.value = false
}

const loadUsers = async () => {
  if (usersLoaded.value)
    return
  const res = await $api('/meta/users')
  users.value = res.data ?? res
  usersLoaded.value = true
}

watch(() => props.isDialogVisible, async (visible) => {
  if (visible) {
    resetForm()
    await loadUsers()
  }
})

const close = () => {
  emit('update:isDialogVisible', false)
}

const submit = async () => {
  if (!form.value.title.trim())
    return

  saving.value = true
  try {
    const task = await create({
      ...form.value,
      due_at: form.value.due_at || null,
    })
    emit('created', task)
    close()
    if (props.redirectOnCreate && task?.id)
      await router.push(`/taches/${task.id}`)
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : 640"
    scrollable
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <DialogCloseBtn @click="close" />

    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="40"
          >
            <VIcon
              icon="tabler-plus"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h5">
          Nouvelle tâche
        </VCardTitle>
        <VCardSubtitle>
          Création rapide — les options avancées restent optionnelles
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-5">
        <VForm @submit.prevent="submit">
          <AppTextField
            v-model="form.title"
            class="mb-4"
            label="Objet *"
            required
            autofocus
          />
          <UserAutocomplete
            v-model="form.assignee_id"
            class="mb-4"
            label="Responsable"
            :items="users"
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
            rows="3"
          />

          <VSwitch
            v-model="showAdvanced"
            class="mb-4"
            label="Options avancées"
            color="primary"
            hide-details
          />

          <template v-if="showAdvanced">
            <UserAutocomplete
              v-model="form.contributor_ids"
              class="mb-4"
              label="Contributeurs"
              :items="users"
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
              class="mb-2"
              label="Enregistrer comme brouillon"
              color="warning"
              hide-details
            />
          </template>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="!form.title.trim()"
          @click="submit"
        >
          Créer
        </VBtn>
        <VBtn
          variant="tonal"
          :disabled="saving"
          @click="close"
        >
          Annuler
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
