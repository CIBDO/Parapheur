<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import { formatDateFr, formatDateTimeFr } from '@/utils/parapheurUi'
import {
  attendanceLabels,
  decisionStatusLabels,
  documentKindLabels,
  downloadMeetingHtml,
  exportKindLabels,
  isOnlyOfficeEditableDocument,
  meetingStatusColor,
  meetingStatusLabels,
  openMeetingHtml,
} from '@/utils/meetingsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
    navActiveLink: 'parapheur-reunions',
  },
})

const route = useRoute()
const ability = useAbility()
const id = computed(() => Number(route.params.id))

const meeting = ref<any>(null)
const users = ref<any[]>([])
const documents = ref<any[]>([])
const audit = ref<any[]>([])
const tab = ref('synthese')
const mobileTab = computed({
  get: () => tab.value,
  set: (v: string) => { tab.value = v },
})
const busy = ref(false)
const errorMessage = ref('')
const isMobile = useMediaQuery('(max-width: 960px)')

const tabs = [
  { value: 'synthese', title: 'Synthèse' },
  { value: 'agenda', title: 'Ordre du jour' },
  { value: 'participants', title: 'Participants' },
  { value: 'documents', title: 'Documents' },
  { value: 'notes', title: 'Notes' },
  { value: 'decisions', title: 'Décisions' },
  { value: 'minutes', title: 'Compte rendu' },
  { value: 'historique', title: 'Historique' },
]

const agendaForm = ref({ title: '', description: '', presenter_id: null as number | null, duration_minutes: 15 })
const participantUserId = ref<number | null>(null)
const participantType = ref<'interne' | 'externe'>('interne')
const externalForm = ref({
  external_name: '',
  external_function: '',
  external_structure: '',
  email: '',
  phone: '',
  is_required: true,
})
const existingDocId = ref<number | null>(null)
const uploadFile = ref<File[] | File | null>(null)
const selectedMeetingDocId = ref<number | null>(null)
const meetingEditorRemountKey = ref(0)
const noteForm = ref({ visibility: 'officielle', section: 'resume', body: '', agenda_item_id: null as number | null })
const decisionForm = ref({ title: '', body: '', assignee_id: null as number | null, due_date: '', create_instruction: true, agenda_item_id: null as number | null })
const postponeForm = ref({ meeting_date: '', meeting_time: '', reason: '' })
const cancelReason = ref('')
const postponeDialog = ref(false)
const cancelDialog = ref(false)
const minutesKind = ref('cr_detaille')
const minutesBody = ref('')

const load = async () => {
  meeting.value = await $api(`/meetings/${id.value}`)
  minutesBody.value = meeting.value.minutes?.[0]?.body || ''
}

onMounted(async () => {
  const [people, docs] = await Promise.all([
    $api('/meta/users'),
    $api('/ged/documents', { query: { per_page: 50 } }).catch(() => ({ data: [] })),
  ])
  users.value = people
  documents.value = docs.data ?? docs
  await load()
})

const run = async (fn: () => Promise<unknown>) => {
  busy.value = true
  errorMessage.value = ''
  try {
    await fn()
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || e?.message || 'Action impossible'
  }
  finally {
    busy.value = false
  }
}

const transition = (status: string) => run(() => $api(`/meetings/${id.value}/transition`, { method: 'POST', body: { status } }))
const sendInvitations = () => run(() => $api(`/meetings/${id.value}/send-invitations`, { method: 'POST' }))
const generateConvocation = () => run(() => $api(`/meetings/${id.value}/convocation`, { method: 'POST' }))
const submitConvocation = () => run(() => $api(`/meetings/${id.value}/convocation/submit`, { method: 'POST' }))
const confirm = (status: string) => run(() => $api(`/meetings/${id.value}/confirm`, { method: 'POST', body: { status } }))
const addAgenda = () => run(async () => {
  await $api(`/meetings/${id.value}/agenda`, { method: 'POST', body: agendaForm.value })
  agendaForm.value = { title: '', description: '', presenter_id: null, duration_minutes: 15 }
})
const addParticipant = () => run(async () => {
  if (participantType.value === 'interne') {
    if (!participantUserId.value)
      return
    await $api(`/meetings/${id.value}/participants`, {
      method: 'POST',
      body: {
        participation_type: 'interne',
        user_id: participantUserId.value,
      },
    })
    participantUserId.value = null

    return
  }

  if (!externalForm.value.external_name.trim() || !externalForm.value.email.trim()) {
    errorMessage.value = 'Nom et e-mail obligatoires pour un invité externe.'

    return
  }

  await $api(`/meetings/${id.value}/participants`, {
    method: 'POST',
    body: {
      participation_type: 'externe',
      ...externalForm.value,
    },
  })
  externalForm.value = {
    external_name: '',
    external_function: '',
    external_structure: '',
    email: '',
    phone: '',
    is_required: true,
  }
})

