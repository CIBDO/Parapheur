<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import type { Correspondence } from '@/composables/useCorrespondence'
import {
  formatCorrespondenceNumber,
  getCorrespondenceStatusLabel,
  getCorrespondenceStatusColor,
  getCorrespondencePriorityLabel,
  getCorrespondenceConfidentialityLabel,
  directionLabel,
  correspondenceDirectionColors,
  correspondencePriorityColors,
  correspondenceMediumLabels,
  partyRoleLabel,
  partyRoleLabels,
  partyRoleColors,
  partyRoleValue,
  partyDisplayName,
  partiesByRole,
  assignmentStatusLabel,
  assignmentStatusColors,
  detailRouteName,
} from '@/utils/courrierUi'
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import { $api } from '@/utils/api'

interface Props {
  correspondence: Correspondence
  direction?: 'entrant' | 'sortant' | 'interne'
}

const props = defineProps<Props>()
const emit = defineEmits<{
  refresh: []
}>()

const router = useRouter()
const { 
  reply, 
  assign, 
  takeCharge, 
  requestComplement, 
  syncParties, 
  createReminder, 
  listReminders, 
  attachSignedVersion, 
  printDocument, 
  createCirculationSheet, 
  generateCirculationSheetDocument,
  generateDispatchSlip,
  generateAcknowledgementDocument,
  dispatch,
  registerCorrespondence,
  updateCorrespondence,
  fetchMeta,
} = useCorrespondence()

const activeTab = ref('synthese')
const reminders = ref<any[]>([])
const users = ref<any[]>([])
const assigning = ref(false)
const structures = ref<any[]>([])
const channels = ref<any[]>([])
const categories = ref<any[]>([])
const creatingFiche = ref(false)
const ficheMessage = ref('')
const ficheError = ref('')
const outputBusy = ref(false)
const outputMessage = ref('')
const outputError = ref('')
const registeringDeparture = ref(false)
const dispatching = ref(false)
const documentDetail = ref<any>(null)
const documentPreviewLoading = ref(false)
const documentPreviewError = ref('')
const editorRemountKey = ref(0)

// Dialogs
const showAssignDialog = ref(false)
const showComplementDialog = ref(false)
const showReminderDialog = ref(false)
const showPrintDialog = ref(false)
const showDispatchDialog = ref(false)
const showSignedUploadDialog = ref(false)

// Forms
const assignForm = ref({
  to_user_id: null as number | null,
  instruction_text: '',
  due_date: '',
})
const complementForm = ref({ assignmentId: null as number | null, observation: '' })
const reminderForm = ref({ reminder_date: '', note: '' })
const printForm = ref({ reason: '', copies: 1, is_reprint: false })
const dispatchForm = ref({ method: 'courrier', date: '', tracking_number: '', observations: '' })
const signedFile = ref<File | null>(null)

// Parties management
const editingParties = ref(false)
const partiesForm = ref<any[]>([])

// Traitement
const editingTraitement = ref(false)
const savingTraitement = ref(false)
const traitementForm = ref({
  structure_id: null as number | null,
  channel_id: null as number | null,
  category_id: null as number | null,
})

const structureItems = computed(() =>
  structures.value.map(s => ({ value: s.id, title: s.name || s.code })),
)
const channelItems = computed(() =>
  channels.value.map(c => ({ value: c.id, title: c.name || c.code })),
)
const categoryItems = computed(() =>
  categories.value.map(c => ({ value: c.id, title: c.name || c.code })),
)

const myAssignments = computed(() => {
  return props.correspondence.assignments?.filter(a => a.status !== 'termine' && a.status !== 'refuse') || []
})

const isOverdue = computed(() => {
  if (!props.correspondence.due_date) return false
  return new Date(props.correspondence.due_date) < new Date()
})

const fromParty = computed(() =>
  partiesByRole(props.correspondence.parties, 'from')[0] || null,
)

const toParties = computed(() =>
  partiesByRole(props.correspondence.parties, 'to'),
)

const fallbackRecipientName = computed(() => {
  if (toParties.value.length)
    return null
  return props.correspondence.structure?.name
    || (props.correspondence.direction === 'entrant' ? 'DGTCP' : null)
})

const currentAssignee = computed(() => {
  const a = props.correspondence.assignments?.find((item: any) =>
    ['transmis', 'recu', 'pris_en_charge', 'en_traitement'].includes(item.status),
  )
  return a?.to_user?.name || a?.to_structure?.name || null
})

/** Une seule fiche métier par courrier (préférence : avec document, sinon la plus ancienne). */
const primaryCirculationSheet = computed(() => {
  const sheets = [...(props.correspondence.circulation_sheets || [])]
  if (!sheets.length)
    return null
  sheets.sort((a: any, b: any) => {
    const aDoc = a.document_id ? 0 : 1
    const bDoc = b.document_id ? 0 : 1
    if (aDoc !== bDoc)
      return aDoc - bDoc
    return (a.id || 0) - (b.id || 0)
  })
  return sheets[0]
})

const hasCirculationSheet = computed(() => !!primaryCirculationSheet.value)

const latestDispatch = computed(() => {
  const list = [...(props.correspondence.dispatches || [])]

  return list.sort((a: any, b: any) => (b.id || 0) - (a.id || 0))[0] || null
})

const latestAcknowledgement = computed(() => {
  const list = [...(props.correspondence.acknowledgements || [])]

  return list.sort((a: any, b: any) => (b.id || 0) - (a.id || 0))[0] || null
})

const isSortant = computed(() => (props.direction || props.correspondence.direction) === 'sortant')

const canRegisterDeparture = computed(() =>
  isSortant.value && !props.correspondence.departure_number
  && !['expedie', 'accuse_recu', 'classe', 'archive', 'annule'].includes(props.correspondence.status || ''),
)

const canDispatch = computed(() =>
  isSortant.value
  && !['expedie', 'accuse_recu', 'classe', 'archive', 'annule'].includes(props.correspondence.status || ''),
)

const alreadyDispatched = computed(() =>
  isSortant.value && ['expedie', 'accuse_recu'].includes(props.correspondence.status || ''),
)

const sortantStep = computed(() => {
  if (!isSortant.value)
    return 0
  if (alreadyDispatched.value || latestDispatch.value)
    return 3
  if (props.correspondence.departure_number)
    return 2

  return 1
})

const statusDisplayLabel = computed(() =>
  getCorrespondenceStatusLabel(props.correspondence.status, props.direction || props.correspondence.direction),
)

const documentVersions = computed(() => documentDetail.value?.versions || [])
const mainDocumentVersion = computed(() =>
  documentVersions.value.find((v: any) => v.is_main) || documentVersions.value[0] || null,
)
const documentStreamUrl = computed(() => {
  const preview = mainDocumentVersion.value?.preview
  if (preview?.mode === 'pdf_iframe' && preview.url)
    return preview.url
  const mime = (mainDocumentVersion.value?.mime_type || '').toLowerCase()
  const name = (mainDocumentVersion.value?.original_name || '').toLowerCase()
  if (mime.includes('pdf') || name.endsWith('.pdf') || mime.startsWith('image/'))
    return mainDocumentVersion.value?.stream_url || null
  return null
})
const isDocumentOnlyOffice = computed(() =>
  mainDocumentVersion.value?.preview?.mode === 'onlyoffice_editor',
)
const isDocumentOfficeDownload = computed(() =>
  mainDocumentVersion.value?.preview?.mode === 'office_download',
)

