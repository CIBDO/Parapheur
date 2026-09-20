<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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

const route = useRoute()
const router = useRouter()
const {
  fetchServiceCatalog,
  fetchServiceForm,
  fetchMeta,
  createTicket,
  duplicateCheck,
  uploadAttachment,
  loading,
} = useTicketing()

const step = ref(1)
const showMore = ref(false)
const catalogSearch = ref('')
const catalogs = ref<any[]>([])
const selectedItem = ref<any>(null)
const formFields = ref<any[]>([])
const customValues = ref<Record<string, any>>({})
const impacts = ref<any[]>([])
const urgencies = ref<any[]>([])
const applications = ref<any[]>([])
const assets = ref<any[]>([])
const users = ref<any[]>([])
const teams = ref<any[]>([])
const duplicates = ref<any[]>([])
const errorMsg = ref('')
const submitLoading = ref(false)
const file = ref<File | null>(null)

const form = ref({
  title: '',
  description: '',
  impact_id: null as number | null,
  urgency_id: null as number | null,
  application_id: null as number | null,
  asset_ids: [] as number[],
  requester_id: null as number | null,
  observer_ids: [] as number[],
  location_label: '',
  support_team_id: null as number | null,
  assignee_id: null as number | null,
})

const steps = [
  { n: 1, label: 'Service' },
  { n: 2, label: 'Détails' },
  { n: 3, label: 'Contrôle' },
]

const flatItems = computed(() => {
  const items: any[] = []
  for (const cat of catalogs.value) {
    for (const item of cat.items || []) {
      items.push({
        ...item,
        catalog_name: cat.name,
        catalog_id: cat.id,
      })
    }
  }

  return items
})

const filteredCatalogs = computed(() => {
  const q = catalogSearch.value.trim().toLowerCase()
  if (!q)
    return catalogs.value

  return catalogs.value
    .map(cat => ({
      ...cat,
      items: (cat.items || []).filter((i: any) =>
        String(i.name || '').toLowerCase().includes(q)
        || String(i.description || '').toLowerCase().includes(q),
      ),
    }))
    .filter(cat => cat.items.length || String(cat.name || '').toLowerCase().includes(q))
})

const impactItems = computed(() => impacts.value.map(i => ({ value: i.id, title: i.name })))
const urgencyItems = computed(() => urgencies.value.map(u => ({ value: u.id, title: u.name })))
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
const canAssignAtCreate = computed(() => !selectedItem.value?.requires_approval)
const agentItems = computed(() => teamAgentSelectItems(teams.value, form.value.support_team_id))

const selectedTeamLabel = computed(() =>
  teamItems.value.find(t => t.value === form.value.support_team_id)?.title || null,
)
const selectedAgentLabel = computed(() =>
  agentItems.value.find(a => a.value === Number(form.value.assignee_id))?.title || null,
)

watch(() => form.value.support_team_id, () => {
  if (!form.value.assignee_id)
    return
  if (!agentItems.value.some(a => a.value === Number(form.value.assignee_id)))
    form.value.assignee_id = null
})

onMounted(async () => {
  try {
    const [catalog, meta, apps, ast, metaUsers] = await Promise.all([
      fetchServiceCatalog(),
      fetchMeta(),
      $api('/ticketing/applications', { query: { per_page: 100, is_active: 1 } }).catch(() => null),
      $api('/ticketing/assets', { query: { per_page: 100 } }).catch(() => null),
      $api('/meta/users', { query: { per_page: 200 } }).catch(() => null),
    ])
    catalogs.value = listItems(catalog).length ? listItems(catalog) : (Array.isArray(catalog) ? catalog : [])
    impacts.value = meta?.impacts || []
    urgencies.value = meta?.urgencies || []
    teams.value = meta?.teams || []
    applications.value = listItems(apps)
    assets.value = listItems(ast)
    users.value = listItems(metaUsers)

    const preselectId = Number(route.query.service_item_id)
    if (preselectId) {
      const item = flatItems.value.find(i => i.id === preselectId)
      if (item)
        await selectService(item)
    }
  }
  catch (e) {
    console.error(e)
  }
})

async function selectService(item: any) {
  selectedItem.value = item
  customValues.value = {}
  errorMsg.value = ''
  showMore.value = false
  form.value.assignee_id = null
  form.value.support_team_id = item.support_team_id || item.support_team?.id || null
  try {
    const response = await fetchServiceForm(item.id)
    formFields.value = response?.fields || response?.item?.fields || []
    const resolved = response?.item || item
    selectedItem.value = {
      ...item,
      ...resolved,
      catalog_name: item.catalog_name || resolved?.catalog?.name,
    }
    form.value.support_team_id = resolved?.support_team_id
      || resolved?.support_team?.id
      || item.support_team_id
      || null
    form.value.title = item.name || resolved?.name || ''
    step.value = 2
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger le formulaire'
  }
}

