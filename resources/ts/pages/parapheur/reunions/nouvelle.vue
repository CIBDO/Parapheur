<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { confidentialityOptions, priorityOptions } from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'create',
    subject: 'Meeting',
    navActiveLink: 'parapheur-reunions',
  },
})

const router = useRouter()
const busy = ref(false)
const errorMessage = ref('')
const users = ref<any[]>([])
const types = ref<any[]>([])
const structures = ref<any[]>([])
const documents = ref<any[]>([])
const meetings = ref<any[]>([])

const externalDraft = ref({
  external_name: '',
  external_function: '',
  external_structure: '',
  email: '',
  phone: '',
})

const form = ref({
  object: '',
  description: '',
  meeting_type_id: null as number | null,
  meeting_date: '',
  meeting_time: '10:00',
  end_time: '12:00',
  location: '',
  visio_url: '',
  chair_id: null as number | null,
  secretary_id: null as number | null,
  structure_id: null as number | null,
  confidentiality: 'normal',
  priority: 'normale',
  observations: '',
  participant_ids: [] as number[],
  external_participants: [] as Array<{
    participation_type: 'externe'
    external_name: string
    external_function?: string
    external_structure?: string
    email: string
    phone?: string
  }>,
  document_ids: [] as number[],
  parent_meeting_id: null as number | null,
  is_recurring: false,
  recurrence: {
    frequency: 'weekly',
    interval: 1,
    weekday: null as number | null,
    ends_on: '',
    occurrences_limit: null as number | null,
  },
  agenda_items: [
    { title: 'Adoption du compte rendu précédent' },
    { title: '' },
  ] as Array<{ title: string; duration_minutes?: number | null }>,
})

onMounted(async () => {
  const [people, typeList, structs, docs, list] = await Promise.all([
    $api('/meta/users'),
    $api('/meetings/types'),
    $api('/meta/structures'),
    $api('/ged/documents', { query: { per_page: 50 } }).catch(() => ({ data: [] })),
    $api('/meetings', { query: { per_page: 50 } }),
  ])
  users.value = people
  types.value = typeList
  structures.value = structs
  documents.value = docs.data ?? docs
  meetings.value = list.data ?? list
})

const addAgendaLine = () => {
  form.value.agenda_items.push({ title: '' })
}

const removeAgendaLine = (index: number) => {
  form.value.agenda_items.splice(index, 1)
}

const addExternalParticipant = () => {
  const draft = externalDraft.value
  if (!draft.external_name.trim() || !draft.email.trim()) {
    errorMessage.value = 'Pour un invité externe, le nom et l’e-mail sont obligatoires.'

    return
  }
  if (form.value.external_participants.some(p => p.email.toLowerCase() === draft.email.trim().toLowerCase())) {
    errorMessage.value = 'Cet e-mail externe est déjà dans la liste.'

    return
  }
  form.value.external_participants.push({
    participation_type: 'externe',
    external_name: draft.external_name.trim(),
    external_function: draft.external_function.trim() || undefined,
    external_structure: draft.external_structure.trim() || undefined,
    email: draft.email.trim(),
    phone: draft.phone.trim() || undefined,
  })
  externalDraft.value = {
    external_name: '',
    external_function: '',
    external_structure: '',
    email: '',
    phone: '',
  }
  errorMessage.value = ''
}

const removeExternalParticipant = (index: number) => {
  form.value.external_participants.splice(index, 1)
}

