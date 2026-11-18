<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'manage', subject: 'Archives' },
})

const { getJson, postJson, loading, error } = useArchives()
const structureId = ref<number | null>(null)
const events = ref<any[]>([])
const climate = ref<any[]>([])

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

async function load() {
  if (!structureId.value)
    return
  const [ev, cl] = await Promise.all([
    getJson(`/archives/preventive/events?structure_id=${structureId.value}`),
    getJson(`/archives/preventive/climate?structure_id=${structureId.value}`),
  ])
  events.value = ev.data || ev || []
  climate.value = cl.data || cl || []
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
  await load()
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Conservation préventive"
      subtitle="Incidents, climat (T° / humidité) — saisie manuelle"
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
      <VCardText class="d-flex ga-3 align-center">
        <VTextField
          v-model.number="structureId"
          label="Structure ID"
          type="number"
          style="max-width: 200px"
        />
        <VBtn
          color="primary"
          :loading="loading"
          @click="load"
        >
          Charger
        </VBtn>
      </VCardText>
    </VCard>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardTitle>Signaler un événement</VCardTitle>
          <VCardText>
            <VSelect
              v-model="eventForm.event_type"
              :items="['incident', 'infiltration', 'degradation', 'infestation', 'incendie', 'securite', 'nettoyage', 'intervention', 'climat']"
              label="Type"
              class="mb-2"
            />
            <VSelect
              v-model="eventForm.severity"
              :items="['info', 'warning', 'critical']"
              label="Sévérité"
              class="mb-2"
            />
            <VTextField
              v-model="eventForm.title"
              label="Titre"
              class="mb-2"
            />
            <VTextarea
              v-model="eventForm.description"
              label="Description"
              rows="2"
              class="mb-2"
            />
            <VTextField
              v-model.number="eventForm.location_id"
              label="Emplacement ID (opt.)"
              type="number"
              class="mb-2"
            />
            <VBtn
              color="primary"
              @click="submitEvent"
            >
              Enregistrer
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardTitle>Relevé climatique</VCardTitle>
          <VCardText>
            <VTextField
              v-model.number="climateForm.location_id"
              label="Emplacement ID"
              type="number"
              class="mb-2"
            />
            <VTextField
              v-model.number="climateForm.temperature_c"
              label="Température (°C)"
              type="number"
              class="mb-2"
            />
            <VTextField
              v-model.number="climateForm.humidity_pct"
              label="Humidité (%)"
              type="number"
              class="mb-2"
            />
            <VTextField
              v-model="climateForm.notes"
              label="Notes"
              class="mb-2"
            />
            <VBtn
              color="primary"
              @click="submitClimate"
            >
              Enregistrer
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="parapheur-section-card mt-4">
      <VCardTitle>Événements récents</VCardTitle>
      <VCardText>
        <VTable density="compact">
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
              <td>{{ e.occurred_at }}</td>
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
