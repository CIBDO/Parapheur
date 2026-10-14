<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import {
  correspondenceConfidentialityLabels,
  correspondencePriorityLabels,
} from '@/utils/courrierUi'
import { $api } from '@/utils/api'

const props = defineProps<{
  modelValue: boolean
  direction: 'entrant' | 'sortant'
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  created: [item: { id: number }]
}>()

const { createCorrespondence, fetchMeta, loading } = useCorrespondence()
const userData = useCookie<Record<string, any> | null>('userData')

const errorMsg = ref('')
const structures = ref<any[]>([])
const categories = ref<any[]>([])
const priorities = ref<any[]>([])
const correspondents = ref<any[]>([])
const searching = ref(false)
const showOriginDialog = ref(false)
const originForm = ref({ sigle: '', name: '' })
const creatingOrigin = ref(false)
const scanFile = ref<File | File[] | null>(null)

const form = ref(emptyForm())

function emptyForm() {
  const today = new Date().toISOString().split('T')[0]

  return {
    subject: '',
    summary: '',
    external_reference: '',
    ministry_number: '',
    correspondence_date: today,
    received_at: new Date().toISOString().slice(0, 16),
    piece_count: 1,
    priority: 'normale',
    confidentiality: 'normal',
    category_id: null as number | null,
    correspondent_id: null as number | null,
    recipient_structure_ids: [] as number[],
    sender_structure_id: null as number | null,
  }
}

const isArrival = computed(() => props.direction === 'entrant')
const title = computed(() => isArrival.value ? 'Nouveau courrier arrivé' : 'Nouveau courrier de départ')

const structureItems = computed(() =>
  structures.value.map(s => ({
    value: s.id,
    title: s.code ? `${s.code} — ${s.name}` : s.name,
  })),
)

const categoryItems = computed(() =>
  categories.value.map(c => ({ value: c.id, title: c.name || c.code })),
)

const priorityItems = computed(() => {
  const active = priorities.value.filter(item => item.is_active !== false)
  if (!active.length)
    return Object.entries(correspondencePriorityLabels).map(([value, title]) => ({ value, title }))

  return active.map(item => ({ value: item.code, title: item.name }))
})

const correspondentItems = computed(() =>
  correspondents.value.map(c => ({
    value: c.id,
    title: c.sigle ? `${c.sigle} — ${c.name}` : c.name,
    raw: c,
  })),
)

function close() {
  emit('update:modelValue', false)
}

async function searchCorrespondents(query: string) {
  searching.value = true
  try {
    const res = await $api('/mail/correspondents', { query: { q: query || undefined, per_page: 20 } })
    correspondents.value = res.data || res || []
  }
  finally {
    searching.value = false
  }
}

async function createOrigin() {
  if (!originForm.value.sigle.trim() || !originForm.value.name.trim())
    return

  creatingOrigin.value = true
  try {
    const created = await $api('/mail/correspondents', {
      method: 'POST',
      body: {
        type: 'organisme',
        sigle: originForm.value.sigle.trim(),
        name: originForm.value.name.trim(),
        organization: originForm.value.sigle.trim(),
      },
    })
    correspondents.value = [created, ...correspondents.value.filter(c => c.id !== created.id)]
    form.value.correspondent_id = created.id
    showOriginDialog.value = false
    originForm.value = { sigle: '', name: '' }
  }
  catch (error: any) {
    errorMsg.value = error?.data?.message || 'Impossible d’enregistrer cette origine.'
  }
  finally {
    creatingOrigin.value = false
  }
}

async function submit() {
  errorMsg.value = ''
  if (!form.value.subject.trim()) {
    errorMsg.value = isArrival.value ? 'Renseignez l’objet.' : 'Renseignez la désignation.'

    return
  }

  const correspondent = correspondents.value.find(c => c.id === form.value.correspondent_id)
  const party = correspondent
    ? {
        role: isArrival.value ? 'from' : 'to',
        correspondent_id: correspondent.id,
        name: correspondent.name,
        organization: correspondent.sigle || correspondent.organization || null,
      }
    : null

  try {
    let created: { id: number }
    if (isArrival.value) {
      const body = new FormData()
      body.append('direction', 'entrant')
      body.append('medium', 'physique')
      body.append('subject', form.value.subject)
      body.append('piece_count', String(form.value.piece_count || 1))
      body.append('priority', form.value.priority)
      body.append('confidentiality', form.value.confidentiality)
      if (form.value.summary)
        body.append('summary', form.value.summary)
      if (form.value.external_reference)
        body.append('external_reference', form.value.external_reference)
      if (form.value.ministry_number)
        body.append('ministry_number', form.value.ministry_number)
      if (form.value.correspondence_date)
        body.append('correspondence_date', form.value.correspondence_date)
      if (form.value.received_at)
        body.append('received_at', form.value.received_at)
      if (form.value.category_id)
        body.append('category_id', String(form.value.category_id))
      const structureId = userData.value?.structure?.id ?? userData.value?.structure_id
      if (structureId)
        body.append('structure_id', String(structureId))
      form.value.recipient_structure_ids.forEach(id => body.append('recipient_structure_ids[]', String(id)))
      if (party) {
        body.append('parties[0][role]', party.role)
        body.append('parties[0][correspondent_id]', String(party.correspondent_id))
        body.append('parties[0][name]', party.name)
        if (party.organization)
          body.append('parties[0][organization]', party.organization)
      }
      const file = Array.isArray(scanFile.value) ? scanFile.value[0] : scanFile.value
      if (file)
        body.append('scan_file', file)
      created = await createCorrespondence(body)
    }
    else {
      if (!form.value.correspondent_id) {
        errorMsg.value = 'Indiquez le destinataire.'

        return
      }
      created = await createCorrespondence({
        direction: 'sortant',
        medium: 'hybride',
        subject: form.value.subject,
        summary: form.value.summary || undefined,
        piece_count: form.value.piece_count || 1,
        priority: form.value.priority,
        confidentiality: form.value.confidentiality,
        correspondence_date: form.value.correspondence_date || undefined,
        category_id: form.value.category_id,
        sender_structure_id: form.value.sender_structure_id,
        structure_id: form.value.sender_structure_id || userData.value?.structure?.id || userData.value?.structure_id,
        parties: party ? [party] : [],
      })
    }

    emit('created', created)
    close()
  }
  catch (error: any) {
    const errors = error?.data?.errors
    errorMsg.value = errors && typeof errors === 'object'
      ? Object.values(errors).flat().join(' ')
      : (error?.data?.message || 'Erreur lors de l’enregistrement')
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMsg.value = ''
    form.value = emptyForm()
    form.value.sender_structure_id = userData.value?.structure?.id ?? userData.value?.structure_id ?? null
    scanFile.value = null
    searchCorrespondents('')
  }
})

