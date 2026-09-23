<script setup lang="ts">
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'

const route = useRoute('parapheur-id')
const ability = useAbility()

definePage({
  meta: {
    action: 'read',
    subject: 'Parapheur',
    navActiveLink: 'parapheur',
  },
})

type FileInputValue = File | File[] | null

const dossier = ref<any>(null)
const loading = ref(true)
const actionComment = ref('')
const actionError = ref('')
const busy = ref(false)
const users = ref<Array<{ id: number; name: string }>>([])
const workflows = ref<Array<{ id: number; name: string; code: string; steps?: any[] }>>([])
const versionFile = ref<FileInputValue>(null)
const versionNote = ref('')
const attachmentFile = ref<FileInputValue>(null)
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

const statusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  depose: 'Déposé',
  en_circuit: 'En circuit',
  transmis: 'Transmis',
  a_consulter: 'À consulter',
  en_consultation: 'En consultation',
  en_attente: 'En attente',
  a_corriger: 'À corriger',
  corrige: 'Corrigé',
  a_viser: 'À viser',
  vise: 'Visé',
  a_valider: 'À valider',
  valide: 'Validé',
  rejete: 'Rejeté',
  traite: 'Traité',
  classe: 'Classé',
  archive: 'Archivé',
  annule: 'Annulé',
}

const priorityLabels: Record<string, string> = {
  normale: 'Normale',
  importante: 'Importante',
  urgente: 'Urgente',
  tres_urgente: 'Très urgente',
}

const confidentialityLabels: Record<string, string> = {
  normal: 'Normal',
  restreint: 'Restreint',
  confidentiel: 'Confidentiel',
  tres_confidentiel: 'Très confidentiel',
}

const actionLabels: Record<string, string> = {
  information: 'Pour information',
  consultation: 'Pour consultation',
  avis: 'Pour avis',
  observations: 'Pour observations',
  instruction: 'Pour instruction',
  visa: 'Pour visa',
  validation: 'Pour validation',
}

const folderLabels: Record<string, string> = {
  a_traiter: 'À traiter',
  a_consulter: 'À consulter',
  pour_information: 'Pour information',
  a_viser: 'À viser',
  a_valider: 'À valider',
  en_attente: 'En attente',
  retournes: 'Retournés',
  envoyes: 'Envoyés',
  traites: 'Traités',
  archives: 'Archivés',
}

const historyLabels: Record<string, string> = {
  prise_connaissance: 'Prise de connaissance',
  commenter: 'Commentaire',
  avis: 'Avis',
  recommandation: 'Recommandation',
  instruction: 'Instruction',
  demande_complement: 'Demande de complément',
  retour_correction: 'Retour pour correction',
  valider: 'Validation',
  rejeter: 'Rejet',
  viser: 'Visa',
  transmettre: 'Transmission',
  reaffecter: 'Réaffectation',
  mettre_en_attente: 'Mise en attente',
  classer: 'Classement',
  archiver: 'Archivage',
}

const transmissionStatusLabels: Record<string, string> = {
  pending: 'En attente',
  seen: 'Vu',
  done: 'Traité',
}

const kindLabels: Record<string, string> = {
  general: 'Commentaire',
  avis: 'Avis',
  observation: 'Observation',
  recommandation: 'Recommandation',
  piece_jointe: 'Pièce jointe',
  annexe: 'Annexe',
  complement: 'Complément',
}

const canActPermission = computed(() => ability.can('act', 'Document') || ability.can('manage', 'Document') || ability.can('manage', 'all'))
const canVisePermission = computed(() => ability.can('vise', 'Document') || ability.can('manage', 'all'))
const canValidatePermission = computed(() => ability.can('validate', 'Document') || ability.can('manage', 'all'))
const canInstruct = computed(() => ability.can('manage', 'Instruction') || ability.can('manage', 'all'))
const isAdmin = computed(() => ability.can('manage', 'all'))

