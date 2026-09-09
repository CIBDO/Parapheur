<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'Reporting',
  },
})

const dg = ref<any>(null)
const direction = ref<any>(null)
const exporting = ref(false)

onMounted(async () => {
  try {
    dg.value = await $api('/dashboard/dg')
  }
  catch {}
  try {
    direction.value = await $api('/dashboard/direction')
  }
  catch {}
})

const downloadExport = async (scope: 'dg' | 'direction', format: 'csv' | 'pdf') => {
  exporting.value = true
  try {
    const blob = await $api('/reporting/export', {
      query: { scope, format },
      responseType: 'blob',
    }) as Blob
    const ext = format === 'csv' ? 'csv' : 'html'
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `reporting_${scope}_${Date.now()}.${ext}`
    a.click()
    URL.revokeObjectURL(url)
  }
  finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-6">
      <h4 class="text-h4 mb-0">
        Reporting
      </h4>
      <div class="d-flex flex-wrap gap-2">
        <VBtn
          variant="tonal"
          :loading="exporting"
          @click="downloadExport('dg', 'csv')"
        >
          Export CSV DG
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="exporting"
          @click="downloadExport('dg', 'pdf')"
        >
          Export imprimable DG
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="exporting"
          @click="downloadExport('direction', 'csv')"
        >
          Export CSV Direction
        </VBtn>
      </div>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard>
          <VCardTitle>Indicateurs DG (CDC §32–33)</VCardTitle>
          <VCardText v-if="dg">
            <VList density="compact">
              <VListItem
                title="Dossiers reçus"
                :subtitle="String(dg.received)"
              />
              <VListItem
                title="À traiter"
                :subtitle="String(dg.to_process)"
              />
              <VListItem
                title="Urgents"
                :subtitle="String(dg.urgent)"
              />
              <VListItem
                title="En retard"
                :subtitle="String(dg.overdue)"
              />
              <VListItem
                title="Validés"
                :subtitle="String(dg.validated)"
              />
              <VListItem
                title="Retournés"
                :subtitle="String(dg.returned)"
              />
              <VListItem
                title="Délai moyen de traitement (jours)"
                :subtitle="String(dg.avg_processing_days ?? '—')"
              />
              <VListItem
                title="Taux de retour (%)"
                :subtitle="String(dg.return_rate ?? '—')"
              />
              <VListItem
                title="Traités électroniquement"
                :subtitle="String(dg.electronic_treated ?? '—')"
              />
              <VListItem
                title="Respect des échéances (%)"
                :subtitle="String(dg.deadline_respect_rate ?? '—')"
              />
              <VListItem
                title="Exécution des décisions (%)"
                :subtitle="String(dg.decision_execution_rate ?? '—')"
              />
              <VListItem
                title="Instructions ouvertes"
                :subtitle="String(dg.instructions_open)"
              />
              <VListItem
                title="Instructions en retard"
                :subtitle="String(dg.instructions_late)"
              />
            </VList>

            <div
              v-if="dg.avg_processing_by_structure"
              class="mt-4"
            >
              <div class="text-subtitle-2 mb-2">
                Délai moyen par structure
              </div>
              <div
                v-for="(days, code) in dg.avg_processing_by_structure"
                :key="code"
                class="d-flex justify-space-between text-body-2 mb-1"
              >
                <span>{{ code }}</span>
                <span>{{ days }} j</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard>
          <VCardTitle>Indicateurs direction</VCardTitle>
          <VCardText v-if="direction">
            <VList density="compact">
              <VListItem
                title="Documents préparés"
                :subtitle="String(direction.prepared)"
              />
              <VListItem
                title="En validation interne"
                :subtitle="String(direction.in_validation)"
              />
              <VListItem
                title="Transmis DG"
                :subtitle="String(direction.sent_dg)"
              />
              <VListItem
                title="Retournés"
                :subtitle="String(direction.returned)"
              />
              <VListItem
                title="Validés"
                :subtitle="String(direction.validated)"
              />
              <VListItem
                title="En retard"
                :subtitle="String(direction.overdue)"
              />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
