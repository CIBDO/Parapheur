<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { appointmentStatusColor, appointmentStatusLabel, formatAppointmentSlot } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
    navActiveLink: 'parapheur-agenda',
  },
})

const route = useRoute()
const ability = useAbility()
const id = computed(() => Number(route.params.id))
const tab = ref('synthese')
const appointment = ref<any>(null)
const preparation = ref<any>(null)
const audit = ref<any[]>([])
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const conflicts = ref<any[]>([])
const suggestions = ref<any>(null)

const slotForm = ref({ start_at: '', duration_minutes: 30, location: '', submit_to_dg: true, force: false })
const noteForm = ref({ body: '', visibility: 'institutionnelle' })
const followupForm = ref({ kind: 'instruction', title: '', description: '', assignee_id: null as number | null, due_date: '' })
const users = ref<any[]>([])
const structures = ref<any[]>([])
const availableDocs = ref<any[]>([])
const documentId = ref<number | null>(null)
const uploadFile = ref<File[] | File | null>(null)

const rescheduleDialog = ref(false)
const redirectDialog = ref(false)
const rejectDialog = ref(false)
const cancelDialog = ref(false)
const holdDialog = ref(false)

const rescheduleForm = ref({ start_at: '', duration_minutes: 30, reason: '', location: '', reconfirm: true, force: false })
const redirectForm = ref({ redirected_to_user_id: null as number | null, redirected_to_structure_id: null as number | null, reason: '' })
const rejectForm = ref({ reason: '', communicable: true })
const cancelReason = ref('')
const holdReason = ref('')

