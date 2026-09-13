<script setup lang="ts">
import { workspaceShareAbilityLabels } from '@/utils/workspaceUi'

const props = defineProps<{
  modelValue: boolean
  workspaceId: number
  documentId?: number | null
  folderId?: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  shared: []
}>()

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const users = ref<any[]>([])
const form = ref({
  grantee_user_id: null as number | null,
  ability: 'view',
  valid_until: null as string | null,
})
const saving = ref(false)
const errorMsg = ref('')

onMounted(async () => {
  try {
    const res = await $api('/meta/users')
    users.value = res.data || res || []
  }
  catch { /* ignore */ }
})

async function submit() {
  if (!form.value.grantee_user_id)
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/shares`, {
      method: 'POST',
      body: {
        grantee_user_id: form.value.grantee_user_id,
        ability: form.value.ability,
        document_id: props.documentId || undefined,
        folder_id: props.folderId || undefined,
        valid_until: form.value.valid_until || undefined,
      },
    })
    open.value = false
    emit('shared')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Partage impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="480"
  >
    <VCard>
      <VCardTitle>Partager</VCardTitle>
      <VCardText>
        <VAlert
          v-if="errorMsg"
          type="error"
          class="mb-3"
        >
          {{ errorMsg }}
        </VAlert>
        <VSelect
          v-model="form.grantee_user_id"
          :items="users.map((u: any) => ({ title: u.name || u.email, value: u.id }))"
          label="Utilisateur"
          class="mb-3"
        />
        <VSelect
          v-model="form.ability"
          :items="Object.entries(workspaceShareAbilityLabels).map(([value, title]) => ({ title, value }))"
          label="Droit"
          class="mb-3"
        />
        <VTextField
          v-model="form.valid_until"
          label="Expiration (facultatif)"
          type="date"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="open = false"
        >
          Annuler
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="submit"
        >
          Partager
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