watch(() => form.value.title, async (title) => {
  if (!title || title.length < 5 || step.value !== 2)
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

function fieldOptions(field: any) {
  const opts = field.options || field.config?.options || []
  if (Array.isArray(opts)) {
    return opts.map((o: any) => {
      if (typeof o === 'string')
        return { value: o, title: o }

      return { value: o.value ?? o.code ?? o.id, title: o.label ?? o.name ?? o.title ?? String(o.value) }
    })
  }

  return []
}

function goConfirm() {
  errorMsg.value = ''
  if (!form.value.title.trim()) {
    errorMsg.value = 'Indiquez un titre pour la demande.'

    return
  }
  for (const field of formFields.value) {
    if (!field.is_required)
      continue
    const val = customValues.value[field.code]
    if (val === null || val === undefined || val === '' || (Array.isArray(val) && !val.length)) {
      errorMsg.value = `Le champ « ${field.label} » est obligatoire.`
      showMore.value = true

      return
    }
  }
  step.value = 3
}

async function submit() {
  errorMsg.value = ''
  if (canAssignAtCreate.value
    && form.value.assignee_id
    && !agentItems.value.some(a => a.value === Number(form.value.assignee_id))) {
    errorMsg.value = 'Choisissez un agent membre de l’équipe sélectionnée.'
    step.value = 2

    return
  }
  submitLoading.value = true
  try {
    const created = await createTicket({
      title: form.value.title,
      description: form.value.description || null,
      service_item_id: selectedItem.value?.id,
      impact_id: form.value.impact_id,
      urgency_id: form.value.urgency_id,
      application_id: form.value.application_id,
      asset_ids: form.value.asset_ids,
      requester_id: form.value.requester_id,
      observer_ids: form.value.observer_ids,
      location_label: form.value.location_label || null,
      custom_fields: customValues.value,
      support_team_id: canAssignAtCreate.value && form.value.support_team_id
        ? Number(form.value.support_team_id)
        : null,
      assignee_id: canAssignAtCreate.value && form.value.assignee_id
        ? Number(form.value.assignee_id)
        : null,
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
    step.value = 2
  }
  finally {
    submitLoading.value = false
  }
}
</script>

<template>
  <div class="ticketing-create">
    <ParapheurPageHeader
      title="Nouvelle demande"
      subtitle="Choisissez un service du catalogue, puis complétez le formulaire"
    >
      <template #actions>
        <VBtn
          variant="text"
          :to="{ name: 'ticketing-nouveau' }"
        >
          Plutôt un incident ?
        </VBtn>
        <VBtn
          variant="tonal"
          :to="{ name: 'ticketing' }"
        >
          Annuler
        </VBtn>
      </template>
    </ParapheurPageHeader>

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

    <!-- Étape 1 : catalogue -->
    <div v-if="step === 1">
      <AppTextField
        v-model="catalogSearch"
        prepend-inner-icon="tabler-search"
        placeholder="Rechercher un service…"
        class="mb-4"
        hide-details
        style="max-inline-size: 420px"
      />

      <div
        v-if="!filteredCatalogs.length"
        class="text-center py-12 text-medium-emphasis"
      >
        Aucun service trouvé.
      </div>

      <div
        v-for="cat in filteredCatalogs"
        :key="cat.id"
        class="mb-6"
      >
        <div class="text-subtitle-2 text-medium-emphasis mb-3">
          {{ cat.name }}
        </div>
        <VRow dense>
          <VCol
            v-for="item in (cat.items || [])"
            :key="item.id"
            cols="12"
            sm="6"
            lg="4"
          >
            <button
              type="button"
              class="service-tile"
              @click="selectService({ ...item, catalog_name: cat.name })"
            >
              <div class="service-tile__title">
                {{ item.name }}
              </div>
              <div
                v-if="item.description"
                class="service-tile__desc"
              >
                {{ item.description }}
              </div>
              <VChip
                v-if="item.requires_approval"
                size="x-small"
                color="warning"
                variant="tonal"
                class="mt-2"
              >
                Approbation
              </VChip>
            </button>
          </VCol>
        </VRow>
      </div>
    </div>

    <!-- Étape 2 : formulaire -->
    <VCard
      v-else-if="step === 2"
      elevation="0"
      border
    >
      <VCardText class="pa-6">
        <div class="d-flex flex-wrap align-start justify-space-between gap-2 mb-6">
          <div>
            <div class="text-caption text-medium-emphasis">
              {{ selectedItem?.catalog_name }}
            </div>
            <div class="text-h6">
              {{ selectedItem?.name }}
            </div>
          </div>
          <VBtn
            variant="text"
            size="small"
            @click="step = 1"
          >
            Changer
          </VBtn>
        </div>

        <VAlert
          v-if="selectedItem?.requires_approval"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Cette demande sera soumise à validation avant traitement.
        </VAlert>

        <AppTextField
          v-model="form.title"
          label="Titre *"
          class="mb-4"
          hide-details="auto"
        />
        <AppTextarea
          v-model="form.description"
          label="Description"
          rows="3"
          class="mb-4"
          hide-details="auto"
        />

        <template v-if="canAssignAtCreate">
          <div class="text-subtitle-2 mb-3">
            Affectation
          </div>
          <VRow
            dense
            class="mb-4"
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
        </template>

        <template v-if="formFields.length">
          <div class="text-subtitle-2 mb-3">
            Informations du service
          </div>
          <VRow dense>
            <VCol
              v-for="field in formFields"
              :key="field.id || field.code"
              cols="12"
              :md="field.field_type === 'textarea' ? 12 : 6"
            >
              <AppTextarea
                v-if="field.field_type === 'textarea'"
                v-model="customValues[field.code]"
                :label="field.label + (field.is_required ? ' *' : '')"
                rows="3"
                hide-details="auto"
              />
              <AppSelect
                v-else-if="['select', 'radio'].includes(field.field_type)"
                v-model="customValues[field.code]"
                :items="fieldOptions(field)"
                :label="field.label + (field.is_required ? ' *' : '')"
                hide-details="auto"
              />
              <VCheckbox
                v-else-if="['boolean', 'checkbox'].includes(field.field_type)"
                v-model="customValues[field.code]"
                :label="field.label"
                hide-details
                density="compact"
              />
              <AppTextField
                v-else
                v-model="customValues[field.code]"
                :type="['number', 'integer'].includes(field.field_type) ? 'number' : 'text'"
                :label="field.label + (field.is_required ? ' *' : '')"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </template>

        <VAlert
          v-if="duplicates.length"
          type="warning"
          variant="tonal"
          class="mt-4"
          density="compact"
        >
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
          {{ showMore ? 'Masquer les options' : 'Options (priorité, actifs, pièce jointe…)' }}
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
                  v-model="form.impact_id"
                  :items="impactItems"
                  label="Impact"
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.urgency_id"
                  :items="urgencyItems"
                  label="Urgence"
                  clearable
                  hide-details="auto"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.requester_id"
                  :items="userItems"
                  label="Pour le compte de"
                  placeholder="Moi-même (laissez vide)"
                  hint="Laissez vide si c’est pour vous."
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
              <VCol cols="12">
                <VFileInput
                  v-model="file"
                  label="Pièce jointe"
                  prepend-icon="tabler-paperclip"
                  show-size
                  hide-details="auto"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>

        <div class="d-flex justify-space-between mt-8">
          <VBtn
            variant="tonal"
            @click="step = 1"
          >
            Retour
          </VBtn>
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

    <!-- Étape 3 -->
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
          Service : {{ selectedItem?.name }}
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
        <div
          v-if="canAssignAtCreate && selectedTeamLabel"
          class="summary-block mb-4"
        >
          <div class="text-overline text-medium-emphasis">
            Équipe
          </div>
          <div class="text-body-1">
            {{ selectedTeamLabel }}
          </div>
        </div>
        <div
          v-if="canAssignAtCreate && selectedAgentLabel"
          class="summary-block mb-4"
        >
          <div class="text-overline text-medium-emphasis">
            Agent
          </div>
          <div class="text-body-1">
            {{ selectedAgentLabel }}
          </div>
        </div>
        <div
          v-if="selectedItem?.requires_approval"
          class="summary-block mb-4"
        >
          <div class="text-overline text-medium-emphasis">
            Affectation
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Après validation du chef de service
          </div>
        </div>
        <div class="d-flex justify-space-between mt-8">
          <VBtn
            variant="tonal"
            @click="step = 2"
          >
            Modifier
          </VBtn>
          <VBtn
            color="primary"
            size="large"
            :loading="submitLoading || loading"
            @click="submit"
          >
            Créer la demande
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

.service-tile {
  display: block;
  inline-size: 100%;
  block-size: 100%;
  padding: 1rem 1.1rem;
  text-align: start;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.service-tile:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.service-tile__title {
  font-weight: 600;
  font-size: 0.95rem;
  margin-block-end: 0.25rem;
}

.service-tile__desc {
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.summary-block {
  padding-block-end: 0.75rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
