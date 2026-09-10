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

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/appointments', { query: { mine: 1 } })
    items.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes rendez-vous"
      subtitle="Suivi de mes demandes et rendez-vous confirmés"
      icon="tabler-user-check"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'parapheur-agenda-nouveau' }"
        >
          Demander un rendez-vous
        </VBtn>
      </template>
    </ParapheurPageHeader>

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
                Ouvrir
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
