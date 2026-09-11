<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  confidentialityOptions,
  expectedActionOptions,
  priorityOptions,
} from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'create',
    subject: 'Document',
    navActiveLink: 'parapheur-nouveau',
  },
})

const router = useRouter()

const documentTypes = ref<Array<{ id: number; name: string }>>([])
const structures = ref<Array<{ id: number; name: string; code: string }>>([])
const users = ref<Array<{ id: number; name: string; email: string }>>([])
const workflows = ref<Array<{ id: number; name: string; code: string }>>([])

const form = ref({
  object: '',
  reference: '',
  document_type_id: null as number | null,
  structure_id: null as number | null,
  priority: 'normale',
  confidentiality: 'normal',
  expected_action: 'validation',
  due_date: '',
  document_date: '',
  keywords: '',
  transmit_mode: 'libre' as 'none' | 'libre' | 'predefini',
  transmit_to: null as number | null,
  workflow_id: null as number | null,
  transmit_message: '',
})

const mainFile = ref<File | File[] | null>(null)
const piecesJointes = ref<File | File[] | null>(null)
const annexes = ref<File | File[] | null>(null)
const saving = ref(false)
const errorMessage = ref('')

const asFile = (value: File | File[] | null | undefined): File | null => {
  if (!value)
    return null

  return Array.isArray(value) ? (value[0] ?? null) : value
}

const asFiles = (value: File | File[] | null | undefined): File[] => {
  if (!value)
    return []

  return Array.isArray(value) ? value.filter(Boolean) : [value]
}

const canSubmit = computed(() =>
  Boolean(form.value.object.trim() && form.value.document_type_id),
)

onMounted(async () => {
  const [types, structs, people, circuits] = await Promise.all([
    $api('/meta/document-types'),
    $api('/meta/structures'),
    $api('/meta/users'),
    $api('/meta/workflows'),
  ])
  documentTypes.value = types
  structures.value = structs
  users.value = people
  workflows.value = circuits
})

const submit = async () => {
  errorMessage.value = ''
  if (!canSubmit.value) {
    errorMessage.value = 'Renseignez au minimum l’objet et le type de document.'

    return
  }

  saving.value = true
  try {
    const body = new FormData()
    const skip = ['transmit_mode', 'keywords', 'transmit_to', 'workflow_id', 'transmit_message']
    Object.entries(form.value).forEach(([key, value]) => {
      if (skip.includes(key))
        return
      if (value !== null && value !== '')
        body.append(key, String(value))
    })

    if (form.value.keywords.trim()) {
      const kws = form.value.keywords.split(',').map(k => k.trim()).filter(Boolean)
      body.append('keywords', JSON.stringify(kws))
    }

    const principal = asFile(mainFile.value)
    if (principal)
      body.append('main_file', principal)

    asFiles(piecesJointes.value).forEach(file => body.append('pieces_jointes[]', file))
    asFiles(annexes.value).forEach(file => body.append('annexes[]', file))

    if (form.value.transmit_mode === 'libre' && form.value.transmit_to) {
      body.append('transmit_to', String(form.value.transmit_to))
      if (form.value.transmit_message)
        body.append('transmit_message', form.value.transmit_message)
    }
    if (form.value.transmit_mode === 'predefini' && form.value.workflow_id) {
      body.append('workflow_id', String(form.value.workflow_id))
      if (form.value.transmit_message)
        body.append('transmit_message', form.value.transmit_message)
    }

    const doc = await $api('/parapheur/documents', {
      method: 'POST',
      body,
    })

    await router.push({ name: 'parapheur-id', params: { id: doc.id } })
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Échec de l\'enregistrement'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau document"
      subtitle="Déposez un dossier et lancez sa circulation"
      icon="tabler-file-plus"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="secondary"
          :to="{ name: 'parapheur' }"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-6"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VCardText class="pa-6">
        <div class="parapheur-form-section">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-file-description"
              color="primary"
              size="20"
            />
            Identification
          </div>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.object"
                label="Objet *"
                placeholder="Ex. Compte rendu réunion DSI"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.reference"
                label="Référence"
                placeholder="Auto si vide"
                hint="Laissée vide, une référence structure/année est générée"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.document_type_id"
                :items="documentTypes"
                item-title="name"
                item-value="id"
                label="Type de document *"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.structure_id"
                :items="structures"
                :item-title="(i: any) => `${i.code} — ${i.name}`"
                item-value="id"
                label="Structure émettrice"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.expected_action"
                :items="expectedActionOptions"
                item-title="title"
                item-value="value"
                label="Action attendue"
              />
            </VCol>
          </VRow>
        </div>

        <div class="parapheur-form-section">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-adjustments"
              color="primary"
              size="20"
            />
            Priorité & confidentialité
          </div>
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.priority"
                :items="priorityOptions"
                item-title="title"
                item-value="value"
                label="Priorité"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.confidentiality"
                :items="confidentialityOptions"
                item-title="title"
                item-value="value"
                label="Confidentialité"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model="form.due_date"
                type="date"
                label="Date limite"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.document_date"
                type="date"
                label="Date du document"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.keywords"
                label="Mots-clés"
                placeholder="séparés par des virgules"
              />
            </VCol>
          </VRow>
        </div>

        <div class="parapheur-form-section">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-paperclip"
              color="primary"
              size="20"
            />
            Fichiers
          </div>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
            density="compact"
          >
            Le <strong>document principal</strong> est le fichier à viser / valider.
            Vous pouvez joindre plusieurs <strong>pièces jointes</strong> et <strong>annexes</strong>.
          </VAlert>
          <VRow>
            <VCol cols="12">
              <VFileInput
                v-model="mainFile"
                label="Document principal *"
                prepend-icon=""
                prepend-inner-icon="tabler-file"
                show-size
                clearable
                hint="PDF recommandé pour la prévisualisation"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VFileInput
                v-model="piecesJointes"
                label="Pièces jointes"
                prepend-icon=""
                prepend-inner-icon="tabler-paperclip"
                multiple
                chips
                show-size
                clearable
                hint="Plusieurs fichiers autorisés"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VFileInput
                v-model="annexes"
                label="Annexes"
                prepend-icon=""
                prepend-inner-icon="tabler-files"
                multiple
                chips
                show-size
                clearable
                hint="Plusieurs fichiers autorisés"
                persistent-hint
              />
            </VCol>
          </VRow>
        </div>

        <div class="parapheur-form-section mb-0">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-send"
              color="primary"
              size="20"
            />
            Transmission
          </div>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="form.transmit_mode"
                :items="[
                  { title: 'Enregistrer en brouillon (sans transmission)', value: 'none' },
                  { title: 'Transmission libre', value: 'libre' },
                  { title: 'Circuit prédéfini', value: 'predefini' },
                ]"
                item-title="title"
                item-value="value"
                label="Mode de transmission"
              />
            </VCol>
            <VCol
              v-if="form.transmit_mode === 'libre'"
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.transmit_to"
                :items="users"
                item-title="name"
                item-value="id"
                label="Transmettre à"
                clearable
              />
            </VCol>
            <VCol
              v-if="form.transmit_mode === 'predefini'"
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.workflow_id"
                :items="workflows"
                item-title="name"
                item-value="id"
                label="Circuit"
                clearable
              />
            </VCol>
            <VCol
              v-if="form.transmit_mode !== 'none'"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.transmit_message"
                label="Message de transmission"
              />
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          variant="text"
          :to="{ name: 'parapheur' }"
        >
          Annuler
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          size="large"
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          :disabled="!canSubmit"
          @click="submit"
        >
          Enregistrer le dossier
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