const userData = useCookie<any>('userData')
const currentUserId = computed(() => Number(userData.value?.id || 0))
const isFrozen = computed(() => ['archive', 'annule'].includes(dossier.value?.status))
const isAssignee = computed(() =>
  Boolean(dossier.value?.current_assignee_id)
  && Number(dossier.value.current_assignee_id) === currentUserId.value,
)
const isAuthor = computed(() =>
  Boolean(dossier.value?.author_id)
  && Number(dossier.value.author_id) === currentUserId.value,
)
const isInitiatorSpectator = computed(() =>
  isAuthor.value && !isAssignee.value && !isAdmin.value && Boolean(dossier.value?.current_assignee_id),
)

/** Dossier revenu à l’auteur pour correction / complément */
const isReturnedToAuthor = computed(() =>
  isAuthor.value
  && isAssignee.value
  && ['a_corriger', 'corrige'].includes(dossier.value?.status),
)

/** Actions destinataire : viser, valider, retourner, etc. */
const canProcess = computed(() =>
  !isFrozen.value
  && canActPermission.value
  && (isAssignee.value || isAdmin.value),
)
const canVise = computed(() => canProcess.value && canVisePermission.value)
const canValidate = computed(() => canProcess.value && canValidatePermission.value)
const canComment = computed(() => canProcess.value)
const canMutate = computed(() => {
  if (isFrozen.value || !canActPermission.value)
    return false
  if (isAdmin.value || isAssignee.value)
    return true

  // Auteur uniquement tant que le dossier n’est pas encore chez un destinataire
  return isAuthor.value
    && !dossier.value?.current_assignee_id
    && ['brouillon', 'depose', 'a_corriger', 'corrige'].includes(dossier.value?.status)
})
const canTransmit = computed(() => {
  if (isFrozen.value || !canActPermission.value)
    return false
  if (isAdmin.value || isAssignee.value)
    return true

  return isAuthor.value
    && !dossier.value?.current_assignee_id
    && ['brouillon', 'depose', 'a_corriger', 'corrige'].includes(dossier.value?.status)
})

/** Action attendue + statut → boutons visibles (pas le panneau figé). */
const expectedAction = computed(() => String(dossier.value?.expected_action || ''))
const dossierStatus = computed(() => String(dossier.value?.status || ''))

const consultExpectedActions = ['information', 'consultation', 'avis', 'observations', 'instruction']
const endStatuses = ['valide', 'vise', 'traite', 'classe']
const blockedTreatStatuses = ['archive', 'annule', 'en_attente', ...endStatuses]

const canShowProcessActions = computed(() =>
  canProcess.value && !isFrozen.value && !isReturnedToAuthor.value,
)

const showComments = computed(() => canShowProcessActions.value)
const showAcknowledge = computed(() =>
  canShowProcessActions.value
  && consultExpectedActions.includes(expectedAction.value)
  && !blockedTreatStatuses.includes(dossierStatus.value),
)
const showVise = computed(() =>
  canShowProcessActions.value
  && canVisePermission.value
  && expectedAction.value === 'visa'
  && !blockedTreatStatuses.includes(dossierStatus.value),
)
const showValidate = computed(() =>
  canShowProcessActions.value
  && canValidatePermission.value
  && expectedAction.value === 'validation'
  && !blockedTreatStatuses.includes(dossierStatus.value),
)
const showReject = computed(() => showVise.value || showValidate.value)
const showCirculation = computed(() =>
  canShowProcessActions.value
  && !['valide', 'vise', 'traite', 'classe', 'archive', 'annule'].includes(dossierStatus.value),
)
const showFiling = computed(() =>
  canProcess.value
  && !isFrozen.value
  && endStatuses.includes(dossierStatus.value),
)

const showTreatGroup = computed(() =>
  showAcknowledge.value || showVise.value || showValidate.value || showReject.value,
)
const showActionComment = computed(() =>
  showComments.value || showTreatGroup.value || showCirculation.value || showFiling.value,
)
const showActionPanel = computed(() =>
  showComments.value || showTreatGroup.value || showCirculation.value || showFiling.value,
)

const asFile = (value: FileInputValue | undefined): File | null => {
  if (!value)
    return null

  return Array.isArray(value) ? (value[0] ?? null) : value
}

const labelOf = (map: Record<string, string>, value?: string | null, fallback = '—') => {
  if (!value)
    return fallback

  return map[value] || value
}