const toLocalInput = (iso?: string | null) => {
  if (!iso)
    return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const load = async () => {
  loading.value = true
  try {
    appointment.value = await $api(`/appointments/${id.value}`)
    if (appointment.value.start_at) {
      slotForm.value.start_at = toLocalInput(appointment.value.start_at)
      slotForm.value.duration_minutes = appointment.value.duration_minutes || 30
      slotForm.value.location = appointment.value.location || ''
      rescheduleForm.value.start_at = slotForm.value.start_at
      rescheduleForm.value.duration_minutes = slotForm.value.duration_minutes
      rescheduleForm.value.location = slotForm.value.location
    }
  }
  finally {
    loading.value = false
  }
}

const run = async (fn: () => Promise<void>) => {
  busy.value = true
  error.value = ''
  conflicts.value = []
  suggestions.value = null
  try {
    await fn()
    await load()
  }
  catch (e: any) {
    error.value = e?.data?.message || e?.message || 'Action impossible'
    conflicts.value = e?.data?.conflicts || []
    suggestions.value = e?.data?.suggestions || null
  }
  finally {
    busy.value = false
  }
}

const proposeSlot = () => run(async () => {
  await $api(`/appointments/${id.value}/propose-slot`, {
    method: 'POST',
    body: {
      ...slotForm.value,
      start_at: new Date(slotForm.value.start_at).toISOString(),
    },
  })
})

const validateAppt = () => run(async () => {
  await $api(`/appointments/${id.value}/validate`, { method: 'POST', body: { confirm: true } })
})

const confirmAppt = () => run(async () => {
  await $api(`/appointments/${id.value}/confirm`, { method: 'POST' })
})

const startAppt = () => run(async () => {
  await $api(`/appointments/${id.value}/start`, { method: 'POST' })
})

const finishAppt = () => run(async () => {
  await $api(`/appointments/${id.value}/finish`, { method: 'POST', body: { has_followup: true } })
})

const closeAppt = () => run(async () => {
  await $api(`/appointments/${id.value}/close`, { method: 'POST' })
})

const submitReject = () => run(async () => {
  await $api(`/appointments/${id.value}/reject`, { method: 'POST', body: rejectForm.value })
  rejectDialog.value = false
})

const submitCancel = () => run(async () => {
  await $api(`/appointments/${id.value}/cancel`, { method: 'POST', body: { reason: cancelReason.value } })
  cancelDialog.value = false
})

const submitHold = () => run(async () => {
  await $api(`/appointments/${id.value}/hold`, { method: 'POST', body: { complement_request: holdReason.value } })
  holdDialog.value = false
})

const submitReschedule = () => run(async () => {
  await $api(`/appointments/${id.value}/reschedule`, {
    method: 'POST',
    body: {
      ...rescheduleForm.value,
      start_at: new Date(rescheduleForm.value.start_at).toISOString(),
    },
  })
  rescheduleDialog.value = false
})

const submitRedirect = () => run(async () => {
  await $api(`/appointments/${id.value}/redirect`, { method: 'POST', body: redirectForm.value })
  redirectDialog.value = false
})

const addNote = () => run(async () => {
  await $api(`/appointments/${id.value}/notes`, { method: 'POST', body: noteForm.value })
  noteForm.value.body = ''
})

const addFollowup = () => run(async () => {
  await $api(`/appointments/${id.value}/followups`, { method: 'POST', body: followupForm.value })
  followupForm.value = { kind: 'instruction', title: '', description: '', assignee_id: null, due_date: '' }
})

const attachDocument = () => run(async () => {
  if (!documentId.value)
    return
  await $api(`/appointments/${id.value}/documents`, { method: 'POST', body: { document_id: documentId.value } })
  documentId.value = null
})

const uploadNew = () => run(async () => {
  const file = Array.isArray(uploadFile.value) ? uploadFile.value[0] : uploadFile.value
  if (!file)
    return
  const body = new FormData()
  body.append('file', file)
  await $api(`/appointments/${id.value}/documents`, { method: 'POST', body })
  uploadFile.value = null
})

const convertMeeting = () => run(async () => {
  const res = await $api(`/appointments/${id.value}/convert-to-meeting`, { method: 'POST', body: {} })
  if (res.meeting?.id)
    window.location.href = `/parapheur/reunions/${res.meeting.id}`
})

const loadPreparation = async () => {
  preparation.value = await $api(`/appointments/${id.value}/preparation`)
}

const loadAudit = async () => {
  audit.value = await $api(`/appointments/${id.value}/audit`)
}

const canAct = computed(() => !['refuse', 'annule', 'archive', 'cloture'].includes(appointment.value?.status))

watch(tab, async (value) => {
  if (value === 'preparation')
    await loadPreparation()
  if (value === 'historique')
    await loadAudit()
})

onMounted(async () => {
  const [u, s, docs] = await Promise.all([
    $api('/meta/users'),
    $api('/meta/structures'),
    $api('/parapheur/documents', { query: { per_page: 50 } }).catch(() => ({ data: [] })),
  ])
  users.value = u
  structures.value = s
  availableDocs.value = (docs.data ?? docs).map((d: any) => ({
    title: `${d.reference || `#${d.id}`} — ${d.object}`,
    value: d.id,
  }))
  await load()
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="appointment?.subject || 'Rendez-vous'"
      :subtitle="appointment ? `${appointment.reference} · ${appointmentStatusLabel(appointment.status)}` : ''"
      icon="tabler-calendar-event"
    >
      <template #actions>
        <VChip
          v-if="appointment"
          :color="appointmentStatusColor(appointment.status)"
        >
          {{ appointmentStatusLabel(appointment.status) }}
        </VChip>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ error }}
      <ul v-if="conflicts.length">
        <li
          v-for="(c, i) in conflicts"
          :key="i"
        >
          {{ c.message }}
        </li>
      </ul>
      <div
        v-if="suggestions?.before || suggestions?.after"
        class="mt-2"
      >
        Suggestions :
        <span v-if="suggestions.before">avant {{ new Date(suggestions.before.start_at).toLocaleString('fr-FR') }}</span>
        <span v-if="suggestions.after"> · après {{ new Date(suggestions.after.start_at).toLocaleString('fr-FR') }}</span>
      </div>
    </VAlert>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <template v-if="appointment">
      <div class="d-flex flex-wrap gap-2 mb-4">
        <VBtn
          v-if="['demande_recue', 'a_examiner', 'creneau_a_proposer', 'creneau_propose'].includes(appointment.status)"
          color="primary"
          :loading="busy"
          @click="proposeSlot"
        >
          Proposer / Soumettre
        </VBtn>
        <VBtn
          v-if="appointment.status === 'a_valider' && ability.can('validate', 'Appointment')"
          color="success"
          :loading="busy"
          @click="validateAppt"
        >
          Valider
        </VBtn>
        <VBtn
          v-if="appointment.status === 'valide'"
          color="success"
          variant="tonal"
          :loading="busy"
          @click="confirmAppt"
        >
          Confirmer
        </VBtn>
        <VBtn
          v-if="['confirme', 'valide', 'pret', 'a_valider', 'reporte'].includes(appointment.status)"
          color="warning"
          variant="tonal"
          @click="rescheduleDialog = true"
        >
          Reporter
        </VBtn>
        <VBtn
          v-if="canAct"
          variant="tonal"
          @click="redirectDialog = true"
        >
          Réorienter
        </VBtn>
        <VBtn
          v-if="['confirme', 'pret'].includes(appointment.status)"
          color="primary"
          :loading="busy"
          @click="startAppt"
        >
          Démarrer
        </VBtn>
        <VBtn
          v-if="appointment.status === 'en_cours'"
          color="primary"
          :loading="busy"
          @click="finishAppt"
        >
          Terminer
        </VBtn>
        <VBtn
          v-if="['termine', 'suite_a_donner'].includes(appointment.status)"
          variant="tonal"
          :loading="busy"
          @click="closeAppt"
        >
          Clôturer
        </VBtn>
        <VBtn
          v-if="canAct"
          color="error"
          variant="tonal"
          @click="rejectDialog = true"
        >
          Refuser
        </VBtn>
        <VBtn
          v-if="['confirme', 'valide', 'pret', 'a_valider'].includes(appointment.status)"
          color="error"
          variant="outlined"
          @click="cancelDialog = true"
        >
          Annuler
        </VBtn>
        <VBtn
          v-if="canAct"
          variant="tonal"
          @click="holdDialog = true"
        >
          Mettre en attente
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="busy"
          @click="convertMeeting"
        >
          Transformer en réunion
        </VBtn>
      </div>

      <VTabs
        v-model="tab"
        class="mb-4"
      >
        <VTab value="synthese">
          Synthèse
        </VTab>
        <VTab value="participants">
          Participants
        </VTab>
        <VTab value="documents">
          Documents
        </VTab>
        <VTab value="notes">
          Notes
        </VTab>
        <VTab value="suites">
          Suites
        </VTab>
        <VTab value="preparation">
          Fiche DG
        </VTab>
        <VTab value="historique">
          Historique
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
                  <p><strong>Demandeur :</strong> {{ appointment.requester_name }} — {{ appointment.requester_organization }}</p>
                  <p><strong>Fonction :</strong> {{ appointment.requester_position || '—' }}</p>
                  <p><strong>Motif :</strong> {{ appointment.reason || '—' }}</p>
                  <p><strong>Créneau :</strong> {{ formatAppointmentSlot(appointment.start_at, appointment.end_at) }}</p>
                  <p v-if="appointment.previous_start_at">
                    <strong>Créneau initial :</strong> {{ formatAppointmentSlot(appointment.previous_start_at, appointment.previous_end_at) }}
                  </p>
                  <p><strong>Lieu / mode :</strong> {{ appointment.location || '—' }} · {{ appointment.meeting_mode_label }}</p>
                  <p><strong>Confidentialité :</strong> {{ appointment.confidentiality }}</p>
                  <p v-if="appointment.reschedule_reason">
                    <strong>Motif de report :</strong> {{ appointment.reschedule_reason }}
                  </p>
                  <p v-if="appointment.outside_working_hours">
                    <VChip
                      color="warning"
                      size="small"
                    >
                      Hors plage habituelle
                    </VChip>
                  </p>
                  <VDivider class="my-4" />
                  <div class="text-subtitle-2 mb-2">
                    Proposer / ajuster le créneau
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="5"
                    >
                      <VTextField
                        v-model="slotForm.start_at"
                        type="datetime-local"
                        label="Début"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="3"
                    >
                      <VTextField
                        v-model="slotForm.duration_minutes"
                        type="number"
                        label="Durée"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <VTextField
                        v-model="slotForm.location"
                        label="Lieu"
                      />
                    </VCol>
                  </VRow>
                  <VSwitch
                    v-model="slotForm.submit_to_dg"
                    label="Soumettre au DG"
                  />
                  <VSwitch
                    v-model="slotForm.force"
                    label="Forcer malgré conflit"
                    color="warning"
                  />
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VCard>
                <VCardTitle>Contexte</VCardTitle>
                <VCardText style="white-space: pre-wrap">
                  {{ appointment.context_note || '—' }}
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <VWindowItem value="participants">
          <VCard>
            <VList>
              <VListItem
                v-for="p in appointment.participants || []"
                :key="p.id"
              >
                <VListItemTitle>{{ p.display_name }}</VListItemTitle>
                <VListItemSubtitle>{{ p.role }} · {{ p.organization || p.position || p.participation_type }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCard>
        </VWindowItem>

        <VWindowItem value="documents">
          <VCard>
            <VCardText>
              <div class="text-subtitle-2 mb-3">
                Sélectionner un document existant
              </div>
              <div class="d-flex flex-wrap gap-2 mb-6">
                <VSelect
                  v-model="documentId"
                  :items="availableDocs"
                  label="Document du parapheur"
                  clearable
                  style="min-inline-size: 280px; flex: 1"
                />
                <VBtn
                  color="primary"
                  :disabled="!documentId"
                  @click="attachDocument"
                >
                  Lier
                </VBtn>
              </div>

              <div class="text-subtitle-2 mb-3">
                Ajouter un nouveau fichier
              </div>
              <div class="d-flex flex-wrap gap-2 mb-6 align-center">
                <VFileInput
                  v-model="uploadFile"
                  label="Fichier"
                  density="compact"
                  hide-details
                  style="min-inline-size: 280px; flex: 1"
                />
                <VBtn
                  color="primary"
                  variant="tonal"
                  @click="uploadNew"
                >
                  Téléverser
                </VBtn>
              </div>

              <VList>
                <VListItem
                  v-for="doc in appointment.documents || []"
                  :key="doc.id"
                  :to="doc.document ? { name: 'parapheur-id', params: { id: doc.document.id } } : undefined"
                >
                  <VListItemTitle>{{ doc.document?.object || doc.label || `Document #${doc.document_id}` }}</VListItemTitle>
                  <VListItemSubtitle>{{ doc.kind }}</VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="notes">
          <VCard class="mb-4">
            <VCardText>
              <VSelect
                v-model="noteForm.visibility"
                :items="[
                  { title: 'Institutionnelle', value: 'institutionnelle' },
                  { title: 'Privée', value: 'privee' },
                ]"
                label="Visibilité"
                class="mb-3"
              />
              <VTextarea
                v-model="noteForm.body"
                label="Note"
                rows="3"
              />
              <VBtn
                class="mt-3"
                color="primary"
                :disabled="!noteForm.body"
                @click="addNote"
              >
                Ajouter
              </VBtn>
            </VCardText>
          </VCard>
          <VCard
            v-for="note in appointment.notes || []"
            :key="note.id"
            class="mb-2"
          >
            <VCardText>
              <div class="text-caption mb-1">
                {{ note.author?.name }} · {{ note.visibility }}
              </div>
              <div style="white-space: pre-wrap">
                {{ note.body }}
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="suites">
          <VCard class="mb-4">
            <VCardText>
              <VSelect
                v-model="followupForm.kind"
                :items="[
                  { title: 'Instruction', value: 'instruction' },
                  { title: 'Nouveau rendez-vous', value: 'nouveau_rdv' },
                  { title: 'Réunion', value: 'reunion' },
                  { title: 'Document', value: 'document' },
                  { title: 'Aucune', value: 'aucune' },
                  { title: 'Autre', value: 'autre' },
                ]"
                label="Type de suite"
                class="mb-3"
              />
              <VTextField
                v-model="followupForm.title"
                label="Titre"
                class="mb-3"
              />
              <VTextarea
                v-model="followupForm.description"
                label="Description"
                rows="3"
                class="mb-3"
              />
              <VSelect
                v-model="followupForm.assignee_id"
                :items="users"
                item-title="name"
                item-value="id"
                label="Responsable"
                clearable
                class="mb-3"
              />
              <VTextField
                v-model="followupForm.due_date"
                type="date"
                label="Échéance"
                class="mb-3"
              />
              <VBtn
                color="primary"
                @click="addFollowup"
              >
                Enregistrer la suite
              </VBtn>
            </VCardText>
          </VCard>
          <VList>
            <VListItem
              v-for="f in appointment.followups || []"
              :key="f.id"
            >
              <VListItemTitle>{{ f.title || f.kind }}</VListItemTitle>
              <VListItemSubtitle>{{ f.description }}</VListItemSubtitle>
            </VListItem>
          </VList>
        </VWindowItem>

        <VWindowItem value="preparation">
          <VCard v-if="preparation && !preparation.masked">
            <VCardText>
              <div class="text-h6 mb-4">
                Fiche de préparation
              </div>
              <p><strong>Demandeur :</strong> {{ preparation.appointment?.requester_organization || preparation.appointment?.requester_name }}</p>
              <p><strong>Objet :</strong> {{ preparation.appointment?.subject }}</p>
              <p><strong>Date :</strong> {{ formatAppointmentSlot(preparation.appointment?.start_at, preparation.appointment?.end_at) }}</p>
              <VDivider class="my-4" />
              <div class="text-subtitle-2">
                CONTEXTE
              </div>
              <p style="white-space: pre-wrap">
                {{ preparation.context || '—' }}
              </p>
              <div class="text-subtitle-2 mt-4">
                POINTS À ABORDER
              </div>
              <p style="white-space: pre-wrap">
                {{ preparation.points_to_discuss || '—' }}
              </p>
              <div class="text-subtitle-2 mt-4">
                RENDEZ-VOUS PRÉCÉDENTS
              </div>
              <ul>
                <li
                  v-for="prev in preparation.previous_appointments || []"
                  :key="prev.id"
                >
                  {{ prev.reference }} — {{ prev.subject }}
                </li>
              </ul>
              <div class="text-subtitle-2 mt-4">
                INSTRUCTIONS EN COURS
              </div>
              <ul>
                <li
                  v-for="ins in preparation.open_instructions || []"
                  :key="ins.id"
                >
                  {{ ins.title }} ({{ ins.status }})
                </li>
              </ul>
            </VCardText>
          </VCard>
          <VAlert
            v-else-if="preparation?.masked"
            type="warning"
          >
            Créneau indisponible
          </VAlert>
        </VWindowItem>

        <VWindowItem value="historique">
          <VList>
            <VListItem
              v-for="log in audit"
              :key="log.id"
            >
              <VListItemTitle>{{ log.action }}</VListItemTitle>
              <VListItemSubtitle>{{ log.user?.name }} · {{ log.created_at }}</VListItemSubtitle>
            </VListItem>
          </VList>
        </VWindowItem>
      </VWindow>
    </template>

    <VDialog
      v-model="rescheduleDialog"
      max-width="560"
    >
      <VCard title="Reporter le rendez-vous">
        <VCardText>
          <VTextField
            v-model="rescheduleForm.start_at"
            type="datetime-local"
            label="Nouveau créneau"
            class="mb-3"
          />
          <VTextField
            v-model="rescheduleForm.duration_minutes"
            type="number"
            label="Durée (min)"
            class="mb-3"
          />
          <VTextField
            v-model="rescheduleForm.location"
            label="Lieu"
            class="mb-3"
          />
          <VTextarea
            v-model="rescheduleForm.reason"
            label="Motif du report"
            rows="3"
            class="mb-3"
          />
          <VSwitch
            v-model="rescheduleForm.reconfirm"
            label="Reconfirmer après report"
          />
          <VSwitch
            v-model="rescheduleForm.force"
            label="Forcer malgré conflit"
            color="warning"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="rescheduleDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="warning"
            :loading="busy"
            :disabled="!rescheduleForm.start_at"
            @click="submitReschedule"
          >
            Confirmer le report
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="redirectDialog"
      max-width="520"
    >
      <VCard title="Réorienter la demande">
        <VCardText>
          <VSelect
            v-model="redirectForm.redirected_to_user_id"
            :items="users"
            item-title="name"
            item-value="id"
            label="Vers un responsable"
            clearable
            class="mb-3"
          />
          <VSelect
            v-model="redirectForm.redirected_to_structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Vers une structure"
            clearable
            class="mb-3"
          />
          <VTextarea
            v-model="redirectForm.reason"
            label="Motif / traçabilité"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="redirectDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="submitRedirect"
          >
            Réorienter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="rejectDialog"
      max-width="480"
    >
      <VCard title="Refuser la demande">
        <VCardText>
          <VTextarea
            v-model="rejectForm.reason"
            label="Motif"
            rows="3"
            class="mb-3"
          />
          <VSwitch
            v-model="rejectForm.communicable"
            label="Motif communicable au demandeur"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="rejectDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="error"
            :disabled="rejectForm.reason.length < 3"
            :loading="busy"
            @click="submitReject"
          >
            Refuser
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="cancelDialog"
      max-width="480"
    >
      <VCard title="Annuler le rendez-vous">
        <VCardText>
          <VTextarea
            v-model="cancelReason"
            label="Motif d’annulation"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="cancelDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="error"
            :disabled="cancelReason.length < 3"
            :loading="busy"
            @click="submitCancel"
          >
            Annuler le RDV
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="holdDialog"
      max-width="480"
    >
      <VCard title="Mettre en attente">
        <VCardText>
          <VTextarea
            v-model="holdReason"
            label="Complément demandé"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="holdDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="submitHold"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
