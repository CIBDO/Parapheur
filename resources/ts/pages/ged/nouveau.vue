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
    subject: 'Ged',
  },
})

const router = useRouter()
const documentTypes = ref<Array<{ id: number; name: string }>>([])
const structures = ref<Array<{ id: number; name: string; code: string }>>([])
const categories = ref<Array<{ id: number; name: string }>>([])
const classifFlat = ref<Array<{ id: number; label: string }>>([])

const form = ref({
  object: '',
  title: '',
  description: '',
  reference: '',
  dossier_number: '',
  document_type_id: null as number | null,
  category_id: null as number | null,
  structure_id: null as number | null,
  classification_node_id: null as number | null,
  priority: 'normale',
  confidentiality: 'normal',
  expected_action: 'consultation',
  due_date: '',
  document_date: '',
  keywords: '',
  tags: '',
  language: 'fr',
  source: '',
})

const mainFile = ref<File | File[] | null>(null)
const piecesJointes = ref<File | File[] | null>(null)
const annexes = ref<File | File[] | null>(null)
const justificatifs = ref<File | File[] | null>(null)
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

const flattenNodes = (nodes: any[], prefix = ''): Array<{ id: number; label: string }> => {
  const out: Array<{ id: number; label: string }> = []
  for (const n of nodes) {
    const label = prefix ? `${prefix} / ${n.name}` : n.name
    out.push({ id: n.id, label })
    if (n.children?.length)
      out.push(...flattenNodes(n.children, label))
  }

  return out
}

const canSubmit = computed(() =>
  Boolean(form.value.object.trim() && form.value.document_type_id),
)

onMounted(async () => {
  const [types, structs, cats, classif] = await Promise.all([
    $api('/meta/document-types'),
    $api('/meta/structures'),
    $api('/meta/document-categories'),
    $api('/meta/classification-nodes'),
  ])
  documentTypes.value = types
  structures.value = structs
  categories.value = cats.data || cats
  classifFlat.value = flattenNodes(classif.data || [])
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
    Object.entries(form.value).forEach(([key, value]) => {
      if (key === 'keywords' || key === 'tags')
        return
      if (value !== null && value !== '')
        body.append(key, String(value))
    })

    if (form.value.keywords.trim()) {
      const kws = form.value.keywords.split(',').map(k => k.trim()).filter(Boolean)
      body.append('keywords', JSON.stringify(kws))
    }
    if (form.value.tags.trim()) {
      const tags = form.value.tags.split(',').map(k => k.trim().replace(/^#/, '')).filter(Boolean)
      body.append('tags', JSON.stringify(tags))
    }

    const main = asFile(mainFile.value)
    if (main)
      body.append('main_file', main)

    asFiles(piecesJointes.value).forEach(f => body.append('pieces_jointes[]', f))
    asFiles(annexes.value).forEach(f => body.append('annexes[]', f))
    asFiles(justificatifs.value).forEach(f => body.append('justificatifs[]', f))

    const doc = await $api('/ged/documents', { method: 'POST', body })
    await router.push({ name: 'ged-id', params: { id: String(doc.id) } })
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || e?.message || 'Erreur lors de l’enregistrement.'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau document GED"
      subtitle="Dépôt hors circuit parapheur (entrée éventuelle ultérieure)"
      icon="tabler-file-plus"
    />

    <VAlert
      v-if="errorMessage"
      type="error"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <AppTextField
              v-model="form.object"
              label="Objet *"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="form.title"
              label="Titre"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              label="Description"
              rows="2"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.document_type_id"
              :items="documentTypes"
              item-title="name"
              item-value="id"
              label="Type *"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.category_id"
              :items="categories"
              item-title="name"
              item-value="id"
              label="Catégorie"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.structure_id"
              :items="structures"
              item-title="name"
              item-value="id"
              label="Structure"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.classification_node_id"
              :items="classifFlat"
              item-title="label"
              item-value="id"
              label="Plan de classement"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="form.reference"
              label="Référence"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="form.dossier_number"
              label="N° dossier"
              placeholder="Auto si vide"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
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
            md="3"
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
            md="3"
          >
            <AppSelect
              v-model="form.expected_action"
              :items="expectedActionOptions"
              item-title="title"
              item-value="value"
              label="Action attendue"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
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
              v-model="form.tags"
              label="Tags"
              placeholder="SIGRAC, Microfinance, Budget2027"
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
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card mb-4">
      <VCardItem>
        <VCardTitle>Fichiers</VCardTitle>
      </VCardItem>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VFileInput
              v-model="mainFile"
              label="Document principal"
              prepend-icon="tabler-file"
              show-size
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VFileInput
              v-model="piecesJointes"
              label="Pièces jointes"
              multiple
              prepend-icon="tabler-paperclip"
              show-size
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VFileInput
              v-model="annexes"
              label="Annexes"
              multiple
              prepend-icon="tabler-files"
              show-size
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VFileInput
              v-model="justificatifs"
              label="Justificatifs"
              multiple
              prepend-icon="tabler-file-check"
              show-size
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div class="d-flex gap-2">
      <VBtn
        color="primary"
        :loading="saving"
        :disabled="!canSubmit"
        @click="submit"
      >
        Enregistrer
      </VBtn>
      <VBtn
        variant="tonal"
        :to="{ name: 'ged' }"
      >
        Annuler
      </VBtn>
    </div>
  </div>
</template>
