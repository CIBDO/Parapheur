<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'manage', subject: 'ArchivesAdmin' } })

const {
  loading,
  error,
  fetchDispositions,
  getJson,
  createDisposition,
  authorizeDisposition,
  refuseDisposition,
  executeDisposition,
  fetchRetentionDue,
  fetchMetaOptions,
} = useArchives()

const items = ref<any[]>([])
const dueRecords = ref<any[]>([])
const structures = ref<any[]>([])
const selected = ref<any | null>(null)
const dialog = ref(false)
const refuseDialog = ref(false)
const snack = ref(false)
const snackText = ref('')

function notify(msg: string) {
  snackText.value = msg
  snack.value = true
}
const refuseReason = ref('')
const dispositionTypes = ref<{ title: string; value: string }[]>([])
const statusItems = ref<{ title: string; value: string }[]>([])

const form = ref({
  title: '',
  structure_id: null as number | null,
  disposition_type: 'elimination',
  justification: '',
  legal_basis: '',
  record_ids: [] as number[],
})

const statusLabel = (value: string) => statusItems.value.find(i => i.value === value)?.title || value
const typeLabel = (value: string) => dispositionTypes.value.find(i => i.value === value)?.title || value

async function load() {
  const [data, due, structs, meta] = await Promise.all([
    fetchDispositions(),
    fetchRetentionDue().catch(() => ({ data: [] })),
    getJson('/meta/structures').catch(() => ({ data: [] })),
    fetchMetaOptions(),
  ])
  items.value = data.data || []
  dueRecords.value = due.data || []
  structures.value = structs.data || structs || []
  dispositionTypes.value = meta.data?.disposition_types || meta.data?.final_dispositions || []
  statusItems.value = meta.data?.disposition_statuses || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
  if (!form.value.disposition_type && dispositionTypes.value[0])
    form.value.disposition_type = dispositionTypes.value[0].value
}

async function open(id: number) {
  selected.value = await getJson(`/archives/dispositions/${id}`)
}

function openCreate() {
  form.value = {
    title: '',
    structure_id: structures.value[0]?.id ?? null,
    disposition_type: dispositionTypes.value.find(d => d.value === 'elimination')?.value
      || dispositionTypes.value[0]?.value
      || 'elimination',
    justification: '',
    legal_basis: '',
    record_ids: dueRecords.value.slice(0, 20).map((r: any) => r.id),
  }
  dialog.value = true
}

async function save() {
  try {
    const created = await createDisposition({ ...form.value })
    dialog.value = false
    notify('Proposition créée')
    await load()
    if (created?.id)
      await open(created.id)
  }
  catch (e: any) {
    notify(e?.message || error.value || 'Échec')
  }
}

async function authorize() {
  if (!selected.value)
    return
  try {
    await authorizeDisposition(selected.value.id, {})
    notify('Proposition autorisée')
    await open(selected.value.id)
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Autorisation impossible')
  }
}

function openRefuse() {
  refuseReason.value = ''
  refuseDialog.value = true
}

async function confirmRefuse() {
  if (!selected.value || refuseReason.value.trim().length < 3)
    return
  try {
    await refuseDisposition(selected.value.id, refuseReason.value.trim())
    refuseDialog.value = false
    notify('Proposition refusée')
    await open(selected.value.id)
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Refus impossible')
  }
}

async function execute() {
  if (!selected.value)
    return
  if (!confirm('Confirmer l’exécution du sort final ? Cette action est irréversible.'))
    return
  try {
    await executeDisposition(selected.value.id)
    notify('Sort final exécuté')
    await open(selected.value.id)
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Exécution impossible')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Sort final"
      subtitle="Proposition / autorisation / exécution séparées — aucune destruction one-click"
      icon="tabler-gavel"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="openCreate"
        >
          Nouvelle proposition
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
        md="5"
      >
        <VCard class="parapheur-section-card">
          <VList :loading="loading">
            <VListItem
              v-for="d in items"
              :key="d.id"
              :title="d.reference"
              :subtitle="`${d.title} · ${statusLabel(d.status)} · ${d.items_count || 0} pièces`"
              @click="open(d.id)"
            />
            <VListItem
              v-if="!items.length"
              title="Aucune proposition en base"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="7"
      >
        <VCard
          v-if="selected"
          class="parapheur-section-card"
        >
          <VCardItem>
            <VCardTitle>{{ selected.reference }}</VCardTitle>
            <VCardSubtitle>
              {{ statusLabel(selected.status) }}
              · {{ typeLabel(selected.disposition_type) }}
              · PV {{ selected.pv_reference || '—' }}
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="mb-3 text-body-2">
              {{ selected.justification || 'Sans justification' }}
            </div>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VBtn
                v-if="selected.status === 'en_validation' || selected.status === 'proposition'"
                color="success"
                @click="authorize"
              >
                Autoriser
              </VBtn>
              <VBtn
                v-if="selected.status === 'en_validation' || selected.status === 'proposition'"
                color="error"
                @click="openRefuse"
              >
                Refuser
              </VBtn>
              <VBtn
                v-if="selected.status === 'autorisee'"
                color="warning"
                @click="execute"
              >
                Exécuter
              </VBtn>
            </div>
            <VList>
              <VListItem
                v-for="item in selected.items || []"
                :key="item.id"
                :title="item.record?.permanent_id || String(item.record_id)"
                :subtitle="`${item.status} · ${item.record?.title || ''}`"
              />
            </VList>
          </VCardText>
        </VCard>
        <VCard
          v-else
          class="parapheur-section-card"
        >
          <VCardText class="text-medium-emphasis">
            Sélectionnez une proposition ou créez-en une à partir des échéances.
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="640"
    >
      <VCard>
        <VCardTitle>Nouvelle proposition de sort final</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.title"
            label="Titre"
          />
          <VSelect
            v-model="form.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure"
          />
          <VSelect
            v-model="form.disposition_type"
            :items="dispositionTypes"
            item-title="title"
            item-value="value"
            label="Type de sort"
          />
          <VSelect
            v-model="form.record_ids"
            :items="dueRecords.map(r => ({ title: `${r.permanent_id} — ${r.title}`, value: r.id }))"
            label="Archives concernées (échéances)"
            multiple
            chips
            closable-chips
          />
          <VTextarea
            v-model="form.justification"
            label="Justification"
            rows="3"
          />
          <VTextField
            v-model="form.legal_basis"
            label="Fondement juridique"
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
            :disabled="!form.title || !form.structure_id || !form.record_ids.length"
            @click="save"
          >
            Proposer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="refuseDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Motif du refus</VCardTitle>
        <VCardText>
          <VTextarea
            v-model="refuseReason"
            label="Motif (obligatoire)"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="refuseDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            :disabled="refuseReason.trim().length < 3"
            @click="confirmRefuse"
          >
            Confirmer le refus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
