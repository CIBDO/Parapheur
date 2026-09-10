<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { appointmentStatusColor, appointmentStatusLabel, formatAppointmentSlot } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const items = ref<any[]>([])
const loading = ref(false)
const status = ref<string | null>('a_examiner')
const search = ref('')

const statuses = [
  { value: 'demande_recue', title: 'Demandes reçues' },
  { value: 'a_examiner', title: 'À examiner' },
  { value: 'a_valider', title: 'À proposer / valider' },
  { value: 'en_attente', title: 'En attente' },
  { value: 'valide', title: 'À confirmer' },
  { value: 'reporte', title: 'Reportées' },
]

const load = async () => {
  loading.value = true
  try {
    const query: Record<string, unknown> = {}
    if (status.value)
      query.status = status.value
    if (search.value)
      query.q = search.value
    const res = await $api('/appointments', { query })
    items.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

watch(status, load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="File de traitement"
      subtitle="Arbitrage secrétariat — examiner, proposer, confirmer, reporter"
      icon="tabler-inbox"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'parapheur-agenda-nouveau' }"
        >
          Saisie rapide
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap gap-3 align-center">
        <VChipGroup
          v-model="status"
          mandatory
          selected-class="text-primary"
        >
          <VChip
            v-for="item in statuses"
            :key="item.value"
            :value="item.value"
            filter
          >
            {{ item.title }}
          </VChip>
        </VChipGroup>
        <VSpacer />
        <VTextField
          v-model="search"
          density="compact"
          hide-details
          placeholder="Rechercher…"
          style="max-inline-size: 240px"
          @keyup.enter="load"
        />
      </VCardText>
    </VCard>

    <VCard>
      <VProgressLinear
        v-if="loading"
        indeterminate
      />
      <VTable>
        <thead>
          <tr>
            <th>Référence</th>
            <th>Objet</th>
            <th>Demandeur</th>
            <th>Créneau</th>
            <th>Statut</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in items"
            :key="row.id"
          >
            <td>{{ row.reference }}</td>
            <td>{{ row.subject }}</td>
            <td>
              <div>{{ row.requester_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ row.requester_organization }}
              </div>
            </td>
            <td>{{ formatAppointmentSlot(row.start_at, row.end_at) }}</td>
            <td>
              <VChip
                size="small"
                :color="appointmentStatusColor(row.status)"
              >
                {{ appointmentStatusLabel(row.status) }}
              </VChip>
            </td>
            <td>
              <VBtn
                size="small"
                variant="tonal"
                :to="{ name: 'parapheur-agenda-id', params: { id: row.id } }"
              >
                Traiter
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
