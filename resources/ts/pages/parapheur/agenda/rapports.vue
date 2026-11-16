<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const loading = ref(false)
const dashboard = ref<any>({})

const load = async () => {
  loading.value = true
  try {
    dashboard.value = await $api('/appointments/dashboard')
  }
  finally {
    loading.value = false
  }
}

const downloadFeed = async () => {
  const res = await fetch('/api/calendar/ical', {
    credentials: 'include',
    headers: { Accept: 'text/calendar', 'X-Requested-With': 'XMLHttpRequest' },
  })
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'agenda-parapheur.ics'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Rapports agenda"
      subtitle="Indicateurs cabinet DG — permissions appointments.view_reports"
      icon="tabler-chart-dots"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-calendar-plus"
          @click="downloadFeed"
        >
          Exporter mon agenda (iCal)
        </VBtn>
        <VBtn
          color="primary"
          to="/parapheur/agenda/calendrier"
        >
          Ouvrir l’agenda
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow :class="{ 'opacity-60': loading }">
      <VCol
        v-for="item in [
          { title: 'RDV aujourd’hui', value: dashboard.today?.appointments },
          { title: 'Audiences', value: dashboard.today?.audiences },
          { title: 'Réunions du jour', value: dashboard.today?.meetings },
          { title: 'À examiner', value: dashboard.requests?.to_examine },
          { title: 'À valider DG', value: dashboard.requests?.to_validate },
          { title: 'En attente', value: dashboard.requests?.pending },
          { title: 'À confirmer', value: dashboard.followup?.to_confirm },
          { title: 'Reportés', value: dashboard.followup?.rescheduled },
          { title: 'Mes demandes', value: dashboard.my_requests?.total },
          { title: 'Mes confirmés', value: dashboard.my_requests?.confirmed },
          { title: 'Mes en cours', value: dashboard.my_requests?.pending },
          { title: 'Mes refusés', value: dashboard.my_requests?.rejected },
        ]"
        :key="item.title"
        cols="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ item.title }}
            </div>
            <div class="text-h5">
              {{ item.value ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
