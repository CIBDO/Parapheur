<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'AnnouncementStats',
  },
})

const stats = ref<any>(null)
const error = ref('')

onMounted(async () => {
  try {
    stats.value = await $api('/announcements/statistics')
  }
  catch {
    error.value = 'Les statistiques ne sont pas disponibles pour votre profil.'
  }
})

const cards = computed(() => {
  if (!stats.value)
    return []

  return [
    { label: 'Publiées', value: stats.value.published },
    { label: 'Actives', value: stats.value.active },
    { label: 'Expirées', value: stats.value.expired },
    { label: 'Urgentes', value: stats.value.urgent },
    { label: 'En validation', value: stats.value.pending_validation },
    { label: 'Destinataires', value: stats.value.recipients },
    { label: 'Consultations', value: stats.value.consultations },
    { label: 'Confirmations', value: stats.value.acknowledgements },
  ]
})
</script>

<template>
  <ParapheurPageHeader
    title="Statistiques des annonces"
    subtitle="Indicateurs limités à votre périmètre"
    icon="tabler-chart-bar"
  />
  <VAlert v-if="error" type="warning" variant="tonal">
    {{ error }}
  </VAlert>
  <template v-else-if="stats">
    <VRow>
      <VCol v-for="card in cards" :key="card.label" cols="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-h5">{{ card.value }}</div>
            <div class="text-caption">{{ card.label }}</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <VRow class="mt-2">
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>Par catégorie</VCardTitle>
          <VCardText>
            <div v-for="row in stats.by_category" :key="row.category" class="d-flex justify-space-between mb-1">
              <span>{{ row.category }}</span>
              <strong>{{ row.total }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>Par structure</VCardTitle>
          <VCardText>
            <div v-for="row in stats.by_structure" :key="row.structure" class="d-flex justify-space-between mb-1">
              <span>{{ row.structure }}</span>
              <strong>{{ row.total }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <p class="text-caption mt-4">
      Délai moyen de publication : {{ stats.average_publication_hours ?? '—' }} h.
      Taux de confirmation : {{ stats.acknowledgement_rate === null ? '—' : Math.round(stats.acknowledgement_rate * 100) + ' %' }}.
    </p>
  </template>
</template>
