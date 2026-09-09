<script setup lang="ts">
const route = useRoute('parapheur-id')
const ability = useAbility()

definePage({
  meta: {
    action: 'read',
    subject: 'Parapheur',
  },
})

const document = ref<any>(null)
const loading = ref(true)
const actionComment = ref('')
const actionError = ref('')
const busy = ref(false)
const users = ref<Array<{ id: number; name: string }>>([])
const workflows = ref<Array<{ id: number; name: string; code: string; steps?: any[] }>>([])
const versionFile = ref<File[]>([])
const versionNote = ref('')
const attachmentFile = ref<File[]>([])
const attachmentKind = ref('piece_jointe')

const transmitForm = ref({
  intent: 'transmit' as 'transmit' | 'reassign',
  mode: 'libre' as 'libre' | 'predefini',
  to_user_id: null as number | null,
  workflow_id: null as number | null,
  expected_action: 'consultation',
  message: '',
})

const instructionForm = ref({
  assignee_id: null as number | null,
  title: 'Instruction DG',
  body: '',
  due_date: '',
})

const expectedActions = [
  { title: 'Pour information', value: 'information' },
  { title: 'Pour consultation', value: 'consultation' },
  { title: 'Pour avis', value: 'avis' },
  { title: 'Pour observations', value: 'observations' },
  { title: 'Pour instruction', value: 'instruction' },
  { title: 'Pour visa', value: 'visa' },
  { title: 'Pour validation', value: 'validation' },
]

const canAct = computed(() => ability.can('act', 'Document') || ability.can('manage', 'Document') || ability.can('manage', 'all'))
const canVise = computed(() => ability.can('vise', 'Document') || ability.can('manage', 'all'))
const canValidate = computed(() => ability.can('validate', 'Document') || ability.can('manage', 'all'))
const canInstruct = computed(() => ability.can('manage', 'Instruction') || ability.can('manage', 'all'))
const canMutate = computed(() => canAct.value && !['archive', 'annule'].includes(document.value?.status))

const load = async () => {
  loading.value = true
  try {
    document.value = await $api(`/parapheur/documents/${route.params.id}`)
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  const [people, circuits] = await Promise.all([
    $api('/meta/users'),
    $api('/meta/workflows'),
  ])
  users.value = people
  workflows.value = circuits
  await load()
})

const runAction = async (path: string, body: Record<string, unknown> = {}) => {
  actionError.value = ''
  if (['return', 'complement', 'reject'].includes(path) && !actionComment.value.trim() && !body.comment && !body.body) {
    actionError.value = 'Un commentaire / motif est obligatoire pour cette action.'

    return
  }
  if (['comments'].includes(path) && !actionComment.value.trim() && !body.body) {
    actionError.value = 'Saisissez un texte avant d\'envoyer.'

    return
  }

  busy.value = true
  try {
    await $api(`/parapheur/documents/${route.params.id}/${path}`, {
      method: 'POST',
      body: { comment: actionComment.value || undefined, ...body },
    })
    actionComment.value = ''
    await load()
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Action refusée ou en échec'
  }
  finally {
    busy.value = false
  }
}

const createInstruction = async () => {
  busy.value = true
  actionError.value = ''
  try {
    await $api(`/parapheur/documents/${route.params.id}/instructions`, {
      method: 'POST',
      body: instructionForm.value,
    })
    await load()
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Impossible de créer l\'instruction'
  }
  finally {
    busy.value = false
  }
}

const uploadVersion = async () => {
  if (!versionFile.value[0])
    return
  busy.value = true
  try {
    const body = new FormData()
    body.append('file', versionFile.value[0])
    if (versionNote.value)
      body.append('change_note', versionNote.value)
    await $api(`/parapheur/documents/${route.params.id}/versions`, { method: 'POST', body })
    versionFile.value = []
    versionNote.value = ''
    await load()
  }
  finally {
    busy.value = false
  }
}

