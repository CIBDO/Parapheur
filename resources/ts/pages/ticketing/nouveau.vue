<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import { formatTicketNumber, listItems, teamAgentSelectItems } from '@/utils/ticketingUi'

definePage({
  meta: {
    layout: 'default',
    action: 'create',
    subject: 'Ticketing',
  },
})

const router = useRouter()
const {
  fetchMeta,
  createTicket,
  duplicateCheck,
  uploadAttachment,
  loading,
} = useTicketing()

const step = ref(1)
const showMore = ref(false)
const impacts = ref<any[]>([])
const urgencies = ref<any[]>([])
const categories = ref<any[]>([])
const types = ref<any[]>([])
const tags = ref<any[]>([])
const applications = ref<any[]>([])
const assets = ref<any[]>([])
const users = ref<any[]>([])
const teams = ref<any[]>([])
const matrix = ref<any[]>([])
const duplicates = ref<any[]>([])
const errorMsg = ref('')
const submitLoading = ref(false)
const file = ref<File | null>(null)

const form = ref({
  title: '',
  description: '',
  ticket_type_id: null as number | null,
  ticket_category_id: null as number | null,
  impact_id: null as number | null,
  urgency_id: null as number | null,
  application_id: null as number | null,
  asset_ids: [] as number[],
  tag_ids: [] as number[],
  requester_id: null as number | null,
  observer_ids: [] as number[],
  location_label: '',
  is_major_incident: false,
  support_team_id: null as number | null,
  assignee_id: null as number | null,
})

const steps = [
  { n: 1, label: 'Saisie' },
  { n: 2, label: 'Contrôle' },
]

const impactItems = computed(() => impacts.value.map(i => ({ value: i.id, title: i.name })))
const urgencyItems = computed(() => urgencies.value.map(u => ({ value: u.id, title: u.name })))
const typeItems = computed(() => types.value.map(t => ({ value: t.id, title: t.name })))
const tagItems = computed(() => tags.value.map(t => ({ value: t.id, title: t.name })))
const applicationItems = computed(() =>
  applications.value.map(a => ({ value: a.id, title: `${a.code} — ${a.name}` })),
)
const assetItems = computed(() =>
  assets.value.map(a => ({
    value: a.id,
    title: a.inventory_number ? `${a.name} (${a.inventory_number})` : a.name,
  })),
)
const userItems = computed(() => users.value.map(u => ({ value: u.id, title: u.name })))
const teamItems = computed(() => teams.value.map(t => ({ value: t.id, title: t.name })))
const agentItems = computed(() => teamAgentSelectItems(teams.value, form.value.support_team_id))

const selectedTeamLabel = computed(() =>
  teamItems.value.find(t => t.value === form.value.support_team_id)?.title || null,
)
const selectedAgentLabel = computed(() =>
  agentItems.value.find(a => a.value === Number(form.value.assignee_id))?.title || null,
)

const flatCategories = computed(() => {
  const out: any[] = []
  for (const cat of categories.value) {
    out.push({ value: cat.id, title: cat.name })
    for (const child of cat.children || [])
      out.push({ value: child.id, title: `— ${child.name}` })
  }

  return out
})

const resolvedPriority = computed(() => {
  if (!form.value.impact_id || !form.value.urgency_id)
    return null
  const row = matrix.value.find(
    (m: any) => m.impact_id === form.value.impact_id && m.urgency_id === form.value.urgency_id,
  )

  return row?.priority || null
})

const categoryLabel = computed(() =>
  flatCategories.value.find(c => c.value === form.value.ticket_category_id)?.title || '—',
)

const requesterLabel = computed(() => {
  if (!form.value.requester_id)
    return 'Moi-même'
  return userItems.value.find(u => u.value === form.value.requester_id)?.title || '—'
})

watch(() => form.value.support_team_id, () => {
  if (!form.value.assignee_id)
    return
  if (!agentItems.value.some(a => a.value === Number(form.value.assignee_id)))
    form.value.assignee_id = null
})

onMounted(async () => {
  try {
    const [meta, apps, ast, metaUsers, priorityMatrix] = await Promise.all([
      fetchMeta(),
      $api('/ticketing/applications', { query: { per_page: 100, is_active: 1 } }).catch(() => null),
      $api('/ticketing/assets', { query: { per_page: 100 } }).catch(() => null),
      $api('/meta/users', { query: { per_page: 200 } }).catch(() => null),
      $api('/ticketing/admin/priority-matrix').catch(() => null),
    ])
    impacts.value = meta?.impacts || []
    urgencies.value = meta?.urgencies || []
    categories.value = meta?.categories || []
    types.value = meta?.types || []
    tags.value = meta?.tags || []
    teams.value = meta?.teams || []
    applications.value = listItems(apps)
    assets.value = listItems(ast)
    users.value = listItems(metaUsers)
    matrix.value = Array.isArray(priorityMatrix) ? priorityMatrix : (priorityMatrix?.data || [])

    const incident = types.value.find((t: any) => t.code === 'INCIDENT')
    if (incident)
      form.value.ticket_type_id = incident.id
  }
  catch (e) {
    console.error(e)
  }
})