const submit = async () => {
  busy.value = true
  errorMessage.value = ''
  try {
    const participants = [
      ...form.value.participant_ids.map(userId => ({
        participation_type: 'interne' as const,
        user_id: userId,
      })),
      ...form.value.external_participants,
    ]

    const payload: Record<string, unknown> = {
      ...form.value,
      participants,
      agenda_items: form.value.agenda_items.filter(i => i.title.trim()),
    }
    delete payload.participant_ids
    delete payload.external_participants
    if (!form.value.is_recurring) {
      delete payload.recurrence
    }
    else {
      const recurrence: Record<string, unknown> = {
        frequency: form.value.recurrence.frequency,
        interval: form.value.recurrence.interval || 1,
      }
      if (form.value.recurrence.weekday)
        recurrence.weekday = form.value.recurrence.weekday
      if (form.value.recurrence.ends_on)
        recurrence.ends_on = form.value.recurrence.ends_on
      if (form.value.recurrence.occurrences_limit)
        recurrence.occurrences_limit = form.value.recurrence.occurrences_limit
      payload.recurrence = recurrence
    }

    const created = await $api('/meetings', { method: 'POST', body: payload })
    await router.push({ name: 'parapheur-reunions-id', params: { id: created.id } })
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || e?.message || 'Création impossible'
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouvelle réunion"
      subtitle="Planification — le dossier électronique sera constitué automatiquement"
      icon="tabler-calendar-plus"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :to="{ name: 'parapheur-reunions' }"
        >
          Annuler
        </VBtn>
        <VBtn
          color="primary"
          :loading="busy"
          prepend-icon="tabler-check"
          @click="submit"
        >
          Enregistrer
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <div class="parapheur-form-section">
      <div class="parapheur-form-section__title">
        <VIcon
          icon="tabler-info-circle"
          color="primary"
          size="20"
        />
        Informations générales
      </div>
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <AppTextField
            v-model="form.object"
            label="Intitulé / objet"
            class="mb-3"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppSelect
            v-model="form.meeting_type_id"
            :items="types"
            item-title="name"
            item-value="id"
            label="Type de réunion"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="form.description"
            label="Description"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="form.meeting_date"
            type="date"
            label="Date"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppTextField
            v-model="form.meeting_time"
            type="time"
            label="Début"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppTextField
            v-model="form.end_time"
            type="time"
            label="Fin prévisionnelle"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="form.location"
            label="Lieu"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            v-model="form.visio_url"
            label="Lien visioconférence (optionnel)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure organisatrice"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <UserAutocomplete
            v-model="form.chair_id"
            :items="users"
            label="Président"
            placeholder="Rechercher…"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <UserAutocomplete
            v-model="form.secretary_id"
            :items="users"
            label="Secrétaire de séance"
            placeholder="Rechercher…"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppSelect
            v-model="form.confidentiality"
            :items="confidentialityOptions"
            label="Confidentialité"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppSelect
            v-model="form.priority"
            :items="priorityOptions"
            label="Priorité"
          />
        </VCol>
      </VRow>
    </div>

    <div class="parapheur-form-section">
      <div class="parapheur-form-section__title">
        <VIcon
          icon="tabler-users"
          color="primary"
          size="20"
        />
        Participants
      </div>

      <div class="text-subtitle-2 mb-2">
        Internes (utilisateurs E-Tresor)
      </div>
      <UserAutocomplete
        v-model="form.participant_ids"
        :items="users"
        label="Sélectionner des agents"
        placeholder="Rechercher un agent…"
        multiple
        chips
        class="mb-6"
      />

      <div class="text-subtitle-2 mb-2">
        Externes (invités hors plateforme)
      </div>
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="externalDraft.external_name"
            label="Nom et prénoms *"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="externalDraft.email"
            type="email"
            label="E-mail *"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="externalDraft.phone"
            label="Téléphone"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="externalDraft.external_function"
            label="Fonction"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="externalDraft.external_structure"
            label="Structure / organisme"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="d-flex align-center"
        >
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-plus"
            @click="addExternalParticipant"
          >
            Ajouter l’invité
          </VBtn>
        </VCol>
      </VRow>

      <VList
        v-if="form.external_participants.length"
        class="mt-2"
        lines="two"
      >
        <VListItem
          v-for="(guest, index) in form.external_participants"
          :key="guest.email"
        >
          <template #prepend>
            <VChip
              size="small"
              color="warning"
              variant="tonal"
              class="me-3"
            >
              Externe
            </VChip>
          </template>
          <VListItemTitle>{{ guest.external_name }}</VListItemTitle>
          <VListItemSubtitle>
            {{ guest.email }}
            <span v-if="guest.external_structure"> · {{ guest.external_structure }}</span>
            <span v-if="guest.external_function"> · {{ guest.external_function }}</span>
          </VListItemSubtitle>
          <template #append>
            <VBtn
              icon
              variant="text"
              size="small"
              @click="removeExternalParticipant(index)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
        </VListItem>
      </VList>
    </div>

    <div class="parapheur-form-section">
      <div class="d-flex justify-space-between align-center mb-3">
        <div class="parapheur-form-section__title mb-0">
          <VIcon
            icon="tabler-list"
            color="primary"
            size="20"
          />
          Ordre du jour
        </div>
        <VBtn
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="addAgendaLine"
        >
          Point
        </VBtn>
      </div>
      <div
        v-for="(item, index) in form.agenda_items"
        :key="index"
        class="d-flex gap-2 mb-2"
      >
        <AppTextField
          v-model="item.title"
          :label="`Point ${index + 1}`"
        />
        <VBtn
          icon
          variant="text"
          @click="removeAgendaLine(index)"
        >
          <VIcon icon="tabler-trash" />
        </VBtn>
      </div>
    </div>

    <div class="parapheur-form-section">
      <div class="parapheur-form-section__title">
        <VIcon
          icon="tabler-folders"
          color="primary"
          size="20"
        />
        Documents existants
      </div>
      <AppSelect
        v-model="form.document_ids"
        :items="documents"
        :item-title="(i: any) => `${i.reference || i.id} — ${i.object}`"
        item-value="id"
        label="Rattacher des documents du parapheur"
        multiple
        chips
      />
    </div>

    <div class="parapheur-form-section">
      <VSwitch
        v-model="form.is_recurring"
        label="Réunion récurrente"
        color="primary"
      />
      <VRow v-if="form.is_recurring">
        <VCol
          cols="12"
          md="3"
        >
          <AppSelect
            v-model="form.recurrence.frequency"
            :items="[
              { value: 'daily', title: 'Quotidienne' },
              { value: 'weekly', title: 'Hebdomadaire' },
              { value: 'monthly', title: 'Mensuelle' },
            ]"
            label="Fréquence"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppTextField
            v-model.number="form.recurrence.interval"
            type="number"
            label="Intervalle"
            hint="Ex. 2 = toutes les 2 semaines"
            persistent-hint
          />
        </VCol>
        <VCol
          v-if="form.recurrence.frequency === 'weekly'"
          cols="12"
          md="3"
        >
          <AppSelect
            v-model="form.recurrence.weekday"
            :items="[
              { value: 1, title: 'Lundi' },
              { value: 2, title: 'Mardi' },
              { value: 3, title: 'Mercredi' },
              { value: 4, title: 'Jeudi' },
              { value: 5, title: 'Vendredi' },
              { value: 6, title: 'Samedi' },
              { value: 7, title: 'Dimanche' },
            ]"
            label="Jour de la semaine"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppTextField
            v-model="form.recurrence.ends_on"
            type="date"
            label="Fin le"
          />
        </VCol>
        <VCol
          cols="12"
          md="2"
        >
          <AppTextField
            v-model.number="form.recurrence.occurrences_limit"
            type="number"
            label="Nb occurrences"
            hint="2 à 26 — pré-génère la série"
            persistent-hint
          />
        </VCol>
      </VRow>
      <p
        v-if="form.is_recurring"
        class="text-caption text-medium-emphasis mt-2 mb-0"
      >
        Avec une date de fin ou un nombre d’occurrences, les prochaines réunions sont créées immédiatement.
        Sinon, la suivante est générée à la clôture / fin de séance.
      </p>
      <AppSelect
        v-model="form.parent_meeting_id"
        :items="meetings"
        :item-title="(i: any) => `${i.reference || ''} ${i.object || i.title}`"
        item-value="id"
        label="Réunion précédente (suivi des décisions)"
        clearable
        class="mt-3"
      />
    </div>
  </div>
</template>