async function loadDocumentPreview() {
  const documentId = props.correspondence.document?.id
  if (!documentId) {
    documentDetail.value = null
    documentPreviewError.value = ''
    return
  }

  documentPreviewLoading.value = true
  documentPreviewError.value = ''
  try {
    documentDetail.value = await $api(`/parapheur/documents/${documentId}`)
  }
  catch (error: any) {
    documentDetail.value = null
    documentPreviewError.value = error?.data?.message || error?.message || 'Impossible de charger le document'
  }
  finally {
    documentPreviewLoading.value = false
  }
}

function reloadDocumentPreview() {
  editorRemountKey.value += 1
  loadDocumentPreview()
}

watch(
  () => props.correspondence.document?.id,
  () => { loadDocumentPreview() },
  { immediate: true },
)

onMounted(async () => {
  await loadReminders()
  try {
    const [userList, structs, meta] = await Promise.all([
      $api('/meta/users'),
      $api('/meta/structures'),
      fetchMeta(),
    ])
    users.value = Array.isArray(userList) ? userList : []
    structures.value = Array.isArray(structs) ? structs : []
    channels.value = Array.isArray(meta.channels) ? meta.channels : (meta.channels?.data || [])
    categories.value = Array.isArray(meta.categories) ? meta.categories : (meta.categories?.data || [])
  }
  catch {
    users.value = []
    structures.value = []
    channels.value = []
    categories.value = []
  }
})

async function loadReminders() {
  if (props.correspondence.id) {
    try {
      reminders.value = await listReminders(props.correspondence.id)
    } catch (error) {
      console.error('Erreur chargement relances:', error)
    }
  }
}

function formatDate(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR')
}

function formatDateTime(date: string | null) {
  if (!date) return '—'
  return new Date(date).toLocaleString('fr-FR')
}

async function prepareReply() {
  try {
    const created = await reply(props.correspondence.id)
    router.push({ name: 'courrier-sortants-id', params: { id: created.id } })
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de créer la réponse'))
  }
}

function openAssignDialog() {
  assignForm.value = { to_user_id: null, instruction_text: '', due_date: '' }
  showAssignDialog.value = true
}

async function handleAssign() {
  if (!assignForm.value.to_user_id)
    return

  assigning.value = true
  try {
    await assign(props.correspondence.id, [{
      to_user_id: assignForm.value.to_user_id,
      instruction_text: assignForm.value.instruction_text || undefined,
      due_date: assignForm.value.due_date || undefined,
    }])
    showAssignDialog.value = false
    assignForm.value = { to_user_id: null, instruction_text: '', due_date: '' }
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible d\'affecter'))
  } finally {
    assigning.value = false
  }
}

async function handleTakeCharge(assignmentId: number) {
  try {
    await takeCharge(props.correspondence.id, assignmentId)
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de prendre en charge'))
  }
}

async function handleRequestComplement() {
  try {
    if (!complementForm.value.assignmentId) return
    await requestComplement(props.correspondence.id, complementForm.value.assignmentId, complementForm.value.observation)
    showComplementDialog.value = false
    complementForm.value = { assignmentId: null, observation: '' }
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de demander un complément'))
  }
}

async function saveParties() {
  try {
    await syncParties(props.correspondence.id, partiesForm.value)
    editingParties.value = false
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de sauvegarder les parties'))
  }
}

function startEditParties() {
  partiesForm.value = JSON.parse(JSON.stringify(props.correspondence.parties || []))
  editingParties.value = true
}

function startEditTraitement() {
  traitementForm.value = {
    structure_id: props.correspondence.structure?.id
      ?? (props.correspondence as any).structure_id
      ?? null,
    channel_id: props.correspondence.channel?.id
      ?? (props.correspondence as any).channel_id
      ?? null,
    category_id: props.correspondence.category?.id
      ?? (props.correspondence as any).category_id
      ?? null,
  }
  editingTraitement.value = true
}

async function saveTraitement() {
  savingTraitement.value = true
  try {
    await updateCorrespondence(props.correspondence.id, {
      structure_id: traitementForm.value.structure_id,
      channel_id: traitementForm.value.channel_id,
      category_id: traitementForm.value.category_id,
    })
    editingTraitement.value = false
    emit('refresh')
  }
  catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de sauvegarder le traitement'))
  }
  finally {
    savingTraitement.value = false
  }
}

function addParty() {
  partiesForm.value.push({ role: 'to', name: '', organization: '' })
}

function removeParty(index: number) {
  partiesForm.value.splice(index, 1)
}

function linkedCorrespondence(link: any, kind: 'incoming' | 'outgoing') {
  return kind === 'incoming'
    ? (link.source_correspondence || link.sourceCorrespondence || link)
    : (link.target_correspondence || link.targetCorrespondence || link)
}

function eventLabel(type?: string | null) {
  const map: Record<string, string> = {
    correspondence_created: 'Création',
    status_changed: 'Changement de statut',
    assignment_created: 'Affectation',
    taken_charge: 'Prise en charge',
    parties_synced: 'Mise à jour des parties',
    reminder_created: 'Relance créée',
    dispatched: 'Expédition',
    reply_prepared: 'Réponse préparée',
    document_attached: 'Document attaché',
    complement_requested: 'Demande de complément',
  }
  return map[type || ''] || type || 'Événement'
}

async function handleCreateReminder() {
  try {
    await createReminder(props.correspondence.id, {
      reminder_date: reminderForm.value.reminder_date,
      note: reminderForm.value.note || undefined,
      type: 'manual',
    })
    showReminderDialog.value = false
    reminderForm.value = { reminder_date: '', note: '' }
    await loadReminders()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de créer la relance'))
  }
}

async function handlePrint() {
  try {
    await printDocument(props.correspondence.id, printForm.value)
    showPrintDialog.value = false
    printForm.value = { reason: '', copies: 1, is_reprint: false }
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible d\'imprimer'))
  }
}

async function handleCreateCirculationSheet(generate = false) {
  creatingFiche.value = true
  ficheMessage.value = ''
  ficheError.value = ''
  try {
    const result = await createCirculationSheet(props.correspondence.id, { generate })
    const sheet = result?.circulation_sheet || result
    ficheMessage.value = result?.message
      || (generate
        ? `Fiche ${sheet?.number || ''} prête (document généré).`
        : `Fiche ${sheet?.number || ''} prête.`)
    emit('refresh')
  }
  catch (error: any) {
    ficheError.value = error?.data?.error
      || error?.data?.message
      || error.message
      || 'Impossible de créer la fiche'
  }
  finally {
    creatingFiche.value = false
  }
}