onMounted(async () => {
  try {
    const [structs, meta] = await Promise.all([
      $api('/meta/structures'),
      fetchMeta(),
    ])
    structures.value = Array.isArray(structs) ? structs : []
    categories.value = Array.isArray(meta.categories) ? meta.categories : (meta.categories?.data || [])
    priorities.value = Array.isArray(meta.priorities) ? meta.priorities : []
  }
  catch {
    structures.value = []
    categories.value = []
  }
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="860"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ title }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          @click="close"
        />
      </VCardTitle>
      <VCardText>
        <VAlert
          v-if="errorMsg"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errorMsg }}
        </VAlert>

        <VForm @submit.prevent="submit">
          <VRow>
            <VCol
              v-if="isArrival"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.received_at"
                label="Date/heure de réception"
                type="datetime-local"
              />
            </VCol>
            <VCol
              cols="12"
              :md="isArrival ? 6 : 4"
            >
              <AppTextField
                v-model="form.correspondence_date"
                label="Date du courrier"
                type="date"
              />
            </VCol>
            <VCol
              v-if="isArrival"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.external_reference"
                label="Référence du courrier"
              />
            </VCol>
            <VCol
              v-if="isArrival"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.ministry_number"
                label="N° ministère"
              />
            </VCol>
            <VCol cols="12">
              <div class="d-flex align-start gap-2">
                <AppAutocomplete
                  v-model="form.correspondent_id"
                  class="flex-grow-1"
                  :items="correspondentItems"
                  :label="isArrival ? 'Origine du courrier' : 'Destinataire'"
                  :placeholder="isArrival ? 'Sigle ou nom…' : 'Sigle ou nom du destinataire…'"
                  :loading="searching"
                  item-title="title"
                  item-value="value"
                  clearable
                  @update:search="searchCorrespondents"
                />
                <VBtn
                  class="mt-1"
                  icon="tabler-plus"
                  color="primary"
                  variant="tonal"
                  :title="isArrival ? 'Nouvelle origine' : 'Nouveau destinataire'"
                  @click="showOriginDialog = true"
                />
              </div>
            </VCol>
            <VCol
              v-if="isArrival"
              cols="12"
            >
              <AppAutocomplete
                v-model="form.recipient_structure_ids"
                :items="structureItems"
                label="Destinataire(s)"
                placeholder="Services"
                item-title="title"
                item-value="value"
                multiple
                chips
                closable-chips
                clearable
              />
            </VCol>
            <VCol
              v-else
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="form.sender_structure_id"
                :items="structureItems"
                label="Service émetteur"
                item-title="title"
                item-value="value"
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
                label="Type"
                clearable
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
                :items="priorityItems"
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
                v-model="form.subject"
                :label="isArrival ? 'Objet *' : 'Désignation *'"
                rows="3"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.summary"
                label="Résumé"
                rows="3"
              />
            </VCol>
            <VCol
              v-if="isArrival"
              cols="12"
            >
              <VFileInput
                v-model="scanFile"
                label="Scan / document principal"
                accept=".pdf,.doc,.docx,.jpg,.png"
                prepend-icon="tabler-paperclip"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="close"
        >
          Annuler
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          @click="submit"
        >
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="showOriginDialog"
    max-width="420"
  >
    <VCard :title="isArrival ? 'Nouvelle origine' : 'Nouveau destinataire'">
      <VCardText>
        <AppTextField
          v-model="originForm.sigle"
          class="mb-3"
          label="Sigle *"
        />
        <AppTextField
          v-model="originForm.name"
          label="Nom *"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="showOriginDialog = false">
          Annuler
        </VBtn>
        <VBtn
          color="primary"
          :loading="creatingOrigin"
          :disabled="!originForm.sigle.trim() || !originForm.name.trim()"
          @click="createOrigin"
        >
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
