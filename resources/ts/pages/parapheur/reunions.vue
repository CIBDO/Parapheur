<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'Meeting',
  },
})

const meetings = ref<any[]>([])
const users = ref<any[]>([])
const documents = ref<any[]>([])
const dialog = ref(false)
const detail = ref<any>(null)
const decisionDialog = ref(false)
const busy = ref(false)

const form = ref({
  title: '',
  meeting_date: '',
  meeting_time: '',
  location: '',
  chair_id: null as number | null,
  agenda: '',
  participant_ids: [] as number[],
  document_ids: [] as number[],
})

const decisionForm = ref({
  title: '',
  body: '',
  assignee_id: null as number | null,
  due_date: '',
  create_instruction: true,
})

const load = async () => {
  const res = await $api('/meetings')
  meetings.value = res.data ?? res
}

onMounted(async () => {
  const [people, docs] = await Promise.all([
    $api('/meta/users'),
    $api('/parapheur/documents', { query: { folder: undefined } }).catch(() => ({ data: [] })),
  ])
  users.value = people
  documents.value = docs.data ?? docs
  await load()
})

const createMeeting = async () => {
  busy.value = true
  try {
    await $api('/meetings', { method: 'POST', body: form.value })
    dialog.value = false
    form.value = {
      title: '',
      meeting_date: '',
      meeting_time: '',
      location: '',
      chair_id: null,
      agenda: '',
      participant_ids: [],
      document_ids: [],
    }
    await load()
  }
  finally {
    busy.value = false
  }
}

const openDetail = async (id: number) => {
  detail.value = await $api(`/meetings/${id}`)
}

const saveDocuments = async () => {
  if (!detail.value)
    return
  busy.value = true
  try {
    detail.value = await $api(`/meetings/${detail.value.id}`, {
      method: 'PUT',
      body: { document_ids: selectedDocIds.value },
    })
  }
  finally {
    busy.value = false
  }
}

const openDecision = () => {
  decisionForm.value = {
    title: '',
    body: '',
    assignee_id: null,
    due_date: '',
    create_instruction: true,
  }
  decisionDialog.value = true
}

const addDecision = async () => {
  if (!detail.value)
    return
  busy.value = true
  try {
    await $api(`/meetings/${detail.value.id}/decisions`, {
      method: 'POST',
      body: decisionForm.value,
    })
    decisionDialog.value = false
    await openDetail(detail.value.id)
    await load()
  }
  finally {
    busy.value = false
  }
}

