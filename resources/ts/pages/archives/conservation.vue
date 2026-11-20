<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'manage', subject: 'ArchivesAdmin' } })

const {
  loading,
  error,
  fetchRetentionRules,
  createRetentionRule,
  updateRetentionRule,
  validateRetentionRule,
  fetchRetentionDue,
  fetchMetaOptions,
  fetchSeries,
  getJson,
} = useArchives()

const rules = ref<any[]>([])
const due = ref<any[]>([])
const structures = ref<any[]>([])
const series = ref<any[]>([])
const dispositionItems = ref<{ title: string; value: string }[]>([])
const triggerItems = ref<{ title: string; value: string }[]>([])
const dialog = ref(false)
const snack = ref(false)
const snackText = ref('')

function notify(msg: string) {
  snackText.value = msg
  snack.value = true
}
const editing = ref<any | null>(null)
const validateOnCreate = ref(false)

const emptyForm = () => ({
  code: '',
  name: '',
  description: '',
  structure_id: null as number | null,
  series_id: null as number | null,
  trigger_event: 'cloture',
  current_years: 5,
  intermediate_years: 5,
  final_disposition: 'elimination',
  legal_basis: '',
  authority: '',
  is_active: true,
})

const form = ref(emptyForm())

const dispositionLabel = (value: string) =>
  dispositionItems.value.find(i => i.value === value)?.title || value

async function load() {
  const [r, d, structs, meta, ser] = await Promise.all([
    fetchRetentionRules(false),
    fetchRetentionDue(),
    getJson('/meta/structures').catch(() => ({ data: [] })),
    fetchMetaOptions(),
    fetchSeries(),
  ])
  rules.value = r.data || []
  due.value = d.data || []
  structures.value = structs.data || structs || []
  series.value = ser.data || []
  dispositionItems.value = meta.data?.final_dispositions || []
  triggerItems.value = meta.data?.trigger_events || []
  if (!form.value.final_disposition && dispositionItems.value[0])
    form.value.final_disposition = dispositionItems.value[0].value
}

function openCreate() {
  editing.value = null
  validateOnCreate.value = false
  form.value = emptyForm()
  if (dispositionItems.value[0])
    form.value.final_disposition = dispositionItems.value[0].value
  if (triggerItems.value[0])
    form.value.trigger_event = triggerItems.value[0].value
  dialog.value = true
}

function openEdit(rule: any) {
  editing.value = rule
  validateOnCreate.value = false
  form.value = {
    code: rule.code || '',
    name: rule.name || '',
    description: rule.description || '',
    structure_id: rule.structure_id ?? null,
    series_id: rule.series_id ?? null,
    trigger_event: rule.trigger_event || 'cloture',
    current_years: rule.current_years ?? 0,
    intermediate_years: rule.intermediate_years ?? 0,
    final_disposition: rule.final_disposition || 'elimination',
    legal_basis: rule.legal_basis || '',
    authority: rule.authority || '',
    is_active: rule.is_active !== false,
  }
  dialog.value = true
}

async function save() {
  try {
    if (editing.value) {
      if (editing.value.is_validated) {
        await updateRetentionRule(editing.value.id, { is_active: form.value.is_active })
      }
      else {
        await updateRetentionRule(editing.value.id, { ...form.value })
      }
    }
    else {
      const created = await createRetentionRule({ ...form.value })
      if (validateOnCreate.value && created?.id)
        await validateRetentionRule(created.id)
    }
    dialog.value = false
    notify('Règle enregistrée')
    await load()
  }
  catch (e: any) {
    notify(e?.message || error.value || 'Échec')
  }
}

async function validate(rule: any) {
  try {
    await validateRetentionRule(rule.id)
    notify('Règle validée')
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Validation impossible')
  }
}