const removeParticipant = (participantId: number) => run(() =>
  $api(`/meetings/${id.value}/participants/${participantId}`, { method: 'DELETE' }),
)

const participantLabel = (p: any) => p.user?.name || p.external_name || p.email || 'Invité'
const participantMeta = (p: any) => {
  if (p.participation_type === 'externe' || (!p.user_id && p.external_name)) {
    return [p.email, p.external_structure, p.external_function].filter(Boolean).join(' · ')
  }

  return [p.user?.structure?.name, p.user?.position_title].filter(Boolean).join(' · ')
}
const attachExisting = () => run(async () => {
  if (!existingDocId.value)
    return
  await $api(`/meetings/${id.value}/documents`, { method: 'POST', body: { document_id: existingDocId.value } })
  existingDocId.value = null
})
const uploadNew = () => run(async () => {
  const file = Array.isArray(uploadFile.value) ? uploadFile.value[0] : uploadFile.value
  if (!file)
    return
  const body = new FormData()
  body.append('file', file)
  await $api(`/meetings/${id.value}/documents`, { method: 'POST', body })
  uploadFile.value = null
})

const selectedMeetingDocument = computed(() =>
  (meeting.value?.documents || []).find((link: any) => link.document?.id === selectedMeetingDocId.value)?.document
  ?? null,
)
const selectedMeetingDocIsOffice = computed(() => isOnlyOfficeEditableDocument(selectedMeetingDocument.value))

const openMeetingDocument = (documentId?: number | null) => {
  if (!documentId)
    return
  selectedMeetingDocId.value = documentId
  meetingEditorRemountKey.value += 1
}

const closeMeetingDocument = () => {
  selectedMeetingDocId.value = null
}

const onMeetingOnlyOfficeSaved = async () => {
  await load()
}

const onMeetingOnlyOfficeReload = () => {
  meetingEditorRemountKey.value += 1
}

const addNote = () => run(async () => {
  await $api(`/meetings/${id.value}/notes`, { method: 'POST', body: noteForm.value })
  noteForm.value.body = ''
})
const addDecision = () => run(async () => {
  await $api(`/meetings/${id.value}/decisions`, { method: 'POST', body: decisionForm.value })
  decisionForm.value = { title: '', body: '', assignee_id: null, due_date: '', create_instruction: true, agenda_item_id: meeting.value?.current_agenda_item_id }
})
const generateMinutes = () => run(async () => {
  const minute = await $api(`/meetings/${id.value}/minutes`, { method: 'POST', body: { kind: minutesKind.value } })
  minutesBody.value = minute.body
})
const saveMinutes = () => run(async () => {
  const current = meeting.value?.minutes?.[0]
  if (!current)
    return
  await $api(`/meetings/${id.value}/minutes/${current.id}`, { method: 'PUT', body: { body: minutesBody.value } })
})
const submitMinutes = () => run(async () => {
  const current = meeting.value?.minutes?.[0]
  if (!current)
    return
  await $api(`/meetings/${id.value}/minutes/${current.id}/submit`, { method: 'POST' })
})
const validateMinutes = () => run(async () => {
  const current = meeting.value?.minutes?.[0]
  if (!current)
    return
  await $api(`/meetings/${id.value}/minutes/${current.id}/validate`, { method: 'POST' })
})
const diffuseMinutes = () => run(async () => {
  const current = meeting.value?.minutes?.[0]
  if (!current)
    return
  await $api(`/meetings/${id.value}/minutes/${current.id}/diffuse`, { method: 'POST' })
})

