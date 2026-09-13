<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  workspaceId: number
  documentId: number
  documentTitle?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  done: []
}>()

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const tab = ref('ged')
const users = ref<any[]>([])
const workflows = ref<any[]>([])
const meetings = ref<any[]>([])
const appointments = ref<any[]>([])
const instructions = ref<any[]>([])
const saving = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const parapheur = ref({
  recipient_ids: [] as number[],
  workflow_id: null as number | null,
  expected_action: 'visa',
  object: '',
  message: '',
  due_date: null as string | null,
})
const meetingId = ref<number | null>(null)
const appointmentId = ref<number | null>(null)
const instructionId = ref<number | null>(null)

onMounted(async () => {
  try {
    const [u, w, m, a, i] = await Promise.all([
      $api('/meta/users'),
      $api('/meta/workflows').catch(() => []),
      $api('/meetings?per_page=50').catch(() => ({ data: [] })),
      $api('/appointments?per_page=50').catch(() => ({ data: [] })),
      $api('/instructions').catch(() => ({ data: [] })),
    ])
    users.value = u.data || u || []
    workflows.value = w.data || w || []
    meetings.value = m.data || m.items || m || []
    appointments.value = a.data || a.items || a || []
    instructions.value = i.data || i.items || i || []
    parapheur.value.object = props.documentTitle || ''
  }
  catch { /* ignore */ }
})

async function submitGed() {
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/documents/${props.documentId}/submit-ged`, { method: 'POST', body: {} })
    successMsg.value = 'Document soumis à la GED.'
    emit('done')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Échec soumission GED'
  }
  finally {
    saving.value = false
  }
}

async function submitParapheur() {
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/documents/${props.documentId}/submit-parapheur`, {
      method: 'POST',
      body: {
        ...parapheur.value,
        recipient_ids: parapheur.value.recipient_ids,
        workflow_id: parapheur.value.workflow_id || undefined,
      },
    })
    successMsg.value = 'Document soumis au parapheur (version figée).'
    emit('done')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Échec soumission parapheur'
  }
  finally {
    saving.value = false
  }
}

async function attachMeeting() {
  if (!meetingId.value)
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/documents/${props.documentId}/attach-meeting`, {
      method: 'POST',
      body: { meeting_id: meetingId.value },
    })
    successMsg.value = 'Document lié à la réunion.'
    emit('done')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Liaison réunion impossible'
  }
  finally {
    saving.value = false
  }
}

async function attachAppointment() {
  if (!appointmentId.value)
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/documents/${props.documentId}/attach-appointment`, {
      method: 'POST',
      body: { appointment_id: appointmentId.value },
    })
    successMsg.value = 'Document lié au rendez-vous.'
    emit('done')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Liaison RDV impossible'
  }
  finally {
    saving.value = false
  }
}

async function attachInstruction() {
  if (!instructionId.value)
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/workspace/${props.workspaceId}/documents/${props.documentId}/attach-instruction`, {
      method: 'POST',
      body: { instruction_id: instructionId.value, as_primary: true },
    })
    successMsg.value = 'Document associé à l’instruction.'
    emit('done')
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Liaison instruction impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="640"
  >
    <VCard>
      <VCardTitle>Actions documentaires</VCardTitle>
      <VCardText>
        <VAlert
          v-if="errorMsg"
          type="error"
          class="mb-3"
        >
          {{ errorMsg }}
        </VAlert>
        <VAlert
          v-if="successMsg"
          type="success"
          class="mb-3"
        >
          {{ successMsg }}
        </VAlert>

        <VTabs v-model="tab">
          <VTab value="ged">
            GED
          </VTab>
          <VTab value="parapheur">
            Parapheur
          </VTab>
          <VTab value="reunion">
            Réunion
          </VTab>
          <VTab value="rdv">
            Rendez-vous
          </VTab>
          <VTab value="instruction">
            Instruction
          </VTab>
        </VTabs>

        <VTabsWindow
          v-model="tab"
          class="mt-4"
        >
          <VTabsWindowItem value="ged">
            <p class="text-body-2 mb-4">
              Transformer ce document de travail en document institutionnel GED (même fichier, changement de contexte).
            </p>
            <VBtn
              color="primary"
              :loading="saving"
              @click="submitGed"
            >
              Envoyer vers la GED
            </VBtn>
          </VTabsWindowItem>

          <VTabsWindowItem value="parapheur">
            <VTextField
              v-model="parapheur.object"
              label="Objet"
              class="mb-3"
            />
            <VSelect
              v-model="parapheur.recipient_ids"
              :items="users.map((u: any) => ({ title: u.name || u.email, value: u.id }))"
              label="Destinataires"
              multiple
              chips
              class="mb-3"
            />
            <VSelect
              v-model="parapheur.workflow_id"
              :items="[{ title: '— Transmission libre —', value: null }, ...workflows.map((w: any) => ({ title: w.name, value: w.id }))]"
              label="Circuit (optionnel)"
              class="mb-3"
            />
            <VSelect
              v-model="parapheur.expected_action"
              :items="[
                { title: 'Visa', value: 'visa' },
                { title: 'Validation', value: 'validation' },
                { title: 'Consultation', value: 'consultation' },
                { title: 'Information', value: 'information' },
              ]"
              label="Action attendue"
              class="mb-3"
            />
            <VTextarea
              v-model="parapheur.message"
              label="Message"
              rows="2"
              class="mb-3"
            />
            <VBtn
              color="primary"
              :loading="saving"
              @click="submitParapheur"
            >
              Soumettre au parapheur
            </VBtn>
          </VTabsWindowItem>

          <VTabsWindowItem value="reunion">
            <VSelect
              v-model="meetingId"
              :items="meetings.map((m: any) => ({ title: m.title || m.object || m.reference, value: m.id }))"
              label="Réunion"
              class="mb-3"
            />
            <VBtn
              color="primary"
              :loading="saving"
              @click="attachMeeting"
            >
              Lier à la réunion
            </VBtn>
          </VTabsWindowItem>

          <VTabsWindowItem value="rdv">
            <VSelect
              v-model="appointmentId"
              :items="appointments.map((a: any) => ({ title: a.subject || a.reference, value: a.id }))"
              label="Rendez-vous"
              class="mb-3"
            />
            <VBtn
              color="primary"
              :loading="saving"
              @click="attachAppointment"
            >
              Ajouter au dossier RDV
            </VBtn>
          </VTabsWindowItem>

          <VTabsWindowItem value="instruction">
            <VSelect
              v-model="instructionId"
              :items="instructions.map((i: any) => ({ title: i.title || `#${i.id}`, value: i.id }))"
              label="Instruction"
              class="mb-3"
            />
            <VBtn
              color="primary"
              :loading="saving"
              @click="attachInstruction"
            >
              Associer à l’instruction
            </VBtn>
          </VTabsWindowItem>
        </VTabsWindow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="open = false"
        >
          Fermer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
