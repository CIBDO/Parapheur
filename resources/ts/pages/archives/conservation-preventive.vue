<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'manage', subject: 'Archives' },
})

const { getJson, postJson, fetchLocations, loading, error } = useArchives()
const structureId = ref<number | null>(null)
const structures = ref<any[]>([])
const locations = ref<any[]>([])
const events = ref<any[]>([])

const eventForm = ref({
  event_type: 'incident',
  severity: 'info',
  title: '',
  description: '',
  location_id: null as number | null,
})

const climateForm = ref({
  location_id: null as number | null,
  temperature_c: null as number | null,
  humidity_pct: null as number | null,
  notes: '',
})

const structureItems = computed(() =>
  structures.value.map(s => ({ title: s.name || s.code, value: s.id })),
)
const locationItems = computed(() =>
  locations.value.map(l => ({ title: `${l.full_code} — ${l.name}`, value: l.id })),
)

const eventTypeItems = [
  { title: 'Incident', value: 'incident' },
  { title: 'Infiltration', value: 'infiltration' },
  { title: 'Dégradation', value: 'degradation' },
  { title: 'Infestation', value: 'infestation' },
  { title: 'Incendie', value: 'incendie' },
  { title: 'Sécurité', value: 'securite' },
  { title: 'Nettoyage', value: 'nettoyage' },
  { title: 'Intervention', value: 'intervention' },
]

const severityItems = [
  { title: 'Info', value: 'info' },
  { title: 'Attention', value: 'warning' },
  { title: 'Critique', value: 'critical' },
]

async function loadMeta() {
  const res = await getJson('/meta/structures').catch(() => ({ data: [] }))
  structures.value = res.data || res || []
  if (!structureId.value && structures.value[0])
    structureId.value = structures.value[0].id
  await onStructureChange()
}

async function onStructureChange() {
  if (!structureId.value)
    return
  const locs = await fetchLocations({ structure_id: structureId.value })
  locations.value = locs.data || locs || []
  await load()
}

async function load() {
  if (!structureId.value)
    return
  const ev = await getJson(`/archives/preventive/events?structure_id=${structureId.value}`)
  events.value = ev.data || ev || []
}

async function submitEvent() {
  if (!structureId.value || !eventForm.value.title)
    return
  await postJson('/archives/preventive/events', {
    structure_id: structureId.value,
    ...eventForm.value,
  })
  eventForm.value.title = ''
  eventForm.value.description = ''
  await load()
}

async function submitClimate() {
  if (!structureId.value || !climateForm.value.location_id)
    return
  await postJson('/archives/preventive/climate', {
    structure_id: structureId.value,
    ...climateForm.value,
  })
  climateForm.value.notes = ''
  await load()
}

onMounted(loadMeta)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Conservation préventive"
      subtitle="Incidents et relevés climatiques"
      icon="tabler-temperature"
    />

    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VSelect
          v-model="structureId"
          :items="structureItems"
          label="Structure"
          style="max-width: 360px"
          @update:model-value="onStructureChange"
        />
      </VCardText>
    </VCard>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardTitle>Événement</VCardTitle>
          <VCardText class="d-flex flex-column gap-3">
            <VSelect
              v-model="eventForm.event_type"
              :items="eventTypeItems"
              label="Type"
            />
            <VSelect
              v-model="eventForm.severity"
              :items="severityItems"
              label="Sévérité"
            />
            <VTextField
              v-model="eventForm.title"
              label="Titre"
            />
            <VTextarea
              v-model="eventForm.description"
              label="Description"
              rows="2"
            />
            <VSelect
              v-model="eventForm.location_id"
              :items="locationItems"
              label="Emplacement"
              clearable
            />
            <div>
              <VBtn
                color="primary"
                :loading="loading"
                @click="submitEvent"
              >
                Enregistrer
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardTitle>Relevé climatique</VCardTitle>
          <VCardText class="d-flex flex-column gap-3">
            <VSelect
              v-model="climateForm.location_id"
              :items="locationItems"
              label="Emplacement"
            />
            <VTextField
              v-model.number="climateForm.temperature_c"
              label="Température (°C)"
              type="number"
            />
            <VTextField
              v-model.number="climateForm.humidity_pct"
              label="Humidité (%)"
              type="number"
            />
            <VTextField
              v-model="climateForm.notes"
              label="Notes"
            />
            <div>
              <VBtn
                color="primary"
                :loading="loading"
                @click="submitClimate"
              >
                Enregistrer
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="parapheur-section-card mt-4">
      <VCardTitle>Événements</VCardTitle>
      <VCardText>
        <div
          v-if="!events.length"
          class="text-medium-emphasis"
        >
          Aucun événement pour cette structure.
        </div>
        <VTable
          v-else
          density="compact"
        >
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Sévérité</th>
              <th>Titre</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="e in events"
              :key="e.id"
            >
              <td>{{ formatDateFr(e.occurred_at, true) }}</td>
              <td>{{ e.event_type }}</td>
              <td>{{ e.severity }}</td>
              <td>{{ e.title }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </div>
</template>
