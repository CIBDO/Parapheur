<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'AnnouncementStats',
  },
})

const route = useRoute()
const data = ref<any>(null)
const error = ref('')

async function load() {
  try {
    data.value = await $api(`/announcements/${route.params.id}/receipts`)
  }
  catch {
    error.value = 'Les accusés de lecture ne sont pas disponibles pour votre profil.'
  }
}

async function remind() {
  const result = await $api<{ sent: number }>(`/announcements/${route.params.id}/remind`, { method: 'POST' })
  error.value = ''
  data.value = { ...data.value, notice: `${result.sent} relance(s) envoyée(s).` }
  await load()
}

async function exportCsv() {
  const text = await $api<string>(`/announcements/${route.params.id}/receipts`, {
    query: { format: 'csv' },
    responseType: 'text',
  })
  const blob = new Blob([text], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'accuses-annonce.csv'
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
</script>

<template>
  <ParapheurPageHeader
    title="Accusés de lecture"
    subtitle="La confirmation est un accusé de prise de connaissance, pas une signature électronique."
    icon="tabler-checkbox"
  >
    <template #actions>
      <VBtn variant="tonal" @click="remind">
        Relancer
      </VBtn>
      <VBtn variant="text" @click="exportCsv">
        Exporter
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <VAlert v-if="error" type="warning" variant="tonal">
    {{ error }}
  </VAlert>
  <template v-else-if="data">
    <VRow class="mb-4">
      <VCol cols="12" sm="4">
        <VCard><VCardText>Destinataires : {{ data.total }}</VCardText></VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard><VCardText>Consultations : {{ data.consultations }}</VCardText></VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard><VCardText>Confirmations : {{ data.confirmations }} ({{ data.rate === null ? '—' : Math.round(data.rate * 100) + ' %' }})</VCardText></VCard>
      </VCol>
    </VRow>
    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Agent</th>
            <th>Consultation</th>
            <th>Confirmation</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in data.items" :key="row.user_id">
            <td>{{ row.name }}</td>
            <td>{{ row.consulted_at || '—' }}</td>
            <td>{{ row.acknowledged_at || '—' }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </template>
</template>
