<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondenceConfidentialityLabels,
  correspondenceMediumLabels,
  correspondencePriorityLabels,
} from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'create', subject: 'Courrier' },
})

const router = useRouter()
const { createCorrespondence, loading } = useCorrespondence()
const errorMsg = ref('')

const form = ref({
  direction: 'interne',
  subject: '',
  summary: '',
  medium: 'electronique',
  priority: 'normale',
  confidentiality: 'normal',
  correspondence_date: new Date().toISOString().split('T')[0],
  received_at: new Date().toISOString().slice(0, 16),
})

async function submit() {
  errorMsg.value = ''
  try {
    const created = await createCorrespondence(form.value)
    router.push({ name: 'courrier-internes-id', params: { id: created.id } })
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Erreur'
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau courrier interne"
      subtitle="Transmission entre directions"
    />
    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
      variant="tonal"
    >
      {{ errorMsg }}
    </VAlert>
    <VCard>
      <VCardText>
        <VForm @submit.prevent="submit">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.subject"
                label="Objet *"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="form.correspondence_date"
                label="Date"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.medium"
                :items="Object.entries(correspondenceMediumLabels).map(([value, title]) => ({ value, title }))"
                label="Support"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.priority"
                :items="Object.entries(correspondencePriorityLabels).map(([value, title]) => ({ value, title }))"
                label="Priorité"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.summary"
                label="Instruction / résumé"
                rows="3"
              />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-3 mt-4">
            <VBtn
              variant="tonal"
              :to="{ name: 'courrier-internes' }"
            >
              Annuler
            </VBtn>
            <VBtn
              type="submit"
              color="primary"
              :loading="loading"
            >
              Enregistrer
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