async function handleGenerateExistingSheet(sheetId: number) {
  creatingFiche.value = true
  ficheError.value = ''
  ficheMessage.value = ''
  try {
    await generateCirculationSheetDocument(sheetId)
    ficheMessage.value = 'Document de fiche généré.'
    emit('refresh')
  }
  catch (error: any) {
    ficheError.value = error?.data?.error
      || error?.data?.message
      || error.message
      || 'Impossible de générer le document'
  }
  finally {
    creatingFiche.value = false
  }
}

async function handleUploadSigned() {
  if (!signedFile.value) return
  try {
    const formData = new FormData()
    formData.append('file', signedFile.value)
    await attachSignedVersion(props.correspondence.id, formData)
    showSignedUploadDialog.value = false
    signedFile.value = null
    emit('refresh')
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible d\'attacher le fichier'))
  }
}

async function handleRegisterDeparture() {
  registeringDeparture.value = true
  try {
    await registerCorrespondence(props.correspondence.id)
    emit('refresh')
  }
  catch (error: any) {
    alert(error?.data?.message || error.message || 'Impossible d\'enregistrer au départ')
  }
  finally {
    registeringDeparture.value = false
  }
}

async function handleDispatch() {
  if (!dispatchForm.value.method) {
    alert('Choisissez un mode d\'expédition')

    return
  }
  dispatching.value = true
  try {
    await dispatch(props.correspondence.id, {
      method: dispatchForm.value.method,
      dispatched_at: dispatchForm.value.date || undefined,
      tracking_number: dispatchForm.value.tracking_number || undefined,
      observations: dispatchForm.value.observations,
      generate: true,
    })
    showDispatchDialog.value = false
    dispatchForm.value = { method: 'courrier', date: '', tracking_number: '', observations: '' }
    emit('refresh')
  }
  catch (error: any) {
    alert(error?.data?.message || error.message || 'Impossible d\'expédier')
  }
  finally {
    dispatching.value = false
  }
}

async function handleGenerateDispatchSlip() {
  if (!latestDispatch.value?.id)
    return
  outputBusy.value = true
  outputError.value = ''
  outputMessage.value = ''
  try {
    await generateDispatchSlip(props.correspondence.id, latestDispatch.value.id)
    outputMessage.value = 'Bordereau d\'envoi généré.'
    emit('refresh')
  }
  catch (error: any) {
    outputError.value = error?.data?.error || error?.data?.message || error.message || 'Échec génération bordereau'
  }
  finally {
    outputBusy.value = false
  }
}

async function handleGenerateAcknowledgementDoc() {
  if (!latestAcknowledgement.value?.id)
    return
  outputBusy.value = true
  outputError.value = ''
  outputMessage.value = ''
  try {
    await generateAcknowledgementDocument(props.correspondence.id, latestAcknowledgement.value.id)
    outputMessage.value = 'Accusé de réception généré.'
    emit('refresh')
  }
  catch (error: any) {
    outputError.value = error?.data?.error || error?.data?.message || error.message || 'Échec génération accusé'
  }
  finally {
    outputBusy.value = false
  }
}

function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    signedFile.value = target.files[0]
  }
}

