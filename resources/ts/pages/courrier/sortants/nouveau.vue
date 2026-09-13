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
  direction: 'sortant',
  subject: '',
  summary: '',
  medium: 'hybride',
  priority: 'normale',
  confidentiality: 'normal',
  correspondence_date: new Date().toISOString().split('T')[0],
  requires_reply: false,
  sender_name: 'DGTCP',
  recipient_name: '',
})

async function submit() {
  errorMsg.value = ''
  try {
    const created = await createCorrespondence(form.value)
    router.push({ name: 'courrier-sortants-id', params: { id: created.id } })
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Erreur'
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau courrier sortant"
      subtitle="Projet de départ"
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
              md="6"
            >
              <AppTextField
                v-model="form.sender_name"
                label="Expéditeur"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.recipient_name"
                label="Destinataire *"
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
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.confidentiality"
                :items="Object.entries(correspondenceConfidentialityLabels).map(([value, title]) => ({ value, title }))"
                label="Confidentialité"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.summary"
                label="Résumé"
                rows="3"
              />
            </VCol>
          </VRow>
          <div class="d-flex justify-end gap-3 mt-4">
            <VBtn
              variant="tonal"
              :to="{ name: 'courrier-sortants' }"
            >
              Annuler
            </VBtn>
            <VBtn
              type="submit"
              color="primary"
              :loading="loading"
            >
              Créer le projet
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