const openExport = async (kind: string, print = false) => {
  try {
    await openMeetingHtml(`/api/meetings/${id.value}/export/${kind}`, { print })
  }
  catch (e: any) {
    errorMessage.value = e?.message || 'Export impossible'
  }
}

const downloadExport = async (kind: string) => {
  try {
    await downloadMeetingHtml(
      `/api/meetings/${id.value}/export/${kind}`,
      `${kind}-${meeting.value?.reference || id.value}`,
    )
  }
  catch (e: any) {
    errorMessage.value = e?.message || 'Téléchargement impossible'
  }
}

const previewMinutes = async (print = false) => {
  const current = meeting.value?.minutes?.[0]
  if (!current)
    return
  try {
    await openMeetingHtml(`/api/meetings/${id.value}/minutes/${current.id}/preview`, { print })
  }
  catch (e: any) {
    errorMessage.value = e?.message || 'Aperçu impossible'
  }
}

const postpone = () => run(async () => {
  await $api(`/meetings/${id.value}/postpone`, { method: 'POST', body: postponeForm.value })
  postponeDialog.value = false
})
const cancelMeeting = () => run(async () => {
  await $api(`/meetings/${id.value}/cancel`, { method: 'POST', body: { reason: cancelReason.value } })
  cancelDialog.value = false
})
const loadAudit = async () => {
  audit.value = await $api(`/meetings/${id.value}/audit`)
}
const setAttendance = (participantId: number, status: string) => run(() =>
  $api(`/meetings/${id.value}/participants/${participantId}/attendance`, { method: 'POST', body: { attendance_status: status } }),
)

watch(tab, (value) => {
  if (value === 'historique' && !audit.value.length)
    loadAudit()
})

const statusLabel = computed(() => meetingStatusLabels[meeting.value?.status] || meeting.value?.status_label)
</script>

