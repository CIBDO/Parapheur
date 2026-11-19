<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { recordStatusLabels, useArchives } from '@/composables/useArchives'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const {
  loading,
  fetchRecords,
  fetchFonds,
  fetchSeries,
  fetchLocations,
  fetchBoxes,
  getJson,
} = useArchives()

const q = ref('')
const filters = ref({
  status: null as string | null,
  structure_id: null as number | null,
  fonds_id: null as number | null,
  series_id: null as number | null,
  typology: '',
  medium: null as string | null,
  location_id: null as number | null,
  box_id: null as number | null,
  date_from: '',
  date_to: '',
})

const items = ref<any[]>([])
const total = ref(0)
const searched = ref(false)
const structures = ref<any[]>([])
const fonds = ref<any[]>([])
const series = ref<any[]>([])
const locations = ref<any[]>([])
const boxes = ref<any[]>([])

const statusItems = Object.entries(recordStatusLabels).map(([value, title]) => ({ title, value }))
const mediumItems = [
  { title: 'Électronique', value: 'electronique' },
  { title: 'Physique', value: 'physique' },
  { title: 'Hybride', value: 'hybride' },
]

async function loadMeta() {
  const [structs, f, locs, b] = await Promise.all([
    getJson('/meta/structures').catch(() => ({ data: [] })),
    fetchFonds().catch(() => ({ data: [] })),
    fetchLocations().catch(() => ({ data: [] })),
    fetchBoxes({ per_page: 200 }).catch(() => ({ data: [] })),
  ])
  structures.value = structs.data || structs || []
  fonds.value = f.data || f || []
  locations.value = locs.data || locs || []
  boxes.value = b.data || b || []
}

watch(() => filters.value.fonds_id, async (id) => {
  filters.value.series_id = null
  if (!id) {
    series.value = []
    return
  }
  const data = await fetchSeries(id).catch(() => ({ data: [] }))
  series.value = data.data || data || []
})

async function search() {
  searched.value = true
  const params: Record<string, string | number | boolean> = { per_page: 50 }
  if (q.value.trim())
    params.q = q.value.trim()
  Object.entries(filters.value).forEach(([k, v]) => {
    if (v !== null && v !== undefined && v !== '')
      params[k] = v as string | number
  })
  const data = await fetchRecords(params)
  items.value = data.data || []
  total.value = data.total || items.value.length
}

function resetFilters() {
  q.value = ''
  filters.value = {
    status: null,
    structure_id: null,
    fonds_id: null,
    series_id: null,
    typology: '',
    medium: null,
    location_id: null,
    box_id: null,
    date_from: '',
    date_to: '',
  }
  series.value = []
  searched.value = false
  items.value = []
  total.value = 0
}

onMounted(loadMeta)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche avancée"
      subtitle="Métadonnées des archives autorisées (référence, cote, boîte, période…)"
      icon="tabler-search"
    />

    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex flex-column gap-4">
        <div class="d-flex gap-3 align-center flex-wrap">
          <VTextField
            v-model="q"
            label="Référence, titre, identifiant, cote, boîte, mots-clés…"
            hide-details
            clearable
            class="flex-grow-1"
            style="min-width: 240px"
            @keyup.enter="search"
          />
          <VBtn
            color="primary"
            :loading="loading"
            @click="search"
          >
            Rechercher
          </VBtn>
          <VBtn
            variant="text"
            @click="resetFilters"
          >
            Réinitialiser
          </VBtn>
        </div>

        <VRow dense>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.status"
              :items="statusItems"
              label="Statut"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.structure_id"
              :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
              label="Structure"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.fonds_id"
              :items="fonds.map(f => ({ title: f.name || f.code, value: f.id }))"
              label="Fonds"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.series_id"
              :items="series.map(s => ({ title: s.name || s.code, value: s.id }))"
              label="Série"
              clearable
              hide-details
              :disabled="!filters.fonds_id"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="filters.typology"
              label="Typologie"
              hide-details
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.medium"
              :items="mediumItems"
              label="Support"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.location_id"
              :items="locations.map(l => ({ title: `${l.full_code} — ${l.name}`, value: l.id }))"
              label="Emplacement"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="filters.box_id"
              :items="boxes.map(b => ({ title: b.code, value: b.id }))"
              label="Boîte"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="filters.date_from"
              label="Période du"
              type="date"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="filters.date_to"
              label="Période au"
              type="date"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      v-if="searched"
      class="parapheur-section-card"
    >
      <VCardItem>
        <VCardTitle>{{ total }} résultat{{ total > 1 ? 's' : '' }}</VCardTitle>
      </VCardItem>
      <VList>
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="item.title"
          :subtitle="[
            item.permanent_id,
            item.reference,
            item.structure?.code,
            item.location?.full_code,
            item.box?.code ? `Boîte ${item.box.code}` : null,
            item.date_start || item.date_end
              ? `${formatDateFr(item.date_start)} → ${formatDateFr(item.date_end)}`
              : null,
          ].filter(Boolean).join(' · ')"
          @click="router.push({ name: 'archives-records-id', params: { id: item.id } })"
        >
          <template #append>
            <div class="d-flex align-center gap-2">
              <VChip
                v-if="item.typology"
                size="small"
                label
                variant="tonal"
              >
                {{ item.typology }}
              </VChip>
              <VChip
                size="small"
                label
                color="secondary"
              >
                {{ recordStatusLabels[item.status] || item.status }}
              </VChip>
            </div>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-if="!items.length"
        class="text-medium-emphasis"
      >
        Aucun résultat.
      </VCardText>
    </VCard>
  </div>
</template>