watch(() => form.value.title, async (title) => {
  if (!title || title.length < 5 || step.value !== 1)
    return
  try {
    const res = await duplicateCheck({
      title,
      requester_id: form.value.requester_id || undefined,
    })
    duplicates.value = res?.duplicates || []
  }
  catch {
    duplicates.value = []
  }
})

function goConfirm() {
  errorMsg.value = ''
  if (!form.value.title.trim()) {
    errorMsg.value = 'Indiquez un titre pour l’incident.'

    return
  }
  step.value = 2
}

async function submit() {
  errorMsg.value = ''
  if (form.value.assignee_id
    && !agentItems.value.some(a => a.value === Number(form.value.assignee_id))) {
    errorMsg.value = 'Choisissez un agent membre de l’équipe sélectionnée.'

    return
  }
  submitLoading.value = true
  try {
    const created = await createTicket({
      title: form.value.title,
      description: form.value.description || null,
      ticket_type_id: form.value.ticket_type_id,
      ticket_category_id: form.value.ticket_category_id,
      impact_id: form.value.impact_id,
      urgency_id: form.value.urgency_id,
      application_id: form.value.application_id,
      asset_ids: form.value.asset_ids,
      tag_ids: form.value.tag_ids,
      requester_id: form.value.requester_id,
      observer_ids: form.value.observer_ids,
      location_label: form.value.location_label || null,
      is_major_incident: form.value.is_major_incident,
      support_team_id: form.value.support_team_id ? Number(form.value.support_team_id) : null,
      assignee_id: form.value.assignee_id ? Number(form.value.assignee_id) : null,
    })
    const uploadFile = Array.isArray(file.value) ? file.value[0] : file.value
    if (uploadFile && created?.id) {
      try {
        await uploadAttachment(created.id, uploadFile)
      }
      catch (e) {
        console.error(e)
      }
    }
    await router.push({ name: 'ticketing-id', params: { id: created.id } })
  }
  catch (error: any) {
    const errors = error?.data?.errors
    if (errors && typeof errors === 'object')
      errorMsg.value = Object.values(errors).flat().join(' ')
    else
      errorMsg.value = error?.data?.message || error.message || 'Erreur lors de la création'
    step.value = 1
  }
  finally {
    submitLoading.value = false
  }
}
</script>

