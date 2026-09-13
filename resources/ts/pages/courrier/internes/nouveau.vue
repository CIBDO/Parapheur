<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondenceConfidentialityLabels,
  correspondenceMediumLabels,
  correspondencePriorityLabels,
} from '@/utils/courrierUi'
import { $api } from '@/utils/api'

definePage({
  meta: { layout: 'default', action: 'create', subject: 'Courrier' },
})

const router = useRouter()
const { createCorrespondence, loading } = useCorrespondence()
const userData = useCookie<Record<string, any> | null>('userData')
const errorMsg = ref('')

const structures = ref<any[]>([])

const form = ref({
  direction: 'interne',
  subject: '',
  summary: '',
  medium: 'electronique',
  priority: 'normale',
  confidentiality: 'normal',
  correspondence_date: new Date().toISOString().split('T')[0],
  sender_structure_id: null as number | null,
  recipient_structure_id: null as number | null,
})

const structureItems = computed(() =>
  structures.value.map(s => ({
    value: s.id,
    title: s.code ? `${s.code} — ${s.name}` : (s.name || `#${s.id}`),
  })),
)

onMounted(async () => {
  form.value.sender_structure_id = userData.value?.structure?.id
    ?? userData.value?.structure_id
    ?? null

  try {
    const structs = await $api('/meta/structures')
    structures.value = Array.isArray(structs) ? structs : []
  }
  catch {
    structures.value = []
  }
})

async function submit() {
  errorMsg.value = ''
  if (!form.value.sender_structure_id) {
    errorMsg.value = 'Sélectionnez la structure / le service expéditeur.'

    return
  }
  if (!form.value.recipient_structure_id) {
    errorMsg.value = 'Sélectionnez la structure / le service destinataire.'

    return
  }
  if (form.value.sender_structure_id === form.value.recipient_structure_id) {
    errorMsg.value = 'L’expéditeur et le destinataire doivent être des structures différentes.'

    return
  }

  try {
    const created = await createCorrespondence({
      ...form.value,
      structure_id: form.value.sender_structure_id,
    })
    router.push({ name: 'courrier-internes-id', params: { id: created.id } })
  }
  catch (e: any) {
    const errors = e?.data?.errors
    if (errors && typeof errors === 'object')
      errorMsg.value = Object.values(errors).flat().join(' ')
    else
      errorMsg.value = e?.data?.message || e.message || 'Erreur'
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau courrier interne"
      subtitle="Transmission entre structures / services"
    />

    <VAlert
      type="info"
      variant="tonal"
      class="mb-4"
    >
      Choisissez les structures existantes pour l’émetteur et le destinataire (directions, services, cabinets…).
    </VAlert>

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
              <AppSelect
                v-model="form.sender_structure_id"
                :items="structureItems"
                label="Structure / service expéditeur *"
                hint="Service qui émet le courrier interne"
                persistent-hint
                :disabled="!structureItems.length"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.recipient_structure_id"
                :items="structureItems"
                label="Structure / service destinataire *"
                hint="Service destinataire de la transmission"
                persistent-hint
                :disabled="!structureItems.length"
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
