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
const { createCorrespondence, fetchMeta, loading } = useCorrespondence()
const userData = useCookie<Record<string, any> | null>('userData')
const errorMsg = ref('')

const form = ref({
  direction: 'sortant',
  subject: '',
  summary: '',
  observations: '',
  medium: 'hybride',
  priority: 'normale',
  confidentiality: 'normal',
  correspondence_date: new Date().toISOString().split('T')[0],
  piece_count: 1,
  requires_reply: false,
  sender_name: 'DGTCP',
  recipient_name: '',
  structure_id: null as number | null,
  channel_id: null as number | null,
  category_id: null as number | null,
})

const structures = ref<any[]>([])
const channels = ref<any[]>([])
const categories = ref<any[]>([])

const structureItems = computed(() =>
  structures.value.map(s => ({ value: s.id, title: s.name || s.code })),
)
const channelItems = computed(() =>
  channels.value.map(c => ({ value: c.id, title: c.name || c.code })),
)
const categoryItems = computed(() =>
  categories.value.map(c => ({ value: c.id, title: c.name || c.code })),
)

onMounted(async () => {
  form.value.structure_id = userData.value?.structure?.id
    ?? userData.value?.structure_id
    ?? null

  try {
    const [structs, meta] = await Promise.all([
      $api('/meta/structures'),
      fetchMeta(),
    ])
    structures.value = Array.isArray(structs) ? structs : []
    channels.value = Array.isArray(meta.channels) ? meta.channels : (meta.channels?.data || [])
    categories.value = Array.isArray(meta.categories) ? meta.categories : (meta.categories?.data || [])

    if (!form.value.channel_id && channels.value.length) {
      const byMedium = form.value.medium === 'electronique'
        ? channels.value.find(c => String(c.code).toUpperCase() === 'EMAIL')
        : channels.value.find(c => String(c.code).toUpperCase() === 'COURRIER')
      form.value.channel_id = (byMedium || channels.value[0])?.id ?? null
    }
  }
  catch {
    structures.value = []
    channels.value = []
    categories.value = []
  }
})

async function submit() {
  errorMsg.value = ''
  if (!form.value.recipient_name?.trim()) {
    errorMsg.value = 'Indiquez le destinataire du courrier.'

    return
  }
  try {
    const created = await createCorrespondence(form.value)
    router.push({ name: 'courrier-sortants-id', params: { id: created.id } })
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
      title="Nouveau courrier sortant"
      subtitle="Projet de départ — le N° DEP est attribué à l’enregistrement ou à l’expédition"
    />

    <VAlert
      type="info"
      variant="tonal"
      class="mb-4"
    >
      <div class="font-weight-medium mb-1">
        Parcours sortant
      </div>
      <ol class="ps-4 mb-0 text-body-2">
        <li>
          <strong>Créer le projet</strong> (brouillon, sans numéro de départ)
        </li>
        <li>
          <strong>Enregistrer au départ</strong> → attribution du N° DEP/…
        </li>
        <li>
          <strong>Expédier</strong> → preuve d’envoi + bordereau BE (statut Expédié)
        </li>
      </ol>
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
              <AppTextField
                v-model="form.sender_name"
                label="Émetteur (structure / service)"
                hint="Qui envoie — en général DGTCP ou votre direction"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.recipient_name"
                label="Destinataire externe *"
                hint="Organisme ou personne à qui part le courrier"
                persistent-hint
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="form.correspondence_date"
                label="Date du courrier"
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
              <AppTextField
                v-model.number="form.piece_count"
                label="Nombre de pièces"
                type="number"
                min="1"
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
              md="4"
            >
              <AppSelect
                v-model="form.confidentiality"
                :items="Object.entries(correspondenceConfidentialityLabels).map(([value, title]) => ({ value, title }))"
                label="Confidentialité"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.structure_id"
                :items="structureItems"
                label="Structure émettrice"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.channel_id"
                :items="channelItems"
                label="Canal d’envoi"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.category_id"
                :items="categoryItems"
                label="Catégorie"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.summary"
                label="Résumé"
                rows="3"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.observations"
                label="Observations"
                rows="2"
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
              Créer le projet de départ
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
