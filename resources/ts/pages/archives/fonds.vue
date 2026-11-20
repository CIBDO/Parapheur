<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'manage', subject: 'ArchivesAdmin' },
})

const {
  loading,
  error,
  fetchFonds,
  createFonds,
  updateFonds,
  deleteFonds,
  createSeries,
  updateSeries,
  deleteSeries,
  getJson,
} = useArchives()

const fonds = ref<any[]>([])
const structures = ref<any[]>([])
const snack = ref(false)
const snackText = ref('')
const fondsDialog = ref(false)

function notify(msg: string) {
  snackText.value = msg
  snack.value = true
}
const seriesDialog = ref(false)
const editingFonds = ref<any | null>(null)
const editingSeries = ref<any | null>(null)

const fondsForm = ref({
  code: '',
  name: '',
  description: '',
  structure_id: null as number | null,
  is_active: true,
})

const seriesForm = ref({
  fonds_id: null as number | null,
  code: '',
  name: '',
  description: '',
  is_active: true,
})

async function load() {
  const [f, s] = await Promise.all([
    fetchFonds(false),
    getJson('/meta/structures').catch(() => ({ data: [] })),
  ])
  fonds.value = f.data || []
  structures.value = s.data || s || []
}

function openFondsCreate() {
  editingFonds.value = null
  fondsForm.value = { code: '', name: '', description: '', structure_id: null, is_active: true }
  fondsDialog.value = true
}

function openFondsEdit(item: any) {
  editingFonds.value = item
  fondsForm.value = {
    code: item.code || '',
    name: item.name || '',
    description: item.description || '',
    structure_id: item.structure_id ?? null,
    is_active: item.is_active !== false,
  }
  fondsDialog.value = true
}

async function saveFonds() {
  try {
    if (editingFonds.value)
      await updateFonds(editingFonds.value.id, { ...fondsForm.value })
    else
      await createFonds({ ...fondsForm.value })
    fondsDialog.value = false
    notify('Fonds enregistré')
    await load()
  }
  catch (e: any) {
    notify(e?.message || error.value || 'Échec')
  }
}

async function removeFonds(item: any) {
  if (!confirm(`Supprimer le fonds « ${item.code} » ?`))
    return
  try {
    await deleteFonds(item.id)
    notify('Fonds supprimé')
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Suppression impossible')
  }
}

function openSeriesCreate(fondsId?: number) {
  editingSeries.value = null
  seriesForm.value = {
    fonds_id: fondsId ?? (fonds.value[0]?.id ?? null),
    code: '',
    name: '',
    description: '',
    is_active: true,
  }
  seriesDialog.value = true
}

function openSeriesEdit(serie: any) {
  editingSeries.value = serie
  seriesForm.value = {
    fonds_id: serie.fonds_id,
    code: serie.code || '',
    name: serie.name || '',
    description: serie.description || '',
    is_active: serie.is_active !== false,
  }
  seriesDialog.value = true
}

async function saveSeries() {
  try {
    if (editingSeries.value)
      await updateSeries(editingSeries.value.id, { ...seriesForm.value })
    else
      await createSeries({ ...seriesForm.value })
    seriesDialog.value = false
    notify('Série enregistrée')
    await load()
  }
  catch (e: any) {
    notify(e?.message || error.value || 'Échec')
  }
}

async function removeSeries(serie: any) {
  if (!confirm(`Supprimer la série « ${serie.code} » ?`))
    return
  try {
    await deleteSeries(serie.id)
    notify('Série supprimée')
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Suppression impossible')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Fonds et séries"
      subtitle="Référentiel intellectuel — données issues de la base"
      icon="tabler-hierarchy-2"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          @click="openSeriesCreate()"
        >
          Nouvelle série
        </VBtn>
        <VBtn
          color="primary"
          @click="openFondsCreate"
        >
          Nouveau fonds
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VSnackbar
      v-model="snack"
      :timeout="4000"
    >{{ snackText }}</VSnackbar>

    <VRow>
      <VCol
        v-for="item in fonds"
        :key="item.id"
        cols="12"
        md="6"
      >
        <VCard
          class="parapheur-section-card"
          :loading="loading"
        >
          <VCardItem>
            <VCardTitle>{{ item.code }} — {{ item.name }}</VCardTitle>
            <VCardSubtitle>
              {{ item.description || 'Sans description' }}
              · {{ item.is_active ? 'Actif' : 'Inactif' }}
            </VCardSubtitle>
            <template #append>
              <div class="d-flex gap-1">
                <VBtn
                  size="small"
                  variant="tonal"
                  @click="openFondsEdit(item)"
                >
                  Éditer
                </VBtn>
                <VBtn
                  size="small"
                  color="error"
                  variant="text"
                  @click="removeFonds(item)"
                >
                  Suppr.
                </VBtn>
              </div>
            </template>
          </VCardItem>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="text-caption">
                Séries
              </div>
              <VBtn
                size="x-small"
                variant="tonal"
                @click="openSeriesCreate(item.id)"
              >
                + Série
              </VBtn>
            </div>
            <div
              v-for="serie in (item.series || [])"
              :key="serie.id"
              class="d-flex align-center justify-space-between mb-1"
            >
              <VChip
                size="small"
                label
                :color="serie.is_active === false ? 'secondary' : undefined"
              >
                {{ serie.code }} — {{ serie.name }}
              </VChip>
              <div class="d-flex gap-1">
                <VBtn
                  size="x-small"
                  variant="text"
                  @click="openSeriesEdit(serie)"
                >
                  Éditer
                </VBtn>
                <VBtn
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeSeries(serie)"
                >
                  ×
                </VBtn>
              </div>
            </div>
            <div
              v-if="!(item.series || []).length"
              class="text-medium-emphasis"
            >
              Aucune série.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard
      v-if="!fonds.length"
      class="parapheur-section-card"
    >
      <VCardText class="text-medium-emphasis">
        Aucun fonds. Créez le premier référentiel.
      </VCardText>
    </VCard>

    <VDialog
      v-model="fondsDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ editingFonds ? 'Modifier le fonds' : 'Créer un fonds' }}</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="fondsForm.code"
            label="Code"
          />
          <VTextField
            v-model="fondsForm.name"
            label="Libellé"
          />
          <VSelect
            v-model="fondsForm.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure (optionnel)"
            clearable
          />
          <VTextarea
            v-model="fondsForm.description"
            label="Description"
            rows="3"
          />
          <VSwitch
            v-model="fondsForm.is_active"
            label="Actif"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="fondsDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            @click="saveFonds"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="seriesDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ editingSeries ? 'Modifier la série' : 'Créer une série' }}</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VSelect
            v-model="seriesForm.fonds_id"
            :items="fonds.map(f => ({ title: `${f.code} — ${f.name}`, value: f.id }))"
            label="Fonds"
          />
          <VTextField
            v-model="seriesForm.code"
            label="Code"
          />
          <VTextField
            v-model="seriesForm.name"
            label="Libellé"
          />
          <VTextarea
            v-model="seriesForm.description"
            label="Description"
            rows="2"
          />
          <VSwitch
            v-model="seriesForm.is_active"
            label="Actif"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="seriesDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            @click="saveSeries"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