<template>
  <div v-if="meeting">
    <ParapheurPageHeader
      :title="meeting.object || meeting.title"
      :subtitle="`${meeting.reference} · ${formatDateFr(meeting.meeting_date)} ${meeting.meeting_time || ''} · ${meeting.location || 'Lieu non précisé'}`"
      icon="tabler-users-group"
    >
      <template #actions>
        <VChip
          :color="meetingStatusColor(meeting.status)"
          variant="tonal"
        >
          {{ statusLabel }}
        </VChip>
        <VBtn
          v-if="meeting.status === 'en_cours' || meeting.status === 'prete'"
          color="primary"
          :to="{ name: 'parapheur-reunions-seance-id', params: { id: meeting.id } }"
        >
          Mode séance
        </VBtn>
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              variant="tonal"
              prepend-icon="tabler-printer"
            >
              Exporter
            </VBtn>
          </template>
          <VList density="compact">
            <VListItem
              v-for="(label, kind) in exportKindLabels"
              :key="kind"
              :title="label"
              @click="openExport(String(kind))"
            />
            <VDivider />
            <VListItem
              title="Imprimer l’ordre du jour"
              @click="openExport('agenda', true)"
            />
            <VListItem
              title="Télécharger la présence"
              @click="downloadExport('attendance')"
            />
          </VList>
        </VMenu>
        <VBtn
          v-if="ability.can('manage', 'Meeting')"
          variant="tonal"
          :loading="busy"
          @click="generateConvocation"
        >
          Générer convocation
        </VBtn>
        <VBtn
          v-if="ability.can('manage', 'Meeting') && meeting.convocation_document"
          variant="tonal"
          color="secondary"
          :loading="busy"
          @click="submitConvocation"
        >
          Soumettre convocation
        </VBtn>
        <VBtn
          v-if="ability.can('manage', 'Meeting')"
          color="primary"
          :loading="busy"
          @click="sendInvitations"
        >
          Diffuser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VSelect
      v-if="isMobile"
      v-model="mobileTab"
      :items="tabs"
      item-title="title"
      item-value="value"
      class="mb-4"
    />
    <VTabs
      v-else
      v-model="tab"
      class="mb-4"
    >
      <VTab
        v-for="item in tabs"
        :key="item.value"
        :value="item.value"
      >
        {{ item.title }}
      </VTab>
    </VTabs>

    <VWindow v-model="tab">
      <VWindowItem value="synthese">
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <VCard class="mb-4">
              <VCardText>
                <VRow>
                  <VCol cols="6"><strong>Président</strong><div>{{ meeting.chair?.name || '—' }}</div></VCol>
                  <VCol cols="6"><strong>Secrétaire</strong><div>{{ meeting.secretary?.name || '—' }}</div></VCol>
                  <VCol cols="6"><strong>Confidentialité</strong><div>{{ meeting.confidentiality }}</div></VCol>
                  <VCol cols="6"><strong>Type</strong><div>{{ meeting.type?.name || '—' }}</div></VCol>
                </VRow>
                <p
                  v-if="meeting.description"
                  class="mt-4 mb-0"
                >
                  {{ meeting.description }}
                </p>
                <div
                  v-if="meeting.convocation_document || meeting.minutes_document"
                  class="d-flex flex-wrap gap-2 mt-4"
                >
                  <VBtn
                    v-if="meeting.convocation_document"
                    size="small"
                    variant="tonal"
                    :to="{ name: 'parapheur-id', params: { id: meeting.convocation_document.id } }"
                  >
                    Convocation parapheur
                  </VBtn>
                  <VBtn
                    v-if="meeting.minutes_document"
                    size="small"
                    variant="tonal"
                    color="secondary"
                    :to="{ name: 'parapheur-id', params: { id: meeting.minutes_document.id } }"
                  >
                    Compte rendu parapheur
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
            <VCard v-if="meeting.previous_open_decisions?.length">
              <VCardTitle>Suivi des décisions précédentes</VCardTitle>
              <VList>
                <VListItem
                  v-for="d in meeting.previous_open_decisions"
                  :key="d.id"
                >
                  <VListItemTitle>{{ d.title }}</VListItemTitle>
                  <VListItemSubtitle>
                    {{ d.assignee?.name || '—' }} · {{ d.status_label }} · {{ formatDateFr(d.due_date) }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCard>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VCard class="mb-4">
              <VCardText>
                <div class="d-flex justify-space-between mb-2"><span>Participants</span><strong>{{ meeting.stats?.participants }}</strong></div>
                <div class="d-flex justify-space-between mb-2"><span>Confirmation</span><strong>{{ meeting.stats?.confirmation_rate }} %</strong></div>
                <div class="d-flex justify-space-between mb-2"><span>Documents</span><strong>{{ meeting.stats?.documents }}</strong></div>
                <div class="d-flex justify-space-between mb-2"><span>Décisions</span><strong>{{ meeting.stats?.decisions }}</strong></div>
                <div class="d-flex justify-space-between"><span>Compte rendu</span><strong>{{ meeting.stats?.minutes_status || '—' }}</strong></div>
              </VCardText>
            </VCard>
            <VCard>
              <VCardTitle>Actions</VCardTitle>
              <VCardText class="d-flex flex-column gap-2">
                <VBtn
                  v-for="st in meeting.allowed_transitions"
                  :key="st"
                  variant="tonal"
                  size="small"
                  :loading="busy"
                  @click="transition(st)"
                >
                  {{ meetingStatusLabels[st] || st }}
                </VBtn>
                <VBtn
                  variant="tonal"
                  size="small"
                  @click="postponeDialog = true"
                >
                  Reporter
                </VBtn>
                <VBtn
                  color="error"
                  variant="tonal"
                  size="small"
                  @click="cancelDialog = true"
                >
                  Annuler
                </VBtn>
                <VBtn
                  variant="text"
                  size="small"
                  @click="confirm('confirme')"
                >
                  Je participerai
                </VBtn>
                <VBtn
                  variant="text"
                  size="small"
                  @click="confirm('excuse')"
                >
                  Je serai absent
                </VBtn>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <VWindowItem value="agenda">
        <VCard>
          <VCardText>
            <div
              v-for="item in meeting.agenda_items"
              :key="item.id"
              class="mb-4 pa-3"
              style="border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 10px"
            >
              <div class="d-flex justify-space-between">
                <strong>{{ item.item_number }}. {{ item.title }}</strong>
                <VChip
                  v-if="item.is_follow_up"
                  size="small"
                  color="warning"
                >
                  Suivi
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.presenter?.name || '—' }} · {{ item.duration_minutes ? `${item.duration_minutes} min` : '' }}
              </div>
              <div
                v-if="item.description"
                class="mt-2"
              >
                {{ item.description }}
              </div>
            </div>
            <div class="parapheur-form-section mt-4 mb-0">
              <AppTextField
                v-model="agendaForm.title"
                label="Nouveau point"
                class="mb-2"
              />
              <VBtn
                color="primary"
                :loading="busy"
                @click="addAgenda"
              >
                Ajouter
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="participants">
        <VCard>
          <VCardText>
            <VBtnToggle
              v-model="participantType"
              mandatory
              density="compact"
              variant="tonal"
              divided
              class="mb-4"
            >
              <VBtn value="interne">
                Interne
              </VBtn>
              <VBtn value="externe">
                Externe
              </VBtn>
            </VBtnToggle>

            <div v-if="participantType === 'interne'">
              <AppSelect
                v-model="participantUserId"
                :items="users"
                item-title="name"
                item-value="id"
                label="Utilisateur e-Parapheur"
                class="mb-3"
              />
            </div>
            <VRow v-else>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="externalForm.external_name"
                  label="Nom et prénoms *"
                  class="mb-3"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="externalForm.email"
                  type="email"
                  label="E-mail *"
                  class="mb-3"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="externalForm.phone"
                  label="Téléphone"
                  class="mb-3"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="externalForm.external_function"
                  label="Fonction"
                  class="mb-3"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="externalForm.external_structure"
                  label="Structure / organisme"
                  class="mb-3"
                />
              </VCol>
            </VRow>

            <VBtn
              class="mb-6"
              color="primary"
              :loading="busy"
              prepend-icon="tabler-user-plus"
              @click="addParticipant"
            >
              Ajouter le participant
            </VBtn>

            <VTable>
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Identité</th>
                  <th>Convocation</th>
                  <th>Confirmation</th>
                  <th>Présence</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in meeting.participants"
                  :key="p.id"
                >
                  <td>
                    <VChip
                      size="small"
                      :color="(p.participation_type === 'externe' || (!p.user_id && p.external_name)) ? 'warning' : 'primary'"
                      variant="tonal"
                    >
                      {{ (p.participation_type === 'externe' || (!p.user_id && p.external_name)) ? 'Externe' : 'Interne' }}
                    </VChip>
                  </td>
                  <td>
                    <div>{{ participantLabel(p) }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ participantMeta(p) || '—' }}
                    </div>
                  </td>
                  <td>{{ p.invitation_status }}</td>
                  <td>{{ p.confirmation_status || '—' }}</td>
                  <td>{{ attendanceLabels[p.attendance_status] || p.attendance_status || '—' }}</td>
                  <td class="d-flex gap-1">
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      @click="setAttendance(p.id, 'present')"
                    >
                      Présent
                    </VBtn>
                    <VBtn
                      size="x-small"
                      variant="text"
                      color="error"
                      icon
                      @click="removeParticipant(p.id)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="16"
                      />
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="documents">
        <VCard>
          <VCardText>
            <AppSelect
              v-model="existingDocId"
              :items="documents"
              :item-title="(i: any) => `${i.reference || i.id} — ${i.object}`"
              item-value="id"
              label="Sélectionner un document existant"
              class="mb-3"
            />
            <VBtn
              class="mb-4 me-2"
              :loading="busy"
              @click="attachExisting"
            >
              Rattacher
            </VBtn>
            <VFileInput
              v-model="uploadFile"
              label="Téléverser un nouveau document"
              class="mb-3"
              multiple
            />
            <VBtn
              class="mb-6"
              :loading="busy"
              @click="uploadNew"
            >
              Téléverser
            </VBtn>
            <VList>
              <VListItem
                v-for="link in meeting.documents"
                :key="link.id"
              >
                <VListItemTitle>{{ link.document?.object }}</VListItemTitle>
                <VListItemSubtitle>{{ documentKindLabels[link.kind] || link.kind }} · {{ link.document?.reference }}</VListItemSubtitle>
                <template #append>
                  <div class="d-flex flex-wrap gap-2">
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :disabled="!link.document?.id"
                      @click="openMeetingDocument(link.document?.id)"
                    >
                      Ouvrir
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="text"
                      :to="{ name: 'parapheur-id', params: { id: link.document?.id } }"
                      :disabled="!link.document?.id"
                    >
                      Fiche parapheur
                    </VBtn>
                  </div>
                </template>
              </VListItem>
            </VList>

            <div
              v-if="selectedMeetingDocId && selectedMeetingDocument"
              class="mt-6"
            >
              <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
                <div>
                  <div class="text-subtitle-1">
                    {{ selectedMeetingDocument.object }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ selectedMeetingDocument.reference }}
                  </div>
                </div>
                <VBtn
                  variant="tonal"
                  size="small"
                  @click="closeMeetingDocument"
                >
                  Fermer
                </VBtn>
              </div>

              <OnlyOfficeEditor
                v-if="selectedMeetingDocIsOffice"
                :key="`oo-meeting-${selectedMeetingDocId}-${meetingEditorRemountKey}`"
                :document-id="selectedMeetingDocId"
                @saved="onMeetingOnlyOfficeSaved"
                @reload="onMeetingOnlyOfficeReload"
                @error="(msg) => { errorMessage = msg }"
              />
              <VAlert
                v-else
                type="info"
                variant="tonal"
              >
                Ce fichier n’est pas éditable via ONLYOFFICE (DOCX, XLSX ou PPTX requis).
                <RouterLink
                  class="ms-1"
                  :to="{ name: 'parapheur-id', params: { id: selectedMeetingDocId } }"
                >
                  Ouvrir la fiche parapheur
                </RouterLink>
              </VAlert>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="notes">
        <VCard>
          <VCardText>
            <AppSelect
              v-model="noteForm.visibility"
              :items="[{ value: 'officielle', title: 'Note officielle' }, { value: 'privee', title: 'Note privée' }]"
              class="mb-2"
            />
            <AppSelect
              v-model="noteForm.section"
              :items="[
                { value: 'resume', title: 'Synthèse' },
                { value: 'observations', title: 'Observations' },
                { value: 'recommandations', title: 'Recommandations' },
                { value: 'decision', title: 'Décision' },
              ]"
              class="mb-2"
            />
            <AppSelect
              v-model="noteForm.agenda_item_id"
              :items="meeting.agenda_items"
              item-title="title"
              item-value="id"
              label="Point d’ordre du jour"
              clearable
              class="mb-2"
            />
            <AppTextarea
              v-model="noteForm.body"
              label="Note"
              class="mb-2"
            />
            <VBtn
              color="primary"
              class="mb-6"
              :loading="busy"
              @click="addNote"
            >
              Enregistrer la note
            </VBtn>
            <div
              v-for="note in meeting.notes"
              :key="note.id"
              class="mb-3"
            >
              <VChip
                size="small"
                class="me-2"
                :color="note.visibility === 'privee' ? 'warning' : 'primary'"
              >
                {{ note.visibility }}
              </VChip>
              <strong>{{ note.author?.name }}</strong>
              <div style="white-space: pre-wrap">
                {{ note.body }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="decisions">
        <VCard>
          <VCardText>
            <AppTextField
              v-model="decisionForm.title"
              label="Libellé de la décision"
              class="mb-2"
            />
            <AppTextarea
              v-model="decisionForm.body"
              label="Description"
              class="mb-2"
            />
            <AppSelect
              v-model="decisionForm.assignee_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Responsable"
              class="mb-2"
            />
            <AppTextField
              v-model="decisionForm.due_date"
              type="date"
              label="Échéance"
              class="mb-2"
            />
            <VSwitch
              v-model="decisionForm.create_instruction"
              label="Générer une instruction de suivi"
              color="primary"
            />
            <VBtn
              color="primary"
              class="mb-6"
              :loading="busy"
              @click="addDecision"
            >
              Nouvelle décision
            </VBtn>
            <VList>
              <VListItem
                v-for="d in meeting.decisions"
                :key="d.id"
              >
                <VListItemTitle>{{ d.reference }} — {{ d.title }}</VListItemTitle>
                <VListItemSubtitle>
                  {{ d.assignee?.name || 'Sans responsable' }}
                  · {{ decisionStatusLabels[d.status] || d.status_label }}
                  · {{ formatDateFr(d.due_date) }}
                  <span v-if="d.instruction"> · instruction #{{ d.instruction.id }}</span>
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="minutes">
        <VCard>
          <VCardText>
            <AppSelect
              v-model="minutesKind"
              :items="[
                { value: 'cr_simple', title: 'Compte rendu simple' },
                { value: 'cr_detaille', title: 'Compte rendu détaillé' },
                { value: 'pv', title: 'Procès-verbal' },
                { value: 'releve_decisions', title: 'Relevé de décisions' },
              ]"
              class="mb-3"
            />
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VBtn
                :loading="busy"
                @click="generateMinutes"
              >
                Générer le projet
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="saveMinutes"
              >
                Enregistrer
              </VBtn>
              <VBtn
                variant="tonal"
                :loading="busy"
                @click="submitMinutes"
              >
                Soumettre
              </VBtn>
              <VBtn
                color="primary"
                :loading="busy"
                @click="validateMinutes"
              >
                Valider
              </VBtn>
              <VBtn
                :loading="busy"
                @click="diffuseMinutes"
              >
                Diffuser
              </VBtn>
              <VBtn
                v-if="meeting.minutes?.[0]"
                variant="tonal"
                prepend-icon="tabler-eye"
                @click="previewMinutes()"
              >
                Aperçu
              </VBtn>
              <VBtn
                v-if="meeting.minutes?.[0]"
                variant="tonal"
                prepend-icon="tabler-printer"
                @click="previewMinutes(true)"
              >
                Imprimer
              </VBtn>
              <VBtn
                v-if="meeting.minutes_document"
                variant="text"
                :to="{ name: 'parapheur-id', params: { id: meeting.minutes_document.id } }"
              >
                Ouvrir dans le parapheur
              </VBtn>
            </div>
            <div class="text-body-2 text-medium-emphasis mb-2">
              Projet de compte rendu
            </div>
            <TiptapEditor
              v-model="minutesBody"
              placeholder="Générez le projet puis affinez le texte ici…"
              class="minutes-editor border rounded"
            />
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="historique">
        <VCard>
          <VList>
            <VListItem
              v-for="log in audit"
              :key="log.id"
            >
              <VListItemTitle>{{ log.action }}</VListItemTitle>
              <VListItemSubtitle>{{ formatDateTimeFr(log.created_at) }} · {{ log.user?.name || 'Système' }}</VListItemSubtitle>
            </VListItem>
          </VList>
        </VCard>
      </VWindowItem>
    </VWindow>

    <VDialog
      v-model="postponeDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Reporter la réunion</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="postponeForm.meeting_date"
            type="date"
            label="Nouvelle date"
            class="mb-3"
          />
          <AppTextField
            v-model="postponeForm.meeting_time"
            type="time"
            label="Nouvelle heure"
            class="mb-3"
          />
          <AppTextarea
            v-model="postponeForm.reason"
            label="Motif"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="postponeDialog = false">
            Fermer
          </VBtn>
          <VBtn
            color="primary"
            @click="postpone"
          >
            Reporter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="cancelDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Annuler la réunion</VCardTitle>
        <VCardText>
          <AppTextarea
            v-model="cancelReason"
            label="Motif (obligatoire)"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="cancelDialog = false">
            Fermer
          </VBtn>
          <VBtn
            color="error"
            @click="cancelMeeting"
          >
            Confirmer l’annulation
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped lang="scss">
.minutes-editor {
  :deep(.ProseMirror) {
    min-block-size: 28rem;
    padding: 1rem;
  }
}
</style>