const uploadAttachment = async () => {
  if (!attachmentFile.value[0])
    return
  busy.value = true
  try {
    const body = new FormData()
    body.append('file', attachmentFile.value[0])
    body.append('kind', attachmentKind.value)
    await $api(`/parapheur/documents/${route.params.id}/attachments`, { method: 'POST', body })
    attachmentFile.value = []
    await load()
  }
  finally {
    busy.value = false
  }
}

const transmitOrReassign = async () => {
  actionError.value = ''
  busy.value = true
  try {
    if (transmitForm.value.intent === 'reassign') {
      if (!transmitForm.value.to_user_id) {
        actionError.value = 'Destinataire requis pour la réaffectation.'

        return
      }
      await $api(`/parapheur/documents/${route.params.id}/reassign`, {
        method: 'POST',
        body: {
          to_user_id: transmitForm.value.to_user_id,
          expected_action: transmitForm.value.expected_action,
          message: transmitForm.value.message || undefined,
        },
      })
    }
    else {
      const body: Record<string, unknown> = {
        expected_action: transmitForm.value.expected_action,
        message: transmitForm.value.message || undefined,
      }
      if (transmitForm.value.mode === 'predefini')
        body.workflow_id = transmitForm.value.workflow_id
      else
        body.to_user_id = transmitForm.value.to_user_id

      await $api(`/parapheur/documents/${route.params.id}/transmit`, { method: 'POST', body })
    }
    await load()
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Transmission / réaffectation en échec'
  }
  finally {
    busy.value = false
  }
}

const downloadArchivePack = async () => {
  busy.value = true
  actionError.value = ''
  try {
    const blob = await $api(`/parapheur/documents/${route.params.id}/archive-pack`, {
      responseType: 'blob',
    }) as Blob
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `dossier_${document.value?.reference || route.params.id}.zip`
    a.click()
    URL.revokeObjectURL(url)
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Export pack impossible'
  }
  finally {
    busy.value = false
  }
}

const mainVersion = computed(() => document.value?.versions?.[0] || null)
const streamUrl = computed(() => {
  const preview = mainVersion.value?.preview
  if (preview?.mode === 'pdf_iframe')
    return preview.url
  return (mainVersion.value?.mime_type || '').includes('pdf') ? mainVersion.value?.stream_url : null
})
const isOffice = computed(() => mainVersion.value?.preview?.mode === 'office_download')

const circuitSteps = computed(() => {
  const steps = document.value?.workflow_instance?.workflow?.steps || []
  const current = document.value?.workflow_instance?.current_step_order
  return [...steps].sort((a: any, b: any) => a.step_order - b.step_order).map((step: any) => ({
    ...step,
    done: current ? step.step_order < current : false,
    current: current ? step.step_order === current : false,
  }))
})

const kindLabel = (kind: string) => {
  const map: Record<string, string> = {
    general: 'commentaire',
    avis: 'avis',
    observation: 'observation',
    recommandation: 'recommandation',
  }
  return map[kind] || kind
}
</script>