async function archiveCorrespondence() {
  if (!confirm('Archiver ce courrier ?')) return
  // TODO: call archive API
  emit('refresh')
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="formatCorrespondenceNumber({ ...correspondence, direction: direction || correspondence.direction })"
      :subtitle="correspondence.subject"
    >
      <template #actions>
        <VBtn
          v-if="direction === 'entrant'"
          variant="tonal"
          prepend-icon="tabler-mail-forward"
          @click="prepareReply"
        >
          Préparer réponse
        </VBtn>
        <VBtn
          v-if="direction === 'entrant'"
          variant="tonal"
          prepend-icon="tabler-user-plus"
          @click="openAssignDialog"
        >
          Affecter
        </VBtn>
        <VBtn
          v-if="canRegisterDeparture"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-hash"
          :loading="registeringDeparture"
          @click="handleRegisterDeparture"
        >
          Enregistrer au départ
        </VBtn>
        <VBtn
          v-if="canDispatch"
          color="primary"
          prepend-icon="tabler-send"
          @click="showDispatchDialog = true"
        >
          {{ correspondence.departure_number ? 'Expédier' : 'Enregistrer & expédier' }}
        </VBtn>
        <VChip
          v-else-if="alreadyDispatched"
          color="success"
          variant="tonal"
          prepend-icon="tabler-circle-check"
        >
          Déjà expédié
        </VChip>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-archive"
          @click="archiveCorrespondence"
        >
          Archiver
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VTabs v-model="activeTab">
        <VTab value="synthese">
          Synthèse
        </VTab>
        <VTab value="parties">
          Parties
        </VTab>
        <VTab value="document">
          Document
        </VTab>
        <VTab value="affectations">
          Affectations
        </VTab>
        <VTab value="relances">
          Relances
        </VTab>
        <VTab value="fiche">
          États de sortie
        </VTab>
        <VTab value="historique">
          Historique
        </VTab>
        <VTab value="lies">
          Chaîne / Liés
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- Synthèse -->
        <VWindowItem value="synthese">
          <VCardText>
            <div class="d-flex flex-wrap ga-2 mb-5">
              <VChip
                size="small"
                :color="correspondenceDirectionColors[correspondence.direction] || 'secondary'"
                variant="tonal"
                prepend-icon="tabler-arrows-exchange"
              >
                {{ directionLabel(correspondence.direction) }}
              </VChip>
              <VChip
                size="small"
                :color="getCorrespondenceStatusColor(correspondence.status)"
                variant="tonal"
              >
                {{ statusDisplayLabel }}
              </VChip>
              <VChip
                size="small"
                :color="correspondencePriorityColors[correspondence.priority || ''] || 'secondary'"
                variant="tonal"
                prepend-icon="tabler-flag"
              >
                {{ getCorrespondencePriorityLabel(correspondence.priority) }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-lock"
              >
                {{ getCorrespondenceConfidentialityLabel(correspondence.confidentiality) }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-mail"
              >
                {{ correspondenceMediumLabels[correspondence.medium] || correspondence.medium }}
              </VChip>
              <VChip
                v-if="correspondence.due_date"
                size="small"
                :color="isOverdue ? 'error' : 'success'"
                variant="tonal"
                prepend-icon="tabler-calendar-due"
              >
                Échéance {{ formatDate(correspondence.due_date) }}
              </VChip>
            </div>

            <VAlert
              v-if="isSortant"
              type="info"
              variant="tonal"
              class="mb-5"
            >
              <div class="font-weight-medium mb-2">
                Parcours du courrier sortant
              </div>
              <div class="d-flex flex-wrap ga-2 text-body-2">
                <VChip
                  size="small"
                  :color="sortantStep >= 1 ? 'primary' : 'default'"
                  :variant="sortantStep === 1 ? 'flat' : 'tonal'"
                >
                  1. Projet
                </VChip>
                <VIcon
                  icon="tabler-chevron-right"
                  size="18"
                />
                <VChip
                  size="small"
                  :color="sortantStep >= 2 ? 'primary' : 'default'"
                  :variant="sortantStep === 2 ? 'flat' : 'tonal'"
                >
                  2. N° départ {{ correspondence.departure_number || '(à attribuer)' }}
                </VChip>
                <VIcon
                  icon="tabler-chevron-right"
                  size="18"
                />
                <VChip
                  size="small"
                  :color="sortantStep >= 3 ? 'success' : 'default'"
                  :variant="sortantStep === 3 ? 'flat' : 'tonal'"
                >
                  3. Expédition
                </VChip>
              </div>
              <div class="text-caption mt-2">
                <template v-if="canRegisterDeparture">
                  Ce projet n’a pas encore de N° DEP. Utilisez « Enregistrer au départ » ou « Enregistrer &amp; expédier ».
                </template>
                <template v-else-if="canDispatch">
                  Numéro de départ attribué. Vous pouvez enregistrer l’expédition physique ou électronique.
                </template>
                <template v-else>
                  Courrier expédié
                  <span v-if="latestDispatch?.number"> — bordereau {{ latestDispatch.number }}</span>.
                </template>
              </div>
            </VAlert>

            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <div class="parapheur-form-section mb-4">
                  <div class="parapheur-form-section__title">
                    <VIcon
                      icon="tabler-info-circle"
                      size="20"
                    />
                    Identification
                  </div>
                  <div class="courrier-meta-grid">
                    <template v-if="!isSortant">
                      <div class="courrier-meta-grid__label">
                        N° arrivée
                      </div>
                      <div class="courrier-meta-grid__value font-weight-medium">
                        {{ correspondence.arrival_number || '—' }}
                      </div>
                    </template>
                    <div class="courrier-meta-grid__label">
                      {{ isSortant ? 'N° départ (DEP)' : 'N° départ' }}
                    </div>
                    <div class="courrier-meta-grid__value font-weight-medium">
                      {{ correspondence.departure_number || (isSortant ? 'Non attribué (projet)' : '—') }}
                    </div>
                    <div class="courrier-meta-grid__label">
                      Réf. externe
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.external_reference || '—' }}
                    </div>
                    <div class="courrier-meta-grid__label">
                      Date du courrier
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ formatDate(correspondence.correspondence_date) }}
                    </div>
                    <template v-if="!isSortant">
                      <div class="courrier-meta-grid__label">
                        Réception
                      </div>
                      <div class="courrier-meta-grid__value">
                        {{ formatDateTime(correspondence.received_at) }}
                      </div>
                    </template>
                    <div class="courrier-meta-grid__label">
                      {{ isSortant ? 'Enregistrement au départ' : 'Enregistrement' }}
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ formatDateTime(correspondence.registered_at) }}
                    </div>
                    <template v-if="isSortant && latestDispatch">
                      <div class="courrier-meta-grid__label">
                        Expédié le
                      </div>
                      <div class="courrier-meta-grid__value">
                        {{ formatDateTime(latestDispatch.dispatched_at) }}
                        <span v-if="latestDispatch.method">
                          · {{ latestDispatch.method }}
                        </span>
                      </div>
                    </template>
                    <div class="courrier-meta-grid__label">
                      Pièces
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.piece_count ?? 0 }}
                    </div>
                    <div class="courrier-meta-grid__label">
                      Réponse attendue
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.requires_reply ? 'Oui' : 'Non' }}
                    </div>
                  </div>
                </div>

                <div class="parapheur-form-section mb-0">
                  <div class="parapheur-form-section__title">
                    <VIcon
                      icon="tabler-file-text"
                      size="20"
                    />
                    Contenu
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-1">
                      Objet
                    </div>
                    <div>{{ correspondence.subject || '—' }}</div>
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-1">
                      Résumé
                    </div>
                    <div class="text-body-1 text-medium-emphasis courrier-prose">
                      {{ correspondence.summary || 'Aucun résumé renseigné.' }}
                    </div>
                  </div>
                  <div>
                    <div class="text-body-2 font-weight-medium mb-1">
                      Observations
                    </div>
                    <div class="text-body-1 text-medium-emphasis courrier-prose">
                      {{ correspondence.observations || '—' }}
                    </div>
                  </div>
                </div>
              </VCol>

              <VCol
                cols="12"
                md="5"
              >
                <div class="parapheur-form-section mb-4">
                  <div class="parapheur-form-section__title">
                    <VIcon
                      icon="tabler-users"
                      size="20"
                    />
                    Interlocuteurs
                  </div>
                  <div class="mb-3">
                    <div class="text-body-2 font-weight-medium mb-1">
                      {{ isSortant ? 'Émetteur (nous)' : 'Expéditeur' }}
                    </div>
                    <div v-if="fromParty">
                      {{ partyDisplayName(fromParty) || '—' }}
                      <div
                        v-if="fromParty.organization || fromParty.correspondent?.organization"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ fromParty.organization || fromParty.correspondent?.organization }}
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-medium-emphasis"
                    >
                      Non renseigné
                    </div>
                  </div>
                  <div>
                    <div class="text-body-2 font-weight-medium mb-1">
                      {{ isSortant ? 'Destinataire externe' : 'Destinataire(s)' }}
                    </div>
                    <div v-if="toParties.length">
                      <div
                        v-for="(party, idx) in toParties"
                        :key="party.id || idx"
                        class="mb-1"
                      >
                        {{ partyDisplayName(party) || '—' }}
                      </div>
                    </div>
                    <div
                      v-else-if="fallbackRecipientName"
                    >
                      {{ fallbackRecipientName }}
                    </div>
                    <div
                      v-else
                      class="text-medium-emphasis"
                    >
                      Non renseigné
                    </div>
                  </div>
                </div>

                <div class="parapheur-form-section mb-0">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <div class="parapheur-form-section__title mb-0">
                      <VIcon
                        icon="tabler-git-fork"
                        size="20"
                      />
                      {{ isSortant ? 'Préparation / envoi' : 'Traitement' }}
                    </div>
                    <div class="d-flex ga-2">
                      <template v-if="!editingTraitement">
                        <VBtn
                          size="small"
                          variant="tonal"
                          prepend-icon="tabler-edit"
                          @click="startEditTraitement"
                        >
                          Modifier
                        </VBtn>
                      </template>
                      <template v-else>
                        <VBtn
                          size="small"
                          variant="text"
                          @click="editingTraitement = false"
                        >
                          Annuler
                        </VBtn>
                        <VBtn
                          size="small"
                          color="primary"
                          variant="tonal"
                          :loading="savingTraitement"
                          @click="saveTraitement"
                        >
                          Enregistrer
                        </VBtn>
                      </template>
                    </div>
                  </div>

                  <div
                    v-if="editingTraitement"
                    class="d-flex flex-column ga-3"
                  >
                    <AppSelect
                      v-model="traitementForm.structure_id"
                      :items="structureItems"
                      :label="isSortant ? 'Structure émettrice' : 'Structure'"
                      clearable
                    />
                    <AppSelect
                      v-model="traitementForm.channel_id"
                      :items="channelItems"
                      :label="isSortant ? 'Canal d\'envoi' : 'Canal'"
                      clearable
                    />
                    <AppSelect
                      v-model="traitementForm.category_id"
                      :items="categoryItems"
                      label="Catégorie"
                      clearable
                    />
                  </div>

                  <div
                    v-else
                    class="courrier-meta-grid"
                  >
                    <div class="courrier-meta-grid__label">
                      {{ isSortant ? 'Structure émettrice' : 'Structure' }}
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.structure?.name || '—' }}
                    </div>
                    <div class="courrier-meta-grid__label">
                      {{ isSortant ? 'Canal d\'envoi' : 'Canal' }}
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.channel?.name || '—' }}
                    </div>
                    <div class="courrier-meta-grid__label">
                      Catégorie
                    </div>
                    <div class="courrier-meta-grid__value">
                      {{ correspondence.category?.name || '—' }}
                    </div>
                    <template v-if="isSortant">
                      <div class="courrier-meta-grid__label">
                        N° départ
                      </div>
                      <div class="courrier-meta-grid__value">
                        {{ correspondence.departure_number || 'Non attribué' }}
                      </div>
                      <div class="courrier-meta-grid__label">
                        Expédition
                      </div>
                      <div class="courrier-meta-grid__value">
                        <template v-if="latestDispatch">
                          {{ latestDispatch.method || '—' }}
                          <span v-if="latestDispatch.dispatched_at">
                            · {{ formatDateTime(latestDispatch.dispatched_at) }}
                          </span>
                          <span v-if="latestDispatch.tracking_number">
                            · Suivi {{ latestDispatch.tracking_number }}
                          </span>
                        </template>
                        <template v-else>
                          Pas encore expédié
                        </template>
                      </div>
                      <div class="courrier-meta-grid__label">
                        Rédacteur
                      </div>
                      <div class="courrier-meta-grid__value">
                        {{ correspondence.owner_user?.name || correspondence.registered_by?.name || '—' }}
                      </div>
                    </template>
                    <template v-else>
                      <div class="courrier-meta-grid__label">
                        Affecté à
                      </div>
                      <div class="courrier-meta-grid__value">
                        {{ currentAssignee || 'Non affecté' }}
                      </div>
                    </template>
                    <div class="courrier-meta-grid__label">
                      {{ isSortant ? 'Document (projet / signé)' : 'Document GED' }}
                    </div>
                    <div class="courrier-meta-grid__value">
                      <RouterLink
                        v-if="correspondence.document?.id"
                        :to="{ name: 'ged-id', params: { id: correspondence.document.id } }"
                      >
                        #{{ correspondence.document.id }}
                      </RouterLink>
                      <span v-else>{{ isSortant ? 'Aucun document joint' : 'Aucun' }}</span>
                    </div>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VWindowItem>

        <!-- Parties -->
        <VWindowItem value="parties">
          <VCardText>
            <div class="parapheur-form-section mb-0">
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="parapheur-form-section__title mb-0">
                  <VIcon
                    icon="tabler-users"
                    size="20"
                  />
                  Parties prenantes
                </div>
                <div class="d-flex ga-2">
                  <template v-if="!editingParties">
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-edit"
                      @click="startEditParties"
                    >
                      Modifier
                    </VBtn>
                  </template>
                  <template v-else>
                    <VBtn
                      size="small"
                      variant="text"
                      @click="editingParties = false"
                    >
                      Annuler
                    </VBtn>
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      @click="saveParties"
                    >
                      Enregistrer
                    </VBtn>
                  </template>
                </div>
              </div>

              <div
                v-if="!editingParties && correspondence.parties?.length"
                class="d-flex flex-column ga-3"
              >
                <div
                  v-for="(party, index) in correspondence.parties"
                  :key="party.id || index"
                  class="courrier-party-card"
                >
                  <div class="d-flex align-start justify-space-between ga-3">
                    <div>
                      <div class="font-weight-medium">
                        {{ partyDisplayName(party) || '—' }}
                      </div>
                      <div
                        v-if="party.organization || party.correspondent?.organization || party.function"
                        class="text-caption text-medium-emphasis mt-1"
                      >
                        {{ [party.function, party.organization || party.correspondent?.organization].filter(Boolean).join(' · ') }}
                      </div>
                    </div>
                    <VChip
                      size="small"
                      :color="partyRoleColors[partyRoleValue(party)] || 'secondary'"
                      variant="tonal"
                    >
                      {{ partyRoleLabel(partyRoleValue(party)) }}
                    </VChip>
                  </div>
                </div>
              </div>

              <div
                v-else-if="!editingParties"
                class="parapheur-empty py-8"
              >
                Aucune partie enregistrée
              </div>

              <div v-else>
                <div
                  v-for="(party, index) in partiesForm"
                  :key="index"
                  class="courrier-party-card mb-3"
                >
                  <VRow dense>
                    <VCol
                      cols="12"
                      md="3"
                    >
                      <AppSelect
                        v-model="party.role"
                        :items="Object.entries(partyRoleLabels).map(([value, title]) => ({ value, title }))"
                        label="Rôle"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="party.name"
                        label="Nom"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="party.organization"
                        label="Organisation"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="1"
                      class="d-flex align-center"
                    >
                      <VBtn
                        icon="tabler-trash"
                        variant="text"
                        color="error"
                        @click="removeParty(index)"
                      />
                    </VCol>
                  </VRow>
                </div>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  @click="addParty"
                >
                  Ajouter une partie
                </VBtn>
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- Document -->
        <VWindowItem value="document">
          <VCardText>
            <div
              v-if="correspondence.document?.id"
              class="parapheur-form-section mb-4"
            >
              <div class="d-flex flex-wrap justify-space-between align-center ga-3 mb-4">
                <div class="parapheur-form-section__title mb-0">
                  <VIcon
                    icon="tabler-file"
                    size="20"
                  />
                  Document GED #{{ correspondence.document.id }}
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-printer"
                    @click="showPrintDialog = true"
                  >
                    Imprimer
                  </VBtn>
                  <VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-file-upload"
                    @click="showSignedUploadDialog = true"
                  >
                    Version signée
                  </VBtn>
                </div>
              </div>
              <div class="courrier-meta-grid mb-4">
                <div class="courrier-meta-grid__label">
                  Objet
                </div>
                <div class="courrier-meta-grid__value">
                  {{ correspondence.document.object || correspondence.document.title || '—' }}
                </div>
                <div class="courrier-meta-grid__label">
                  Référence
                </div>
                <div class="courrier-meta-grid__value">
                  {{ correspondence.document.reference || correspondence.document.dossier_number || '—' }}
                </div>
                <div
                  v-if="mainDocumentVersion"
                  class="courrier-meta-grid__label"
                >
                  Fichier
                </div>
                <div
                  v-if="mainDocumentVersion"
                  class="courrier-meta-grid__value"
                >
                  {{ mainDocumentVersion.original_name || '—' }}
                  <span
                    v-if="mainDocumentVersion.mime_type"
                    class="text-caption text-medium-emphasis"
                  >
                    · {{ mainDocumentVersion.mime_type }}
                  </span>
                </div>
              </div>

              <VProgressLinear
                v-if="documentPreviewLoading"
                indeterminate
                class="mb-4"
              />
              <VAlert
                v-if="documentPreviewError"
                type="warning"
                variant="tonal"
                class="mb-4"
              >
                {{ documentPreviewError }}
                <template #append>
                  <VBtn
                    size="small"
                    variant="text"
                    @click="reloadDocumentPreview"
                  >
                    Réessayer
                  </VBtn>
                </template>
              </VAlert>

              <div
                v-if="mainDocumentVersion"
                class="d-flex flex-wrap justify-space-between align-center ga-3 mb-3"
              >
                <div class="text-body-2 text-medium-emphasis">
                  Aperçu
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <VBtn
                    v-if="mainDocumentVersion.download_url"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-download"
                    :href="mainDocumentVersion.download_url"
                    target="_blank"
                  >
                    Télécharger
                  </VBtn>
                  <VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-refresh"
                    :loading="documentPreviewLoading"
                    @click="reloadDocumentPreview"
                  >
                    Recharger
                  </VBtn>
                </div>
              </div>

              <iframe
                v-if="documentStreamUrl"
                class="w-100 border rounded"
                style="border: 0; min-block-size: 640px; background: #f5f5f5;"
                :src="documentStreamUrl"
                title="Aperçu du document"
              />
              <OnlyOfficeEditor
                v-else-if="isDocumentOnlyOffice && correspondence.document?.id"
                :key="`oo-mail-${correspondence.document.id}-${editorRemountKey}`"
                :document-id="correspondence.document.id"
                @reload="reloadDocumentPreview"
              />
              <VAlert
                v-else-if="isDocumentOfficeDownload"
                type="info"
                variant="tonal"
              >
                Document Office : prévisualisation navigateur indisponible.
                Téléchargez le fichier pour le consulter, ou activez ONLYOFFICE pour l’édition en ligne.
              </VAlert>
              <VAlert
                v-else-if="mainDocumentVersion && !documentPreviewLoading"
                type="info"
                variant="tonal"
              >
                Aperçu iframe indisponible pour ce format. Utilisez le téléchargement.
              </VAlert>
            </div>
            <div
              v-else
              class="parapheur-form-section parapheur-empty mb-0"
            >
              <VIcon
                icon="tabler-file-off"
                size="40"
                class="mb-3"
              />
              <div class="mb-1 font-weight-medium">
                Aucun document attaché
              </div>
              <div class="text-caption">
                Joignez un scan à l’enregistrement ou depuis la GED.
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- Affectations -->
        <VWindowItem value="affectations">
          <VCardText>
            <div class="parapheur-form-section mb-0">
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="parapheur-form-section__title mb-0">
                  <VIcon
                    icon="tabler-user-share"
                    size="20"
                  />
                  Affectations
                </div>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-user-plus"
                  @click="openAssignDialog"
                >
                  Nouvelle affectation
                </VBtn>
              </div>

              <div
                v-if="correspondence.assignments?.length"
                class="d-flex flex-column ga-3"
              >
                <div
                  v-for="a in correspondence.assignments"
                  :key="a.id"
                  class="courrier-party-card"
                >
                  <div class="d-flex flex-wrap justify-space-between align-start ga-3">
                    <div>
                      <div class="font-weight-medium">
                        {{ a.to_user?.name || a.to_structure?.name || '—' }}
                      </div>
                      <div class="text-caption text-medium-emphasis mt-1">
                        {{ a.instruction_text || 'Sans instruction' }}
                      </div>
                      <div
                        v-if="a.due_date"
                        class="text-caption mt-1"
                      >
                        Échéance : {{ formatDate(a.due_date) }}
                      </div>
                    </div>
                    <div class="d-flex flex-column align-end ga-2">
                      <VChip
                        size="small"
                        :color="assignmentStatusColors[a.status] || 'secondary'"
                        variant="tonal"
                      >
                        {{ assignmentStatusLabel(a.status) }}
                      </VChip>
                      <div class="d-flex ga-2">
                        <VBtn
                          v-if="['transmis', 'recu'].includes(a.status)"
                          size="small"
                          variant="tonal"
                          color="primary"
                          @click="handleTakeCharge(a.id)"
                        >
                          Prendre en charge
                        </VBtn>
                        <VBtn
                          v-if="['pris_en_charge', 'consulte'].includes(a.status)"
                          size="small"
                          variant="tonal"
                          @click="complementForm.assignmentId = a.id; showComplementDialog = true"
                        >
                          Demander complément
                        </VBtn>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="parapheur-empty py-8"
              >
                Aucune affectation
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- Relances -->
        <VWindowItem value="relances">
          <VCardText>
            <div class="parapheur-form-section mb-0">
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="parapheur-form-section__title mb-0">
                  <VIcon
                    icon="tabler-bell"
                    size="20"
                  />
                  Relances
                </div>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-bell-plus"
                  @click="showReminderDialog = true"
                >
                  Créer relance
                </VBtn>
              </div>

              <div
                v-if="reminders.length"
                class="d-flex flex-column ga-3"
              >
                <div
                  v-for="reminder in reminders"
                  :key="reminder.id"
                  class="courrier-party-card"
                >
                  <div class="d-flex justify-space-between align-start ga-3">
                    <div>
                      <div class="font-weight-medium">
                        {{ formatDate(reminder.reminder_date) }}
                      </div>
                      <div class="text-body-2 text-medium-emphasis mt-1">
                        {{ reminder.note || 'Sans note' }}
                      </div>
                      <div class="text-caption mt-1">
                        Destinataire : {{ reminder.user?.name || '—' }}
                      </div>
                    </div>
                    <VChip
                      size="small"
                      :color="reminder.is_sent ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ reminder.is_sent ? 'Envoyée' : 'Planifiée' }}
                    </VChip>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="parapheur-empty py-8"
              >
                Aucune relance programmée
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- États de sortie -->
        <VWindowItem value="fiche">
          <VCardText>
            <div class="parapheur-form-section mb-6">
              <div class="parapheur-form-section__title">
                <VIcon
                  icon="tabler-files"
                  size="20"
                />
                États de sortie
              </div>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Documents administratifs DGTCP : fiche de circulation, bordereau d’envoi, accusé de réception.
                Le bordereau de transmission interne reste disponible dans
                <RouterLink :to="{ name: 'courrier-bordereaux' }">
                  Bordereaux
                </RouterLink>.
              </p>

              <VAlert
                v-if="ficheMessage || outputMessage"
                type="success"
                variant="tonal"
                class="mb-4"
                closable
                @click:close="ficheMessage = ''; outputMessage = ''"
              >
                {{ ficheMessage || outputMessage }}
              </VAlert>
              <VAlert
                v-if="ficheError || outputError"
                type="error"
                variant="tonal"
                class="mb-4"
                closable
                @click:close="ficheError = ''; outputError = ''"
              >
                {{ ficheError || outputError }}
              </VAlert>
            </div>

            <!-- Fiche de circulation -->
            <div class="parapheur-form-section mb-6">
              <div class="parapheur-form-section__title mb-2">
                <VIcon
                  icon="tabler-file-invoice"
                  size="20"
                />
                Fiche de circulation (FC)
              </div>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Une seule fiche numérotée par courrier (imputation et annotations).
              </p>

              <div
                v-if="primaryCirculationSheet"
                class="courrier-party-card mb-4"
              >
                <div class="d-flex flex-wrap justify-space-between align-center ga-3">
                  <div>
                    <div class="font-weight-medium">
                      {{ primaryCirculationSheet.number || `Fiche #${primaryCirculationSheet.id}` }}
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1">
                      {{ primaryCirculationSheet.created_at ? new Date(primaryCirculationSheet.created_at).toLocaleString('fr-FR') : '' }}
                      <span v-if="primaryCirculationSheet.created_by?.name"> · {{ primaryCirculationSheet.created_by.name }}</span>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap align-center ga-2">
                    <VChip
                      size="small"
                      :color="primaryCirculationSheet.document_id ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ primaryCirculationSheet.document_id ? `Document #${primaryCirculationSheet.document_id}` : 'Sans document' }}
                    </VChip>
                    <VBtn
                      v-if="primaryCirculationSheet.document_id"
                      size="small"
                      variant="tonal"
                      :to="{ name: 'ged-id', params: { id: primaryCirculationSheet.document_id } }"
                    >
                      Ouvrir
                    </VBtn>
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :loading="creatingFiche"
                      @click="handleGenerateExistingSheet(primaryCirculationSheet.id)"
                    >
                      {{ primaryCirculationSheet.document_id ? 'Régénérer' : 'Générer' }}
                    </VBtn>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="parapheur-empty py-6 mb-4"
              >
                Aucune fiche pour ce courrier
              </div>

              <div class="d-flex flex-wrap ga-2">
                <VBtn
                  v-if="!hasCirculationSheet"
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-file-export"
                  :loading="creatingFiche"
                  @click="handleCreateCirculationSheet(true)"
                >
                  Créer et générer la fiche
                </VBtn>
                <VBtn
                  v-if="!hasCirculationSheet"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  :loading="creatingFiche"
                  @click="handleCreateCirculationSheet(false)"
                >
                  Créer la fiche seule
                </VBtn>
              </div>
            </div>

            <!-- Bordereau d'envoi -->
            <div class="parapheur-form-section mb-6">
              <div class="parapheur-form-section__title mb-2">
                <VIcon
                  icon="tabler-send"
                  size="20"
                />
                Bordereau d’envoi (BE)
              </div>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Preuve d’expédition externe. Généré automatiquement à l’expédition, ou régénérable ici.
              </p>

              <div
                v-if="latestDispatch"
                class="courrier-party-card mb-4"
              >
                <div class="d-flex flex-wrap justify-space-between align-center ga-3">
                  <div>
                    <div class="font-weight-medium">
                      {{ latestDispatch.number || `Expédition #${latestDispatch.id}` }}
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1">
                      {{ latestDispatch.method || '—' }}
                      <span v-if="latestDispatch.tracking_number"> · Suivi {{ latestDispatch.tracking_number }}</span>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap align-center ga-2">
                    <VChip
                      size="small"
                      :color="latestDispatch.document_id ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ latestDispatch.document_id ? `Document #${latestDispatch.document_id}` : 'Sans document' }}
                    </VChip>
                    <VBtn
                      v-if="latestDispatch.document_id"
                      size="small"
                      variant="tonal"
                      :to="{ name: 'ged-id', params: { id: latestDispatch.document_id } }"
                    >
                      Ouvrir
                    </VBtn>
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :loading="outputBusy"
                      @click="handleGenerateDispatchSlip"
                    >
                      {{ latestDispatch.document_id ? 'Régénérer' : 'Générer' }}
                    </VBtn>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="parapheur-empty py-6"
              >
                Aucune expédition enregistrée — utilisez l’action « Expédier ».
              </div>
            </div>

            <!-- Accusé de réception -->
            <div class="parapheur-form-section mb-0">
              <div class="parapheur-form-section__title mb-2">
                <VIcon
                  icon="tabler-mail-check"
                  size="20"
                />
                Accusé de réception (AR)
              </div>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Formulaire d’accusé lié à l’enregistrement de réception.
              </p>

              <div
                v-if="latestAcknowledgement"
                class="courrier-party-card mb-4"
              >
                <div class="d-flex flex-wrap justify-space-between align-center ga-3">
                  <div>
                    <div class="font-weight-medium">
                      {{ latestAcknowledgement.number || `AR #${latestAcknowledgement.id}` }}
                    </div>
                    <div class="text-caption text-medium-emphasis mt-1">
                      {{ latestAcknowledgement.acknowledged_by_name || latestAcknowledgement.registered_by?.name || '—' }}
                    </div>
                  </div>
                  <div class="d-flex flex-wrap align-center ga-2">
                    <VChip
                      size="small"
                      :color="latestAcknowledgement.document_id ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ latestAcknowledgement.document_id ? `Document #${latestAcknowledgement.document_id}` : 'Sans document' }}
                    </VChip>
                    <VBtn
                      v-if="latestAcknowledgement.document_id"
                      size="small"
                      variant="tonal"
                      :to="{ name: 'ged-id', params: { id: latestAcknowledgement.document_id } }"
                    >
                      Ouvrir
                    </VBtn>
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :loading="outputBusy"
                      @click="handleGenerateAcknowledgementDoc"
                    >
                      {{ latestAcknowledgement.document_id ? 'Régénérer' : 'Générer' }}
                    </VBtn>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="parapheur-empty py-6"
              >
                Aucun accusé enregistré pour ce courrier.
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- Historique -->
        <VWindowItem value="historique">
          <VCardText>
            <div class="parapheur-form-section mb-0">
              <div class="parapheur-form-section__title">
                <VIcon
                  icon="tabler-history"
                  size="20"
                />
                Historique des événements
              </div>

              <VTimeline
                v-if="correspondence.events?.length"
                side="end"
                density="compact"
                truncate-line="both"
              >
                <VTimelineItem
                  v-for="e in correspondence.events"
                  :key="e.id"
                  size="small"
                  dot-color="primary"
                >
                  <div class="courrier-party-card">
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ formatDateTime(e.created_at) }}
                      ·
                      {{ e.user?.name || 'Système' }}
                    </div>
                    <div class="font-weight-medium">
                      {{ eventLabel(e.event_type) }}
                    </div>
                    <div
                      v-if="e.old_status || e.new_status"
                      class="text-caption mt-1"
                    >
                      <span v-if="e.old_status">{{ getCorrespondenceStatusLabel(e.old_status) }}</span>
                      <span v-if="e.old_status && e.new_status"> → </span>
                      <span v-if="e.new_status">{{ getCorrespondenceStatusLabel(e.new_status) }}</span>
                    </div>
                    <div
                      v-if="e.comment"
                      class="text-body-2 text-medium-emphasis mt-2"
                    >
                      {{ e.comment }}
                    </div>
                  </div>
                </VTimelineItem>
              </VTimeline>
              <div
                v-else
                class="parapheur-empty py-8"
              >
                Aucun événement
              </div>
            </div>
          </VCardText>
        </VWindowItem>

        <!-- Chaîne / Liés -->
        <VWindowItem value="lies">
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="parapheur-form-section mb-0 h-100">
                  <div class="parapheur-form-section__title">
                    <VIcon
                      icon="tabler-mail-down"
                      size="20"
                    />
                    Entrants / sources liés
                  </div>
                  <div
                    v-if="correspondence.links?.incoming?.length"
                    class="d-flex flex-column ga-3"
                  >
                    <RouterLink
                      v-for="link in correspondence.links.incoming"
                      :key="link.id"
                      class="courrier-party-card text-decoration-none"
                      :to="{ name: detailRouteName(linkedCorrespondence(link, 'incoming')?.direction), params: { id: linkedCorrespondence(link, 'incoming')?.id } }"
                    >
                      <div class="font-weight-medium">
                        {{ formatCorrespondenceNumber(linkedCorrespondence(link, 'incoming')) }}
                      </div>
                      <div class="text-caption text-medium-emphasis mt-1">
                        {{ linkedCorrespondence(link, 'incoming')?.subject || link.link_type || '—' }}
                      </div>
                    </RouterLink>
                  </div>
                  <div
                    v-else
                    class="parapheur-empty py-6"
                  >
                    Aucun lien entrant
                  </div>
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="parapheur-form-section mb-0 h-100">
                  <div class="parapheur-form-section__title">
                    <VIcon
                      icon="tabler-mail-forward"
                      size="20"
                    />
                    Réponses / sortants liés
                  </div>
                  <div
                    v-if="correspondence.links?.outgoing?.length"
                    class="d-flex flex-column ga-3"
                  >
                    <RouterLink
                      v-for="link in correspondence.links.outgoing"
                      :key="link.id"
                      class="courrier-party-card text-decoration-none"
                      :to="{ name: detailRouteName(linkedCorrespondence(link, 'outgoing')?.direction), params: { id: linkedCorrespondence(link, 'outgoing')?.id } }"
                    >
                      <div class="font-weight-medium">
                        {{ formatCorrespondenceNumber(linkedCorrespondence(link, 'outgoing')) }}
                      </div>
                      <div class="text-caption text-medium-emphasis mt-1">
                        {{ linkedCorrespondence(link, 'outgoing')?.subject || link.link_type || '—' }}
                      </div>
                    </RouterLink>
                  </div>
                  <div
                    v-else
                    class="parapheur-empty py-6"
                  >
                    Aucun lien sortant
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Dialog Affecter -->
    <VDialog
      v-model="showAssignDialog"
      max-width="520"
    >
      <VCard :title="`Affecter — ${formatCorrespondenceNumber(correspondence)}`">
        <VCardText>
          <AppSelect
            v-model="assignForm.to_user_id"
            class="mb-3"
            :items="users"
            item-title="name"
            item-value="id"
            label="Agent destinataire *"
          />
          <AppTextField
            v-model="assignForm.due_date"
            class="mb-3"
            type="date"
            label="Échéance"
          />
          <AppTextarea
            v-model="assignForm.instruction_text"
            label="Instruction"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showAssignDialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="assigning"
            :disabled="!assignForm.to_user_id"
            @click="handleAssign"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Demande complément -->
    <VDialog v-model="showComplementDialog" max-width="600">
      <VCard>
        <VCardTitle>Demander un complément</VCardTitle>
        <VCardText>
          <VTextarea
            v-model="complementForm.observation"
            label="Observation"
            rows="4"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showComplementDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="handleRequestComplement">
            Envoyer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Relance -->
    <VDialog v-model="showReminderDialog" max-width="600">
      <VCard>
        <VCardTitle>Créer une relance manuelle</VCardTitle>
        <VCardText>
          <VTextField
            v-model="reminderForm.reminder_date"
            label="Date de relance"
            type="date"
            class="mb-4"
          />
          <VTextarea
            v-model="reminderForm.note"
            label="Note"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showReminderDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="handleCreateReminder">
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Impression -->
    <VDialog v-model="showPrintDialog" max-width="600">
      <VCard>
        <VCardTitle>Imprimer le document</VCardTitle>
        <VCardText>
          <VTextField
            v-model="printForm.reason"
            label="Motif"
            class="mb-4"
          />
          <VTextField
            v-model.number="printForm.copies"
            label="Nombre de copies"
            type="number"
            class="mb-4"
          />
          <VCheckbox
            v-model="printForm.is_reprint"
            label="Réimpression"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showPrintDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="handlePrint">
            Imprimer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Expédition -->
    <VDialog
      v-model="showDispatchDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>
          {{ correspondence.departure_number ? 'Enregistrer l\'expédition' : 'Enregistrer au départ et expédier' }}
        </VCardTitle>
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
            density="compact"
          >
            <template v-if="correspondence.departure_number">
              N° départ déjà attribué : <strong>{{ correspondence.departure_number }}</strong>.
              Cette action passe le courrier en « Expédié » et génère le bordereau d’envoi (BE).
            </template>
            <template v-else>
              Aucun N° DEP pour l’instant : l’expédition attribuera automatiquement le numéro de départ,
              puis générera le bordereau d’envoi.
            </template>
          </VAlert>
          <div
            v-if="toParties.length || fallbackRecipientName"
            class="mb-4 text-body-2"
          >
            <span class="text-medium-emphasis">Destinataire :</span>
            {{ toParties.map(p => partyDisplayName(p)).filter(Boolean).join(', ') || fallbackRecipientName }}
          </div>
          <VSelect
            v-model="dispatchForm.method"
            :items="[
              { value: 'courrier', title: 'Courrier postal' },
              { value: 'coursier', title: 'Coursier' },
              { value: 'email', title: 'Courriel' },
              { value: 'fax', title: 'Fax' },
              { value: 'plateforme', title: 'Plateforme' },
            ]"
            label="Mode d'expédition *"
            class="mb-4"
          />
          <VTextField
            v-model="dispatchForm.date"
            label="Date d'expédition"
            type="date"
            class="mb-4"
          />
          <VTextField
            v-model="dispatchForm.tracking_number"
            label="N° de suivi (optionnel)"
            class="mb-4"
          />
          <VTextarea
            v-model="dispatchForm.observations"
            label="Observations"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showDispatchDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="dispatching"
            @click="handleDispatch"
          >
            Confirmer l'expédition
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Upload version signée -->
    <VDialog v-model="showSignedUploadDialog" max-width="600">
      <VCard>
        <VCardTitle>Attacher la version signée</VCardTitle>
        <VCardText>
          <VFileInput
            label="Fichier PDF signé"
            accept=".pdf"
            @change="onFileChange"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showSignedUploadDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="handleUploadSigned">
            Attacher
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