<template>
  <div class="ticketing-create">
    <ParapheurPageHeader
      title="Nouvel incident"
      subtitle="Décrivez le problème — nous classons et priorisons ensuite"
    >
      <template #actions>
        <VBtn
          variant="text"
          :to="{ name: 'ticketing-demande' }"
        >
          Plutôt une demande ?
        </VBtn>
        <VBtn
          variant="tonal"
          :to="{ name: 'ticketing' }"
        >
          Annuler
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <!-- Stepper léger -->
    <div class="create-steps mb-6">
      <div
        v-for="s in steps"
        :key="s.n"
        class="create-step"
        :class="{ active: step === s.n, done: step > s.n }"
      >
        <span class="create-step__num">{{ s.n }}</span>
        <span class="create-step__label">{{ s.label }}</span>
      </div>
    </div>

    <VAlert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>

    <!-- Étape 1 -->
    <VCard
      v-if="step === 1"
      elevation="0"
      border
    >
      <VCardText class="pa-6">
        <div class="text-h6 mb-1">
          Que se passe-t-il ?
        </div>
        <p class="text-body-2 text-medium-emphasis mb-6">
          Un bon titre et une description claire accélèrent la prise en charge.
        </p>

        <AppTextField
          v-model="form.title"
          label="Titre *"
          placeholder="Ex. Impossible de se connecter à SIGRAC"
          class="mb-4"
          hide-details="auto"
          autofocus
        />
        <AppTextarea
          v-model="form.description"
          label="Description"
          placeholder="Contexte, message d’erreur, depuis quand…"
          rows="4"
          class="mb-6"
          hide-details="auto"
        />

        <div class="text-subtitle-2 mb-3">
          Classification
        </div>
        <VRow dense>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.ticket_category_id"
              :items="flatCategories"
              label="Catégorie"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="form.impact_id"
              :items="impactItems"
              label="Impact"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="form.urgency_id"
              :items="urgencyItems"
              label="Urgence"
              clearable
              hide-details="auto"
            />
          </VCol>
        </VRow>

        <div
          v-if="resolvedPriority"
          class="d-flex align-center gap-2 mt-3"
        >
          <span class="text-caption text-medium-emphasis">Priorité calculée</span>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ resolvedPriority.name || resolvedPriority.code }}
          </VChip>
        </div>

        <div class="text-subtitle-2 mb-3 mt-6">
          Affectation
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          Qui traite le ticket (équipe / technicien). Optionnel — laissez vide pour affecter plus tard.
        </p>
        <VRow
          dense
          class="mb-2"
        >
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.support_team_id"
              :items="teamItems"
              label="Équipe"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.assignee_id"
              :items="agentItems"
              label="Agent"
              clearable
              :disabled="!form.support_team_id"
              :hint="form.support_team_id && !agentItems.length ? 'Aucun membre dans cette équipe — ajoutez-en via Admin ticketing.' : undefined"
              :persistent-hint="Boolean(form.support_team_id && !agentItems.length)"
              hide-details="auto"
            />
          </VCol>
        </VRow>

        <VAlert
          v-if="duplicates.length"
          type="warning"
          variant="tonal"
          class="mt-4"
          density="compact"
        >
          <div class="text-body-2 font-weight-medium mb-1">
            Tickets similaires
          </div>
          <div
            v-for="d in duplicates.slice(0, 3)"
            :key="d.id"
            class="text-body-2"
          >
            <RouterLink :to="{ name: 'ticketing-id', params: { id: d.id } }">
              {{ formatTicketNumber(d) }} — {{ d.title }}
            </RouterLink>
          </div>
        </VAlert>

        <VBtn
          variant="text"
          class="mt-4 px-0"
          :prepend-icon="showMore ? 'tabler-chevron-up' : 'tabler-chevron-down'"
          @click="showMore = !showMore"
        >
          {{ showMore ? 'Masquer les options' : 'Options (demandeur, actifs, pièce jointe…)' }}
        </VBtn>

        <VExpandTransition>
          <div v-show="showMore">
            <VDivider class="mb-4" />
            <VRow dense>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.requester_id"
                  :items="userItems"
                  label="Pour le compte de"
                  placeholder="Moi-même (laissez vide)"
                  hint="Laissez vide si c’est pour vous. Choisissez un collègue seulement si vous créez pour quelqu’un d’autre."
                  persistent-hint
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.observer_ids"
                  :items="userItems"
                  label="Observateurs"
                  multiple
                  chips
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.application_id"
                  :items="applicationItems"
                  label="Application"
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.asset_ids"
                  :items="assetItems"
                  label="Actifs"
                  multiple
                  chips
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.location_label"
                  label="Lieu"
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.tag_ids"
                  :items="tagItems"
                  label="Tags"
                  multiple
                  chips
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12">
                <VFileInput
                  v-model="file"
                  label="Pièce jointe"
                  prepend-icon="tabler-paperclip"
                  show-size
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12">
                <VCheckbox
                  v-model="form.is_major_incident"
                  label="Incident majeur (impact large)"
                  hide-details
                  density="compact"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>

        <div class="d-flex justify-end mt-8">
          <VBtn
            color="primary"
            size="large"
            @click="goConfirm"
          >
            Continuer
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Étape 2 -->
    <VCard
      v-else
      elevation="0"
      border
    >
      <VCardText class="pa-6">
        <div class="text-h6 mb-1">
          Vérifiez avant envoi
        </div>
        <p class="text-body-2 text-medium-emphasis mb-6">
          Un dernier regard — vous pourrez encore modifier.
        </p>

        <div class="summary-block mb-4">
          <div class="text-overline text-medium-emphasis">
            Titre
          </div>
          <div class="text-body-1 font-weight-medium">
            {{ form.title }}
          </div>
        </div>
        <div
          v-if="form.description"
          class="summary-block mb-4"
        >
          <div class="text-overline text-medium-emphasis">
            Description
          </div>
          <div class="text-body-2">
            {{ form.description }}
          </div>
        </div>

        <VRow dense>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Catégorie
            </div>
            <div class="text-body-2">
              {{ categoryLabel }}
            </div>
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Priorité
            </div>
            <div class="text-body-2">
              {{ resolvedPriority?.name || 'Non calculée' }}
            </div>
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Demandeur
            </div>
            <div class="text-body-2">
              {{ requesterLabel }}
            </div>
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Équipe
            </div>
            <div class="text-body-2">
              {{ selectedTeamLabel || '—' }}
            </div>
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Agent
            </div>
            <div class="text-body-2">
              {{ selectedAgentLabel || '—' }}
            </div>
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <div class="text-caption text-medium-emphasis">
              Lieu
            </div>
            <div class="text-body-2">
              {{ form.location_label || '—' }}
            </div>
          </VCol>
        </VRow>

        <div class="d-flex justify-space-between mt-8">
          <VBtn
            variant="tonal"
            @click="step = 1"
          >
            Modifier
          </VBtn>
          <VBtn
            color="primary"
            size="large"
            :loading="submitLoading || loading"
            @click="submit"
          >
            Créer l'incident
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.create-steps {
  display: flex;
  gap: 1.5rem;
  align-items: center;
}

.create-step {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.45);
}

.create-step__num {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  inline-size: 1.75rem;
  block-size: 1.75rem;
  border-radius: 50%;
  border: 2px solid currentcolor;
  font-size: 0.8rem;
  font-weight: 600;
}

.create-step.active {
  color: rgb(var(--v-theme-primary));
}

.create-step.active .create-step__num {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  border-color: transparent;
}

.create-step.done {
  color: rgb(var(--v-theme-primary));
}

.create-step__label {
  font-size: 0.875rem;
  font-weight: 500;
}

.summary-block {
  padding-block-end: 0.75rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
