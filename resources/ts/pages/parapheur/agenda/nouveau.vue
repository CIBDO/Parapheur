<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Appointment',
  },
})

const ability = useAbility()
const router = useRouter()
const types = ref<any[]>([])
const users = ref<any[]>([])
const saving = ref(false)
const error = ref('')
const conflicts = ref<any[]>([])
const suggestions = ref<any>(null)

const form = ref({
  as_request: !ability.can('manage', 'Appointment'),
  subject: '',
  reason: '',
  description: '',
  appointment_type_id: null as number | null,
  priority: 'normale',
  confidentiality: 'normal',
  meeting_mode: 'presentiel',
  location: 'Cabinet DG',
  start_at: '',
  duration_minutes: 30,
  requested_date: '',
  intake_channel: 'saisie_secretariat',
  origin_type: 'secretariat',
  requester_name: '',
  requester_organization: '',
  requester_position: '',
  requester_email: '',
  requester_phone: '',
  context_note: '',
  points_to_discuss: '',
  participant_ids: [] as number[],
  direct_schedule: false,
  force: false,
})

const loadMeta = async () => {
  const [t, u] = await Promise.all([
    $api('/appointments/types'),
    $api('/meta/users'),
  ])
  types.value = t
  users.value = u
  if (t[0])
    form.value.appointment_type_id = t[0].id
}

const submit = async () => {
  saving.value = true
  error.value = ''
  conflicts.value = []
  try {
    const body: Record<string, unknown> = { ...form.value }
    if (form.value.start_at)
      body.start_at = new Date(form.value.start_at).toISOString()
    const created = await $api('/appointments', { method: 'POST', body })
    await router.push({ name: 'parapheur-agenda-id', params: { id: created.id } })
  }
  catch (e: any) {
    error.value = e?.data?.message || e?.message || 'Enregistrement impossible'
    conflicts.value = e?.data?.conflicts || []
    suggestions.value = e?.data?.suggestions || null
  }
  finally {
    saving.value = false
  }
}

onMounted(loadMeta)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau rendez-vous"
      subtitle="Saisie secrétariat ou demande en ligne interne"
      icon="tabler-calendar-plus"
    />

    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
      variant="tonal"
    >
      {{ error }}
      <ul
        v-if="conflicts.length"
        class="mt-2 mb-0"
      >
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

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <VTextField
              v-model="form.subject"
              label="Objet *"
              class="mb-3"
            />
            <VTextarea
              v-model="form.reason"
              label="Motif"
              rows="2"
              class="mb-3"
            />
            <VTextarea
              v-model="form.description"
              label="Description"
              rows="3"
              class="mb-3"
            />
            <VTextarea
              v-model="form.context_note"
              label="Note de contexte"
              rows="3"
              class="mb-3"
            />
            <VTextarea
              v-model="form.points_to_discuss"
              label="Points à aborder"
              rows="3"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="form.appointment_type_id"
              :items="types"
              item-title="name"
              item-value="id"
              label="Type"
              class="mb-3"
            />
            <VSelect
              v-model="form.priority"
              :items="[
                { title: 'Normale', value: 'normale' },
                { title: 'Importante', value: 'importante' },
                { title: 'Urgente', value: 'urgente' },
                { title: 'Très urgente', value: 'tres_urgente' },
              ]"
              label="Priorité"
              class="mb-3"
            />
            <VSelect
              v-model="form.confidentiality"
              :items="[
                { title: 'Normal', value: 'normal' },
                { title: 'Restreint', value: 'restreint' },
                { title: 'Confidentiel', value: 'confidentiel' },
                { title: 'Très confidentiel', value: 'tres_confidentiel' },
              ]"
              label="Confidentialité"
              class="mb-3"
            />
            <VSelect
              v-model="form.meeting_mode"
              :items="[
                { title: 'Présentiel', value: 'presentiel' },
                { title: 'Visioconférence', value: 'visioconference' },
                { title: 'Téléphone', value: 'telephone' },
                { title: 'Hybride', value: 'hybride' },
                { title: 'Externe', value: 'externe' },
              ]"
              label="Mode"
              class="mb-3"
            />
            <VTextField
              v-model="form.location"
              label="Lieu"
              class="mb-3"
            />
            <VTextField
              v-model="form.duration_minutes"
              type="number"
              label="Durée (min)"
              class="mb-3"
            />
            <VTextField
              v-model="form.start_at"
              type="datetime-local"
              label="Créneau retenu"
              class="mb-3"
            />
            <VTextField
              v-model="form.requested_date"
              type="date"
              label="Date souhaitée"
              class="mb-3"
            />
            <VSwitch
              v-if="ability.can('manage', 'Appointment')"
              v-model="form.direct_schedule"
              label="Planification directe (confirmée)"
              class="mb-2"
            />
            <VSwitch
              v-model="form.force"
              label="Forcer malgré conflit"
              color="warning"
            />
          </VCol>
        </VRow>

        <VDivider class="my-4" />
        <div class="text-subtitle-1 mb-3">
          Demandeur
        </div>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.requester_name"
              label="Nom"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.requester_organization"
              label="Organisme"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.requester_position"
              label="Fonction"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.requester_email"
              label="E-mail"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.requester_phone"
              label="Téléphone"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="form.intake_channel"
              :items="[
                { title: 'Saisie secrétariat', value: 'saisie_secretariat' },
                { title: 'Téléphone', value: 'telephone' },
                { title: 'Courrier', value: 'courrier' },
                { title: 'E-mail', value: 'email' },
                { title: 'Passage physique', value: 'passage' },
                { title: 'Instruction DG', value: 'instruction_dg' },
                { title: 'En ligne', value: 'en_ligne' },
              ]"
              label="Canal de réception"
            />
          </VCol>
        </VRow>

        <VSelect
          v-model="form.participant_ids"
          :items="users"
          item-title="name"
          item-value="id"
          label="Participants internes DGTCP"
          multiple
          chips
          class="mt-4"
        />

        <div class="d-flex justify-end gap-2 mt-6">
          <VBtn
            variant="tonal"
            :to="{ name: 'parapheur-agenda' }"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!form.subject"
            @click="submit"
          >
            Enregistrer
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