const selectedDocIds = computed({
  get: () => (detail.value?.documents || []).map((d: any) => d.id),
  set: (ids: number[]) => {
    if (!detail.value)
      return
    detail.value.documents = documents.value.filter((d: any) => ids.includes(d.id))
  },
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Réunions"
      subtitle="Dossiers de séance, participants et décisions"
      icon="tabler-users-group"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Nouvelle réunion
        </VBtn>
      </template>
    </ParapheurPageHeader>


    <VRow>
      <VCol
        v-for="meeting in meetings"
        :key="meeting.id"
        cols="12"
        md="6"
      >
        <VCard>
          <VCardTitle>{{ meeting.title }}</VCardTitle>
          <VCardSubtitle>
            {{ meeting.meeting_date }} {{ meeting.meeting_time || '' }} · {{ meeting.location || 'Lieu non précisé' }}
          </VCardSubtitle>
          <VCardText>
            <div class="mb-2">
              <strong>Président :</strong> {{ meeting.chair?.name || '—' }}
            </div>
            <div class="mb-2">
              <strong>Participants :</strong> {{ meeting.participants?.length || 0 }}
            </div>
            <div class="mb-2">
              <strong>Dossier de séance :</strong> {{ meeting.documents?.length || 0 }} document(s)
            </div>
            <div class="mb-2">
              <strong>Décisions :</strong> {{ meeting.decisions?.length || 0 }}
            </div>
            <div style="white-space: pre-wrap">
              {{ meeting.agenda }}
            </div>
          </VCardText>
          <VCardActions>
            <VBtn
              variant="tonal"
              @click="openDetail(meeting.id)"
            >
              Ouvrir
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="720"
    >
      <VCard>
        <VCardTitle>Créer une réunion</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="form.title"
            label="Objet"
            class="mb-3"
          />
          <AppTextField
            v-model="form.meeting_date"
            type="date"
            label="Date"
            class="mb-3"
          />
          <AppTextField
            v-model="form.meeting_time"
            type="time"
            label="Heure"
            class="mb-3"
          />
          <AppTextField
            v-model="form.location"
            label="Lieu"
            class="mb-3"
          />
          <AppSelect
            v-model="form.chair_id"
            :items="users"
            item-title="name"
            item-value="id"
            label="Président"
            class="mb-3"
          />
          <AppSelect
            v-model="form.participant_ids"
            :items="users"
            item-title="name"
            item-value="id"
            label="Participants"
            multiple
            chips
            class="mb-3"
          />
          <AppSelect
            v-model="form.document_ids"
            :items="documents"
            :item-title="(i: any) => `${i.reference || i.id} — ${i.object}`"
            item-value="id"
            label="Dossier de séance"
            multiple
            chips
            class="mb-3"
          />
          <AppTextarea
            v-model="form.agenda"
            label="Ordre du jour"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="createMeeting"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      :model-value="!!detail"
      max-width="860"
      @update:model-value="v => !v && (detail = null)"
    >
      <VCard v-if="detail">
        <VCardTitle>{{ detail.title }}</VCardTitle>
        <VCardText>
          <div class="mb-4 text-body-2">
            {{ detail.meeting_date }} · {{ detail.location || '—' }} · Président : {{ detail.chair?.name || '—' }}
          </div>

          <div class="text-subtitle-1 mb-2">
            Dossier de séance
          </div>
          <AppSelect
            v-model="selectedDocIds"
            :items="documents"
            :item-title="(i: any) => `${i.reference || i.id} — ${i.object}`"
            item-value="id"
            label="Documents"
            multiple
            chips
            class="mb-2"
          />
          <VBtn
            size="small"
            class="mb-6"
            :loading="busy"
            @click="saveDocuments"
          >
            Enregistrer le dossier
          </VBtn>

          <div class="d-flex justify-space-between align-center mb-2">
            <div class="text-subtitle-1">
              Décisions
            </div>
            <VBtn
              size="small"
              color="primary"
              @click="openDecision"
            >
              Ajouter une décision
            </VBtn>
          </div>
          <VList
            v-if="detail.decisions?.length"
            lines="two"
          >
            <VListItem
              v-for="d in detail.decisions"
              :key="d.id"
            >
              <VListItemTitle>{{ d.title }}</VListItemTitle>
              <VListItemSubtitle>
                {{ d.assignee?.name || 'Sans responsable' }}
                <span v-if="d.due_date"> · échéance {{ d.due_date }}</span>
                <span v-if="d.instruction"> · instruction #{{ d.instruction.id }}</span>
              </VListItemSubtitle>
            </VListItem>
          </VList>
          <div
            v-else
            class="text-medium-emphasis"
          >
            Aucune décision
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="detail = null">
            Fermer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="decisionDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Nouvelle décision</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="decisionForm.title"
            label="Titre"
            class="mb-3"
          />
          <AppTextarea
            v-model="decisionForm.body"
            label="Décision"
            class="mb-3"
          />
          <AppSelect
            v-model="decisionForm.assignee_id"
            :items="users"
            item-title="name"
            item-value="id"
            label="Responsable du suivi"
            class="mb-3"
          />
          <AppTextField
            v-model="decisionForm.due_date"
            type="date"
            label="Échéance"
            class="mb-3"
          />
          <VSwitch
            v-model="decisionForm.create_instruction"
            label="Créer une instruction de suivi"
            color="primary"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="decisionDialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="addDecision"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