async function toggleActive(rule: any) {
  try {
    await updateRetentionRule(rule.id, { is_active: !rule.is_active })
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Mise à jour impossible')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Conservation et échéances"
      subtitle="Tableaux de gestion versionnés — CRUD réel"
      icon="tabler-hourglass"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="openCreate"
        >
          Nouvelle règle
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VSnackbar
      v-model="snack"
      :timeout="4000"
    >{{ snackText }}</VSnackbar>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Règles de conservation</VCardTitle>
          </VCardItem>
          <VList :loading="loading">
            <VListItem
              v-for="rule in rules"
              :key="rule.id"
              :title="`${rule.code} v${rule.version} — ${rule.name}`"
              :subtitle="`${rule.current_years}+${rule.intermediate_years} ans → ${dispositionLabel(rule.final_disposition)} · ${rule.is_validated ? 'validée' : 'brouillon'} · ${rule.is_active ? 'active' : 'inactive'}`"
            >
              <template #append>
                <div class="d-flex gap-1">
                  <VBtn
                    size="small"
                    variant="tonal"
                    @click="openEdit(rule)"
                  >
                    Éditer
                  </VBtn>
                  <VBtn
                    v-if="!rule.is_validated"
                    size="small"
                    color="success"
                    variant="tonal"
                    @click="validate(rule)"
                  >
                    Valider
                  </VBtn>
                  <VBtn
                    size="small"
                    variant="text"
                    @click="toggleActive(rule)"
                  >
                    {{ rule.is_active ? 'Désactiver' : 'Activer' }}
                  </VBtn>
                </div>
              </template>
            </VListItem>
            <VListItem
              v-if="!rules.length"
              title="Aucune règle en base"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Échéances arrivées</VCardTitle>
          </VCardItem>
          <VList>
            <VListItem
              v-for="record in due"
              :key="record.id"
              :title="record.title"
              :subtitle="`${record.permanent_id} · échéance ${record.disposition_due_at}`"
            />
            <VListItem
              v-if="!due.length"
              title="Aucune échéance"
            />
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="640"
    >
      <VCard>
        <VCardTitle>{{ editing ? 'Modifier la règle' : 'Nouvelle règle' }}</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VAlert
            v-if="editing?.is_validated"
            type="info"
            variant="tonal"
            density="compact"
          >
            Règle validée : seuls le statut actif/inactif peut être modifié. Pour changer les délais, créez une nouvelle version.
          </VAlert>
          <VTextField
            v-model="form.code"
            label="Code"
            :disabled="!!editing?.is_validated"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
            :disabled="!!editing?.is_validated"
          />
          <VSelect
            v-model="form.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure"
            clearable
            :disabled="!!editing?.is_validated"
          />
          <VSelect
            v-model="form.series_id"
            :items="series.map(s => ({ title: `${s.code} — ${s.name}`, value: s.id }))"
            label="Série"
            clearable
            :disabled="!!editing?.is_validated"
          />
          <VSelect
            v-model="form.trigger_event"
            :items="triggerItems"
            item-title="title"
            item-value="value"
            label="Événement déclencheur"
            :disabled="!!editing?.is_validated"
          />
          <VTextField
            v-model.number="form.current_years"
            type="number"
            label="Années courantes"
            :disabled="!!editing?.is_validated"
          />
          <VTextField
            v-model.number="form.intermediate_years"
            type="number"
            label="Années intermédiaires"
            :disabled="!!editing?.is_validated"
          />
          <VSelect
            v-model="form.final_disposition"
            :items="dispositionItems"
            item-title="title"
            item-value="value"
            label="Sort final"
            :disabled="!!editing?.is_validated"
          />
          <VTextField
            v-model="form.legal_basis"
            label="Fondement juridique"
            :disabled="!!editing?.is_validated"
          />
          <VTextField
            v-model="form.authority"
            label="Autorité"
            :disabled="!!editing?.is_validated"
          />
          <VTextarea
            v-model="form.description"
            label="Description"
            rows="2"
            :disabled="!!editing?.is_validated"
          />
          <VSwitch
            v-model="form.is_active"
            label="Actif"
            hide-details
          />
          <VSwitch
            v-if="!editing"
            v-model="validateOnCreate"
            label="Valider immédiatement après création"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
