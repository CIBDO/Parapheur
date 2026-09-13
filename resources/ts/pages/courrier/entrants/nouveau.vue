<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondencePriorityLabels,
  correspondenceConfidentialityLabels,
  correspondenceMediumLabels,
} from '@/utils/courrierUi'
import { $api } from '@/utils/api'

definePage({
  meta: {
    layout: 'default',
    action: 'create',
    subject: 'Courrier',
  },
})

const router = useRouter()
const { createCorrespondence, fetchMeta, loading } = useCorrespondence()

const userData = useCookie<Record<string, any> | null>('userData')

const form = ref({
  direction: 'entrant',
  correspondence_date: new Date().toISOString().split('T')[0],
  received_at: new Date().toISOString().slice(0, 16),
  subject: '',
  summary: '',
  external_reference: '',
  medium: 'physique',
  priority: 'normale',
  confidentiality: 'normal',
  piece_count: 1,
  requires_reply: false,
  sender_name: '',
  recipient_name: '',
  structure_id: null as number | null,
  channel_id: null as number | null,
  category_id: null as number | null,
})

const scanFile = ref<File | null>(null)
const errorMsg = ref('')
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
  try {
    const body = new FormData()
    Object.entries(form.value).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '')
        return

      // Laravel `boolean` n'accepte pas les chaînes "true"/"false" (FormData)
      if (typeof value === 'boolean') {
        body.append(key, value ? '1' : '0')
        return
      }

      body.append(key, String(value))
    })

    const file = Array.isArray(scanFile.value) ? scanFile.value[0] : scanFile.value
    if (file)
      body.append('scan_file', file)

    const created = await createCorrespondence(body)
    router.push({ name: 'courrier-entrants-id', params: { id: created.id } })
  }
  catch (error: any) {
    const errors = error?.data?.errors
    if (errors && typeof errors === 'object') {
      errorMsg.value = Object.values(errors).flat().join(' ')
    }
    else {
      errorMsg.value = error?.data?.message || error.message || 'Erreur lors de l\'enregistrement'
    }
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau courrier entrant"
      subtitle="Enregistrement rapide bureau d'ordre"
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
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.received_at"
                label="Date/heure de réception *"
                type="datetime-local"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.correspondence_date"
                label="Date du courrier"
                type="date"
              />
            </VCol>
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
                label="Destinataire"
                placeholder="DGTCP"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.external_reference"
                label="Référence externe"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.structure_id"
                :items="structureItems"
                label="Structure de traitement *"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.channel_id"
                :items="channelItems"
                label="Canal"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.category_id"
                :items="categoryItems"
                label="Catégorie"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.medium"
                :items="Object.entries(correspondenceMediumLabels).map(([value, title]) => ({ value, title }))"
                label="Support *"
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
            <VCol cols="12">
              <AppTextarea
                v-model="form.summary"
                label="Résumé"
                rows="3"
              />
            </VCol>
            <VCol cols="12">
              <VFileInput
                v-model="scanFile"
                label="Scan / document principal"
                accept=".pdf,.doc,.docx,.jpg,.png"
                prepend-icon="tabler-paperclip"
              />
            </VCol>
          </VRow>

          <div class="d-flex gap-3 justify-end mt-4">
            <VBtn
              variant="tonal"
              :to="{ name: 'courrier-entrants' }"
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