const formatDate = (value?: string | null) => {
  if (!value)
    return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return '—'

  return date.toLocaleDateString('fr-FR')
}

const formatDateTime = (value?: string | null) => {
  if (!value)
    return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return ''

  return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

const formatBytes = (bytes?: number | null) => {
  if (!bytes)
    return ''
  if (bytes < 1024)
    return `${bytes} o`
  if (bytes < 1024 * 1024)
    return `${(bytes / 1024).toFixed(1)} Ko`

  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
}

const statusColor = (status?: string) => {
  if (['valide', 'vise', 'traite', 'classe'].includes(status || ''))
    return 'success'
  if (['rejete', 'annule', 'a_corriger'].includes(status || ''))
    return 'error'
  if (['urgente', 'en_attente'].includes(status || ''))
    return 'warning'
  if (['a_valider', 'a_viser', 'a_consulter', 'en_circuit'].includes(status || ''))
    return 'info'

  return 'secondary'
}

const priorityColor = (priority?: string) => {
  if (priority === 'tres_urgente' || priority === 'urgente')
    return 'error'
  if (priority === 'importante')
    return 'warning'

  return 'secondary'
}

const load = async () => {
  loading.value = true
  try {
    dossier.value = await $api(`/parapheur/documents/${route.params.id}`)
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
  const file = asFile(versionFile.value)
  if (!file)
    return
  busy.value = true
  actionError.value = ''
  try {
    const body = new FormData()
    body.append('file', file)
    if (versionNote.value)
      body.append('change_note', versionNote.value)
    await $api(`/parapheur/documents/${route.params.id}/versions`, { method: 'POST', body })
    versionFile.value = null
    versionNote.value = ''
    await load()
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Impossible de déposer le document principal'
  }
  finally {
    busy.value = false
  }
}

const uploadAttachment = async () => {
  const file = asFile(attachmentFile.value)
  if (!file)
    return
  busy.value = true
  actionError.value = ''
  try {
    const body = new FormData()
    body.append('file', file)
    body.append('kind', attachmentKind.value)
    await $api(`/parapheur/documents/${route.params.id}/attachments`, { method: 'POST', body })
    attachmentFile.value = null
    await load()
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Impossible d\'ajouter la pièce jointe'
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
    const link = window.document.createElement('a')
    link.href = url
    link.download = `dossier_${dossier.value?.reference || route.params.id}.zip`
    link.click()
    URL.revokeObjectURL(url)
  }
  catch (e: any) {
    actionError.value = e?.data?.message || 'Export pack impossible'
  }
  finally {
    busy.value = false
  }
}

const versions = computed(() => dossier.value?.versions || [])
const mainVersion = computed(() => versions.value.find((v: any) => v.is_main) || versions.value[0] || null)
const streamUrl = computed(() => {
  const preview = mainVersion.value?.preview
  if (preview?.mode === 'pdf_iframe')
    return preview.url

  return (mainVersion.value?.mime_type || '').includes('pdf') ? mainVersion.value?.stream_url : null
})
const isOffice = computed(() => mainVersion.value?.preview?.mode === 'office_download')
const isOnlyOffice = computed(() => mainVersion.value?.preview?.mode === 'onlyoffice_editor')
const hasMainDocument = computed(() => versions.value.length > 0)

const editorRemountKey = ref(0)

const onOnlyOfficeSaved = async () => {
  // Soft refresh : ne pas toucher editorRemountKey (évite de demonstrer l’éditeur).
  try {
    const fresh = await $api(`/parapheur/documents/${route.params.id}`) as any
    // Mettre à jour sans remplacer les versions si l’id courant est inchangé
    if (fresh)
      dossier.value = fresh
  }
  catch {
    // ignore
  }
}

const onOnlyOfficeReload = async () => {
  await onOnlyOfficeSaved()
  editorRemountKey.value += 1
}

const circuitSteps = computed(() => {
  const steps = dossier.value?.workflow_instance?.workflow?.steps || []
  const current = dossier.value?.workflow_instance?.current_step_order

  return [...steps].sort((a: any, b: any) => a.step_order - b.step_order).map((step: any) => ({
    ...step,
    done: current ? step.step_order < current : false,
    current: current ? step.step_order === current : false,
  }))
})
</script>

<template>
  <div v-if="loading">
    <VProgressLinear indeterminate />
  </div>

  <div v-else-if="dossier">
    <VBtn
      variant="text"
      class="px-0 mb-2"
      prepend-icon="tabler-arrow-left"
      :to="{ name: 'parapheur' }"
    >
      Retour au parapheur
    </VBtn>

    <div class="d-flex flex-wrap justify-space-between align-start gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ dossier.object }}
        </h4>
        <div class="text-body-1 text-medium-emphasis">
          {{ dossier.reference }} · {{ dossier.type?.name }} · {{ dossier.structure?.code }}
        </div>
      </div>
      <div class="d-flex flex-wrap align-center gap-2">
        <VBtn
          variant="tonal"
          size="small"
          prepend-icon="tabler-folders"
          :to="{ name: 'ged-id', params: { id: String(dossier.id) } }"
        >
          Fiche GED
        </VBtn>
        <VChip
          label
          :color="statusColor(dossier.status)"
        >
          {{ labelOf(statusLabels, dossier.status) }}
        </VChip>
        <VChip
          label
          :color="priorityColor(dossier.priority)"
        >
          {{ labelOf(priorityLabels, dossier.priority) }}
        </VChip>
        <VChip
          label
          color="info"
        >
          {{ labelOf(actionLabels, dossier.expected_action) }}
        </VChip>
        <VBtn
          v-if="dossier.status === 'archive'"
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

    <VAlert
      v-if="actionError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ actionError }}
    </VAlert>

    <VAlert
      v-if="isReturnedToAuthor"
      type="warning"
      variant="tonal"
      class="mb-6"
      density="comfortable"
    >
      <div class="font-weight-medium mb-1">
        Dossier retourné — corrigez puis retransmettez
      </div>
      <div class="text-body-2">
        Ajoutez une nouvelle version ou des pièces si besoin, puis utilisez
        <strong>Transmettre</strong> pour renvoyer le dossier.
      </div>
    </VAlert>

    <VAlert
      v-else-if="isInitiatorSpectator"
      type="info"
      variant="tonal"
      class="mb-6"
      density="comfortable"
    >
      <div class="font-weight-medium mb-1">
        Dossier transmis — suivi initiateur
      </div>
      <div class="text-body-2">
        Statut :
        <strong>{{ labelOf(statusLabels, dossier.status) }}</strong>
        <template v-if="dossier.current_assignee">
          — actuellement chez
          <strong>{{ dossier.current_assignee.name }}</strong>
        </template>
        . Consultation uniquement tant que le dossier n’est pas revenu.
      </div>
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-file-text"
                size="22"
              />
              Document principal
            </VCardTitle>
            <template #append>
              <VChip
                v-if="mainVersion"
                size="small"
                label
                color="primary"
                variant="tonal"
              >
                Version {{ mainVersion.version_number }}
              </VChip>
            </template>
          </VCardItem>
          <VDivider />
          <VCardText>
            <template v-if="hasMainDocument">
              <div class="d-flex flex-wrap justify-space-between align-center gap-3 mb-4">
                <div>
                  <div class="font-weight-medium">
                    {{ mainVersion.original_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ formatBytes(mainVersion.size) }}
                    <span v-if="mainVersion.uploader?.name"> · {{ mainVersion.uploader.name }}</span>
                    <span v-if="mainVersion.change_note"> · {{ mainVersion.change_note }}</span>
                  </div>
                </div>
                <VBtn
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-download"
                  :href="mainVersion.download_url"
                  target="_blank"
                >
                  Télécharger
                </VBtn>
              </div>

              <iframe
                v-if="streamUrl"
                class="mb-4 w-100"
                style="border: 0; min-block-size: 520px;"
                :src="streamUrl"
                title="Aperçu du document principal"
              />
              <OnlyOfficeEditor
                v-else-if="isOnlyOffice && dossier?.id"
                :key="`oo-${dossier.id}-${editorRemountKey}`"
                :document-id="dossier.id"
                class="mb-4"
                @saved="onOnlyOfficeSaved"
                @reload="onOnlyOfficeReload"
                @error="(msg) => { actionError = msg }"
              />
              <VAlert
                v-else-if="isOffice"
                type="info"
                variant="tonal"
                class="mb-4"
              >
                Document Office : prévisualisation indisponible. Téléchargez le fichier pour le consulter.
              </VAlert>

              <div
                v-if="versions.length > 1"
                class="mb-2"
              >
                <div class="text-subtitle-2 mb-2">
                  Historique des versions
                </div>
                <VList
                  class="py-0"
                  density="compact"
                >
                  <VListItem
                    v-for="version in versions"
                    :key="version.id"
                    :title="`V${version.version_number} — ${version.original_name}`"
                    :subtitle="version.change_note || formatDateTime(version.created_at)"
                  >
                    <template #append>
                      <VBtn
                        size="small"
                        variant="text"
                        :href="version.download_url"
                        target="_blank"
                      >
                        Télécharger
                      </VBtn>
                    </template>
                  </VListItem>
                </VList>
              </div>
            </template>

            <VAlert
              v-else
              type="warning"
              variant="tonal"
              class="mb-4"
            >
              Aucun document principal n’est enregistré sur ce dossier. Les pièces jointes ne le remplacent pas : déposez le fichier ici.
            </VAlert>

            <div
              v-if="canMutate && !hasMainDocument"
              class="d-flex flex-column gap-3"
            >
              <VFileInput
                v-model="versionFile"
                label="Fichier principal"
                prepend-icon=""
                prepend-inner-icon="tabler-paperclip"
                show-size
                density="compact"
                clearable
              />
              <VBtn
                color="primary"
                :loading="busy"
                :disabled="!asFile(versionFile)"
                @click="uploadVersion"
              >
                Enregistrer le document principal
              </VBtn>
            </div>

            <VExpansionPanels
              v-else-if="canMutate"
              variant="accordion"
            >
              <VExpansionPanel>
                <VExpansionPanelTitle>Nouvelle version (immuable)</VExpansionPanelTitle>
                <VExpansionPanelText>
                  <div class="d-flex flex-column gap-3 pt-1">
                    <VFileInput
                      v-model="versionFile"
                      label="Fichier de remplacement"
                      prepend-icon=""
                      prepend-inner-icon="tabler-paperclip"
                      show-size
                      density="compact"
                      clearable
                    />
                    <AppTextField
                      v-model="versionNote"
                      label="Note de changement"
                      density="compact"
                    />
                    <VBtn
                      color="primary"
                      :loading="busy"
                      :disabled="!asFile(versionFile)"
                      @click="uploadVersion"
                    >
                      Déposer la version
                    </VBtn>
                  </div>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-paperclip"
                size="22"
              />
              Pièces jointes
            </VCardTitle>
            <template #append>
              <VChip
                size="small"
                label
                variant="tonal"
              >
                {{ dossier.attachments?.length || 0 }}
              </VChip>
            </template>
          </VCardItem>
          <VDivider />
          <VCardText>
            <VList
              v-if="dossier.attachments?.length"
              class="py-0 mb-4"
              density="compact"
            >
              <VListItem
                v-for="att in dossier.attachments"
                :key="att.id"
                :title="att.original_name"
                :subtitle="`${labelOf(kindLabels, att.kind)} · ${formatBytes(att.size)}`"
              >
                <template #append>
                  <VBtn
                    size="small"
                    variant="tonal"
                    :href="att.download_url"
                    target="_blank"
                  >
                    Télécharger
                  </VBtn>
                </template>
              </VListItem>
            </VList>
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
              <VRow dense>
                <VCol
                  cols="12"
                  md="4"
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
                </VCol>
                <VCol
                  cols="12"
                  md="8"
                >
                  <VFileInput
                    v-model="attachmentFile"
                    label="Ajouter un fichier"
                    prepend-icon=""
                    prepend-inner-icon="tabler-paperclip"
                    show-size
                    density="compact"
                    clearable
                  />
                </VCol>
              </VRow>
              <VBtn
                variant="tonal"
                :loading="busy"
                :disabled="!asFile(attachmentFile)"
                @click="uploadAttachment"
              >
                Ajouter
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Observations & actions</VCardTitle>
          <VDivider />
          <VCardText>
            <div
              v-if="dossier.comments?.length"
              class="mb-4"
            >
              <div
                v-for="comment in dossier.comments"
                :key="comment.id"
                class="mb-4"
              >
                <div class="d-flex flex-wrap justify-space-between gap-2">
                  <div class="font-weight-medium">
                    {{ comment.user?.name }}
                    <VChip
                      size="x-small"
                      class="ms-1"
                      label
                    >
                      {{ labelOf(kindLabels, comment.kind) }}
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ formatDateTime(comment.created_at) }}
                  </div>
                </div>
                <div class="text-body-2 mt-1">
                  {{ comment.body }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="text-medium-emphasis mb-4"
            >
              Aucune observation pour le moment.
            </div>

            <VAlert
              v-if="isInitiatorSpectator"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              Suivi initiateur : le dossier est chez
              <strong>{{ dossier.current_assignee?.name || 'un destinataire' }}</strong>
              ({{ labelOf(statusLabels, dossier.status) }}).
              Aucune action de traitement n’est disponible ici.
            </VAlert>

            <VAlert
              v-else-if="isReturnedToAuthor"
              type="warning"
              variant="tonal"
              class="mb-4"
            >
              Dossier retourné à votre charge. Corrigez le document puis retransmettez-le.
            </VAlert>

            <AppTextarea
              v-if="showActionComment"
              v-model="actionComment"
              label="Commentaire / motif d'action"
              rows="3"
              class="mb-4"
            />

            <template v-if="showActionPanel">
              <template v-if="showComments">
                <div class="text-subtitle-2 mb-2">
                  Commenter
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
                  <VBtn
                    variant="tonal"
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
                </div>
              </template>

              <template v-if="showTreatGroup">
                <div class="text-subtitle-2 mb-2">
                  Traiter
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
                  <VBtn
                    v-if="showAcknowledge"
                    color="secondary"
                    :loading="busy"
                    @click="runAction('acknowledge')"
                  >
                    Prise de connaissance
                  </VBtn>
                  <VBtn
                    v-if="showVise"
                    color="info"
                    :loading="busy"
                    @click="runAction('vise')"
                  >
                    Viser
                  </VBtn>
                  <VBtn
                    v-if="showValidate"
                    color="success"
                    :loading="busy"
                    @click="runAction('validate')"
                  >
                    Valider
                  </VBtn>
                  <VBtn
                    v-if="showReject"
                    color="error"
                    :loading="busy"
                    @click="runAction('reject')"
                  >
                    Rejeter
                  </VBtn>
                </div>
              </template>

              <template v-if="showCirculation">
                <div class="text-subtitle-2 mb-2">
                  Circulation
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
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
                    variant="tonal"
                    :loading="busy"
                    @click="runAction('hold')"
                  >
                    En attente
                  </VBtn>
                </div>
              </template>

              <template v-if="showFiling">
                <div class="text-subtitle-2 mb-2">
                  Classement
                </div>
                <div class="d-flex flex-wrap gap-2">
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
              </template>
            </template>
            <VAlert
              v-else-if="!isInitiatorSpectator"
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
          <VCardTitle>Fiche du dossier</VCardTitle>
          <VDivider />
          <VCardText>
            <div class="d-flex flex-column gap-4">
              <div>
                <div class="text-caption text-medium-emphasis">
                  Auteur
                </div>
                <div>{{ dossier.author?.name || '—' }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Destinataire actuel
                </div>
                <div>{{ dossier.current_assignee?.name || '—' }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Confidentialité
                </div>
                <div>{{ labelOf(confidentialityLabels, dossier.confidentiality) }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Date du document
                </div>
                <div>{{ formatDate(dossier.document_date) }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Échéance
                </div>
                <div>{{ formatDate(dossier.due_date) }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Mots-clés
                </div>
                <div>
                  <template v-if="dossier.keywords?.length">
                    <VChip
                      v-for="kw in dossier.keywords"
                      :key="kw"
                      size="small"
                      class="me-1 mb-1"
                      label
                    >
                      {{ kw }}
                    </VChip>
                  </template>
                  <span v-else>—</span>
                </div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Classement GED
                </div>
                <div>{{ dossier.classification_node?.path || dossier.classification_node?.name || '—' }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Tags GED
                </div>
                <div>
                  <template v-if="dossier.tags?.length">
                    <VChip
                      v-for="tag in dossier.tags"
                      :key="tag.id"
                      size="small"
                      class="me-1 mb-1"
                      label
                    >
                      #{{ tag.name }}
                    </VChip>
                  </template>
                  <span v-else>—</span>
                </div>
              </div>
              <div v-if="dossier.official_version">
                <div class="text-caption text-medium-emphasis">
                  Version officielle
                </div>
                <div>v{{ dossier.official_version.version_number }}</div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Circuit</VCardTitle>
          <VDivider />
          <VCardText>
            <div
              v-if="dossier.workflow_instance"
              class="mb-4"
            >
              <div class="text-caption mb-3">
                Mode : {{ dossier.workflow_instance.kind === 'predefini' ? 'Circuit prédéfini' : 'Libre' }}
                <span v-if="dossier.workflow_instance.workflow">
                  — {{ dossier.workflow_instance.workflow.name }}
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
                    {{ step.role_name }} · {{ labelOf(actionLabels, step.expected_action) }}
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
              v-if="dossier.transmissions?.length"
            >
              <div
                v-for="t in dossier.transmissions"
                :key="t.id"
                class="mb-3 text-body-2"
              >
                <strong>{{ t.from_user?.name }}</strong> → {{ t.to_user?.name }}
                <div class="text-caption">
                  {{ labelOf(actionLabels, t.expected_action) }}
                  · {{ labelOf(folderLabels, t.folder) }}
                  · {{ labelOf(transmissionStatusLabels, t.status) }}
                </div>
                <div
                  v-if="t.message"
                  class="text-caption"
                >
                  {{ t.message }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucune transmission
            </div>
          </VCardText>
        </VCard>

        <VCard
          v-if="canTransmit"
          class="mb-6"
        >
          <VCardTitle>Transmettre / réaffecter</VCardTitle>
          <VDivider />
          <VCardText>
            <AppSelect
              v-model="transmitForm.intent"
              :items="[
                { title: 'Transmettre', value: 'transmit' },
                { title: 'Réaffecter', value: 'reassign' },
              ]"
              label="Action"
              class="mb-3"
            />
            <AppSelect
              v-if="transmitForm.intent === 'transmit'"
              v-model="transmitForm.mode"
              :items="[
                { title: 'Libre', value: 'libre' },
                { title: 'Circuit prédéfini', value: 'predefini' },
              ]"
              label="Mode"
              class="mb-3"
            />
            <UserAutocomplete
              v-if="transmitForm.intent === 'reassign' || transmitForm.mode === 'libre'"
              v-model="transmitForm.to_user_id"
              :items="users"
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
          <VDivider />
          <VCardText>
            <VTimeline
              v-if="dossier.actions?.length"
              density="compact"
              side="end"
            >
              <VTimelineItem
                v-for="action in dossier.actions"
                :key="action.id"
                size="x-small"
              >
                <div class="font-weight-medium">
                  {{ labelOf(historyLabels, action.action_type) }}
                </div>
                <div class="text-caption">
                  {{ action.actor?.name }}
                  <span v-if="action.delegator"> (délégation de {{ action.delegator.name }})</span>
                  <span v-if="action.created_at"> · {{ formatDateTime(action.created_at) }}</span>
                </div>
                <div v-if="action.comment">
                  {{ action.comment }}
                </div>
              </VTimelineItem>
            </VTimeline>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucun historique
            </div>
          </VCardText>
        </VCard>

        <VCard v-if="canInstruct && canProcess">
          <VCardTitle>Créer une instruction</VCardTitle>
          <VDivider />
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
            <UserAutocomplete
              v-model="instructionForm.assignee_id"
              :items="users"
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