<template>
  <div v-if="loading">
    <VProgressLinear indeterminate />
  </div>

  <div v-else-if="document">
    <div class="d-flex flex-wrap justify-space-between gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ document.object }}
        </h4>
        <div class="text-body-1">
          {{ document.reference }} · {{ document.type?.name }} · {{ document.structure?.code }}
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <VChip label>
          {{ document.status }}
        </VChip>
        <VChip
          label
          color="warning"
        >
          {{ document.priority }}
        </VChip>
        <VChip
          label
          color="info"
        >
          {{ document.expected_action }}
        </VChip>
        <VBtn
          v-if="document.status === 'archive'"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-package-export"
          :loading="busy"
          @click="downloadArchivePack"
        >
          Export pack
        </VBtn>
      </div>
    </div>

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="mb-6">
          <VCardTitle>Fiche du dossier</VCardTitle>
          <VCardText>
            <VRow dense>
              <VCol cols="6">
                <strong>Auteur</strong>
                <div>{{ document.author?.name }}</div>
              </VCol>
              <VCol cols="6">
                <strong>Destinataire actuel</strong>
                <div>{{ document.current_assignee?.name || '—' }}</div>
              </VCol>
              <VCol cols="6">
                <strong>Confidentialité</strong>
                <div>{{ document.confidentiality }}</div>
              </VCol>
              <VCol cols="6">
                <strong>Échéance</strong>
                <div>{{ document.due_date || '—' }}</div>
              </VCol>
              <VCol cols="6">
                <strong>Date document</strong>
                <div>{{ document.document_date || '—' }}</div>
              </VCol>
              <VCol cols="6">
                <strong>Mots-clés</strong>
                <div>
                  <template v-if="document.keywords?.length">
                    <VChip
                      v-for="kw in document.keywords"
                      :key="kw"
                      size="small"
                      class="me-1"
                      label
                    >
                      {{ kw }}
                    </VChip>
                  </template>
                  <span v-else>—</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Document principal</VCardTitle>
          <VCardText>
            <div
              v-if="document.versions?.length"
              class="d-flex flex-column gap-2 mb-4"
            >
              <div
                v-for="version in document.versions"
                :key="version.id"
                class="d-flex justify-space-between align-center flex-wrap gap-2"
              >
                <span>
                  V{{ version.version_number }} — {{ version.original_name }}
                  <span
                    v-if="version.change_note"
                    class="text-caption text-medium-emphasis"
                  > ({{ version.change_note }})</span>
                </span>
                <VBtn
                  size="small"
                  variant="tonal"
                  :href="version.download_url"
                  target="_blank"
                >
                  Télécharger
                </VBtn>
              </div>
            </div>
            <div v-else class="mb-4">
              Aucun fichier
            </div>

            <iframe
              v-if="streamUrl"
              class="mt-2 w-100"
              style="min-block-size: 480px; border: 0"
              :src="streamUrl"
            />
            <VAlert
              v-else-if="isOffice"
              type="info"
              variant="tonal"
              class="mt-2"
            >
              Document Office : prévisualisation basique indisponible (édition collaborative prévue au Temps 2).
              <VBtn
                class="ms-2"
                size="small"
                :href="mainVersion?.download_url"
                target="_blank"
              >
                Télécharger
              </VBtn>
            </VAlert>

            <VDivider
              v-if="canMutate"
              class="my-4"
            />
            <div
              v-if="canMutate"
              class="d-flex flex-column gap-3"
            >
              <div class="text-subtitle-2">
                Nouvelle version (immuable)
              </div>
              <VFileInput
                v-model="versionFile"
                label="Fichier de remplacement"
                show-size
                density="compact"
              />
              <AppTextField
                v-model="versionNote"
                label="Note de changement"
                density="compact"
              />
              <VBtn
                color="primary"
                :loading="busy"
                :disabled="!versionFile.length"
                @click="uploadVersion"
              >
                Déposer la version
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Pièces jointes / annexes</VCardTitle>
          <VCardText>
            <div
              v-if="document.attachments?.length"
              class="d-flex flex-column gap-2 mb-4"
            >
              <div
                v-for="att in document.attachments"
                :key="att.id"
                class="d-flex justify-space-between align-center"
              >
                <span>{{ att.original_name }} <VChip size="x-small" label>{{ att.kind }}</VChip></span>
                <VBtn
                  size="small"
                  variant="tonal"
                  :href="att.download_url"
                  target="_blank"
                >
                  Télécharger
                </VBtn>
              </div>
            </div>
            <div
              v-else
              class="mb-4 text-medium-emphasis"
            >
              Aucune pièce jointe
            </div>

            <div
              v-if="canMutate"
              class="d-flex flex-column gap-3"
            >
              <AppSelect
                v-model="attachmentKind"
                :items="[
                  { title: 'Pièce jointe', value: 'piece_jointe' },
                  { title: 'Annexe', value: 'annexe' },
                  { title: 'Complément', value: 'complement' },
                ]"
                label="Type"
                density="compact"
              />
              <VFileInput
                v-model="attachmentFile"
                label="Ajouter un fichier"
                show-size
                density="compact"
              />
              <VBtn
                variant="tonal"
                :loading="busy"
                :disabled="!attachmentFile.length"
                @click="uploadAttachment"
              >
                Ajouter
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Observations & actions</VCardTitle>
          <VCardText>
            <div
              v-for="comment in document.comments"
              :key="comment.id"
              class="mb-4"
            >
              <div class="font-weight-medium">
                {{ comment.user?.name }} · {{ kindLabel(comment.kind) }}
              </div>
              <div>{{ comment.body }}</div>
            </div>
            <VAlert
              v-if="actionError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ actionError }}
            </VAlert>
            <AppTextarea
              v-model="actionComment"
              label="Commentaire / motif d'action"
              rows="3"
              class="mb-4"
            />
            <div
              v-if="canAct"
              class="d-flex flex-wrap gap-2"
            >
              <VBtn
                :loading="busy"
                @click="runAction('comments', { body: actionComment, kind: 'general' })"
              >
                Commenter
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="runAction('comments', { body: actionComment, kind: 'avis' })"
              >
                Avis
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="runAction('comments', { body: actionComment, kind: 'recommandation' })"
              >
                Recommander
              </VBtn>
              <VBtn
                color="secondary"
                :loading="busy"
                @click="runAction('acknowledge')"
              >
                Prise de connaissance
              </VBtn>
              <VBtn
                color="warning"
                :loading="busy"
                @click="runAction('return')"
              >
                Retourner
              </VBtn>
              <VBtn
                color="warning"
                variant="tonal"
                :loading="busy"
                @click="runAction('complement')"
              >
                Demander complément
              </VBtn>
              <VBtn
                v-if="canVise"
                color="info"
                :loading="busy"
                @click="runAction('vise')"
              >
                Viser
              </VBtn>
              <VBtn
                v-if="canValidate"
                color="success"
                :loading="busy"
                @click="runAction('validate')"
              >
                Valider
              </VBtn>
              <VBtn
                v-if="canVise || canValidate"
                color="error"
                :loading="busy"
                @click="runAction('reject')"
              >
                Rejeter
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="runAction('hold')"
              >
                En attente
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="runAction('classify')"
              >
                Classer
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="runAction('archive')"
              >
                Archiver
              </VBtn>
            </div>
            <VAlert
              v-else
              type="info"
              variant="tonal"
              class="mt-2"
            >
              Consultation uniquement — vous n'avez pas les droits d'action sur ce dossier.
            </VAlert>
            <p class="text-caption text-medium-emphasis mt-3 mb-0">
              Les actes de visa et de validation sont administratifs (pas de signature électronique).
            </p>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="mb-6">
          <VCardTitle>Circuit</VCardTitle>
          <VCardText>
            <div
              v-if="document.workflow_instance"
              class="mb-4"
            >
              <div class="text-caption mb-1">
                Mode : {{ document.workflow_instance.kind }}
                <span v-if="document.workflow_instance.workflow">
                  — {{ document.workflow_instance.workflow.name }}
                </span>
              </div>
              <VTimeline
                v-if="circuitSteps.length"
                density="compact"
                side="end"
              >
                <VTimelineItem
                  v-for="step in circuitSteps"
                  :key="step.id"
                  size="x-small"
                  :dot-color="step.current ? 'primary' : (step.done ? 'success' : 'secondary')"
                >
                  <div class="font-weight-medium">
                    {{ step.name }}
                  </div>
                  <div class="text-caption">
                    {{ step.role_name }} · {{ step.expected_action }}
                    <span v-if="step.current"> ← en cours</span>
                  </div>
                </VTimelineItem>
              </VTimeline>
            </div>
            <div
              v-else
              class="text-medium-emphasis mb-4"
            >
              Aucun circuit démarré
            </div>

            <div class="text-subtitle-2 mb-2">
              Transmissions
            </div>
            <div
              v-for="t in document.transmissions"
              :key="t.id"
              class="mb-3 text-body-2"
            >
              <strong>{{ t.from_user?.name }}</strong> → {{ t.to_user?.name }}
              <div class="text-caption">
                {{ t.expected_action }} · {{ t.folder }} · {{ t.status }}
              </div>
              <div
                v-if="t.message"
                class="text-caption"
              >
                {{ t.message }}
              </div>
            </div>
          </VCardText>
        </VCard>

        <VCard
          v-if="canMutate"
          class="mb-6"
        >
          <VCardTitle>Transmettre / réaffecter</VCardTitle>
          <VCardText>
            <VBtnToggle
              v-model="transmitForm.intent"
              mandatory
              density="compact"
              class="mb-3"
            >
              <VBtn value="transmit">
                Transmettre
              </VBtn>
              <VBtn value="reassign">
                Réaffecter
              </VBtn>
            </VBtnToggle>

            <VBtnToggle
              v-if="transmitForm.intent === 'transmit'"
              v-model="transmitForm.mode"
              mandatory
              density="compact"
              class="mb-3"
            >
              <VBtn value="libre">
                Libre
              </VBtn>
              <VBtn value="predefini">
                Circuit
              </VBtn>
            </VBtnToggle>

            <AppSelect
              v-if="transmitForm.intent === 'reassign' || transmitForm.mode === 'libre'"
              v-model="transmitForm.to_user_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Destinataire"
              class="mb-3"
            />
            <AppSelect
              v-else
              v-model="transmitForm.workflow_id"
              :items="workflows"
              item-title="name"
              item-value="id"
              label="Circuit prédéfini"
              class="mb-3"
            />
            <AppSelect
              v-model="transmitForm.expected_action"
              :items="expectedActions"
              label="Action attendue"
              class="mb-3"
            />
            <AppTextField
              v-model="transmitForm.message"
              label="Message"
              class="mb-3"
            />
            <VBtn
              block
              color="primary"
              :loading="busy"
              @click="transmitOrReassign"
            >
              {{ transmitForm.intent === 'reassign' ? 'Réaffecter' : 'Transmettre' }}
            </VBtn>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Historique</VCardTitle>
          <VCardText>
            <VTimeline
              density="compact"
              side="end"
            >
              <VTimelineItem
                v-for="action in document.actions"
                :key="action.id"
                size="x-small"
              >
                <div class="font-weight-medium">
                  {{ action.action_type }}
                </div>
                <div class="text-caption">
                  {{ action.actor?.name }}
                  <span v-if="action.delegator"> (délégation de {{ action.delegator.name }})</span>
                </div>
                <div v-if="action.comment">
                  {{ action.comment }}
                </div>
              </VTimelineItem>
            </VTimeline>
          </VCardText>
        </VCard>

        <VCard v-if="canInstruct">
          <VCardTitle>Créer une instruction</VCardTitle>
          <VCardText>
            <AppTextField
              v-model="instructionForm.title"
              label="Titre"
              class="mb-3"
            />
            <AppTextarea
              v-model="instructionForm.body"
              label="Instruction"
              class="mb-3"
            />
            <AppSelect
              v-model="instructionForm.assignee_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Responsable"
              class="mb-3"
            />
            <AppTextField
              v-model="instructionForm.due_date"
              type="date"
              label="Échéance"
              class="mb-3"
            />
            <VBtn
              block
              color="primary"
              :loading="busy"
              @click="createInstruction"
            >
              Enregistrer l'instruction
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
