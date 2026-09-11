<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { attendanceLabels, decisionStatusLabels } from '@/utils/meetingsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
    navActiveLink: 'parapheur-reunions',
  },
})

const route = useRoute()
const id = computed(() => Number(route.params.id))
const meeting = ref<any>(null)
const busy = ref(false)
const noteBody = ref('')
const decisionTitle = ref('')

const currentIndex = computed(() => {
  const items = meeting.value?.agenda_items || []
  const currentId = meeting.value?.current_agenda_item_id
  const idx = items.findIndex((i: any) => i.id === currentId)

  return idx < 0 ? 0 : idx
})

const currentItem = computed(() => (meeting.value?.agenda_items || [])[currentIndex.value] || null)

const load = async () => {
  meeting.value = await $api(`/meetings/${id.value}`)
}

const run = async (fn: () => Promise<unknown>) => {
  busy.value = true
  try {
    await fn()
    await load()
  }
  finally {
    busy.value = false
  }
}

const setCurrent = (itemId: number) => run(() =>
  $api(`/meetings/${id.value}/agenda/${itemId}/current`, { method: 'POST' }),
)

const prev = () => {
  const items = meeting.value?.agenda_items || []
  const next = items[currentIndex.value - 1]
  if (next)
    setCurrent(next.id)
}

const next = () => {
  const items = meeting.value?.agenda_items || []
  const following = items[currentIndex.value + 1]
  if (following)
    setCurrent(following.id)
}

const addOfficialNote = () => run(async () => {
  if (!noteBody.value)
    return
  await $api(`/meetings/${id.value}/notes`, {
    method: 'POST',
    body: {
      visibility: 'officielle',
      section: 'resume',
      agenda_item_id: currentItem.value?.id,
      body: noteBody.value,
    },
  })
  noteBody.value = ''
})

const addDecision = () => run(async () => {
  if (!decisionTitle.value)
    return
  await $api(`/meetings/${id.value}/decisions`, {
    method: 'POST',
    body: {
      title: decisionTitle.value,
      agenda_item_id: currentItem.value?.id,
      create_instruction: false,
    },
  })
  decisionTitle.value = ''
})

const finish = () => run(() => $api(`/meetings/${id.value}/transition`, { method: 'POST', body: { status: 'terminee' } }))

const setAttendance = (participantId: number, status: string) => run(() =>
  $api(`/meetings/${id.value}/participants/${participantId}/attendance`, {
    method: 'POST',
    body: { attendance_status: status },
  }),
)

onMounted(load)
</script>

<template>
  <div v-if="meeting">
    <ParapheurPageHeader
      :title="meeting.object || meeting.title"
      :subtitle="`Mode séance · point ${currentIndex + 1} / ${(meeting.agenda_items || []).length}`"
      icon="tabler-player-play"
    >
      <template #actions>
        <VChip
          color="info"
          variant="tonal"
        >
          {{ meeting.status_label }}
        </VChip>
        <VBtn
          variant="tonal"
          :to="{ name: 'parapheur-reunions-id', params: { id: meeting.id } }"
        >
          Fiche
        </VBtn>
        <VBtn
          color="error"
          :loading="busy"
          @click="finish"
        >
          Terminer
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="mb-4">
          <VCardText>
            <div class="text-caption text-medium-emphasis mb-1">
              POINT {{ currentIndex + 1 }} / {{ (meeting.agenda_items || []).length }}
            </div>
            <h4 class="text-h4 mb-2">
              {{ currentItem?.title }}
            </h4>
            <p class="text-medium-emphasis">
              Présenté par : {{ currentItem?.presenter?.name || '—' }}
            </p>
            <div class="d-flex gap-2">
              <VBtn
                variant="tonal"
                :disabled="currentIndex === 0"
                @click="prev"
              >
                Point précédent
              </VBtn>
              <VBtn
                color="primary"
                :disabled="currentIndex >= (meeting.agenda_items || []).length - 1"
                @click="next"
              >
                Point suivant
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-4">
          <VCardTitle>Documents du point</VCardTitle>
          <VList>
            <VListItem
              v-for="link in (meeting.documents || []).filter((d: any) => !d.agenda_item_id || d.agenda_item_id === currentItem?.id)"
              :key="link.id"
              :to="link.document?.id ? { name: 'parapheur-id', params: { id: link.document.id } } : undefined"
            >
              <VListItemTitle>{{ link.document?.object }}</VListItemTitle>
              <VListItemSubtitle>{{ link.document?.reference }}</VListItemSubtitle>
            </VListItem>
          </VList>
        </VCard>

        <VCard class="mb-4">
          <VCardTitle>Notes officielles</VCardTitle>
          <VCardText>
            <AppTextarea
              v-model="noteBody"
              label="Synthèse des échanges"
              class="mb-3"
            />
            <VBtn
              color="primary"
              :loading="busy"
              @click="addOfficialNote"
            >
              Enregistrer
            </VBtn>
          </VCardText>
        </VCard>

        <VCard>
          <VCardTitle>Décisions</VCardTitle>
          <VCardText>
            <AppTextField
              v-model="decisionTitle"
              label="Nouvelle décision"
              class="mb-3"
            />
            <VBtn
              color="primary"
              class="mb-4"
              :loading="busy"
              @click="addDecision"
            >
              Ajouter
            </VBtn>
            <VList>
              <VListItem
                v-for="d in (meeting.decisions || []).filter((x: any) => !x.agenda_item_id || x.agenda_item_id === currentItem?.id)"
                :key="d.id"
              >
                <VListItemTitle>{{ d.title }}</VListItemTitle>
                <VListItemSubtitle>{{ decisionStatusLabels[d.status] || d.status }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardTitle>Présences</VCardTitle>
          <VList>
            <VListItem
              v-for="p in meeting.participants"
              :key="p.id"
            >
              <VListItemTitle>{{ p.user?.name || p.external_name || p.email }}</VListItemTitle>
              <VListItemSubtitle>
                {{ (p.participation_type === 'externe' || (!p.user_id && p.external_name)) ? 'Externe' : 'Interne' }}
                · {{ attendanceLabels[p.attendance_status] || 'Non renseigné' }}
              </VListItemSubtitle>
              <template #append>
                <VBtn
                  size="x-small"
                  variant="tonal"
                  @click="setAttendance(p.id, 'present')"
                >
                  Présent
                </VBtn>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
