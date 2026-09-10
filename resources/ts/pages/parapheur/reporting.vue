<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Reporting',
  },
})

const dg = ref<any>(null)
const direction = ref<any>(null)
const exporting = ref(false)
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    const [dgRes, dirRes] = await Promise.all([
      $api('/dashboard/dg').catch(() => null),
      $api('/dashboard/direction').catch(() => null),
    ])
    dg.value = dgRes
    direction.value = dirRes
  }
  finally {
    loading.value = false
  }
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

const dgKpis = computed(() => {
  if (!dg.value)
    return []

  return [
    { title: 'Dossiers reçus', value: dg.value.received, icon: 'tabler-files', color: 'primary' },
    { title: 'À traiter', value: dg.value.to_process, icon: 'tabler-inbox', color: 'info' },
    { title: 'Urgents', value: dg.value.urgent, icon: 'tabler-alert-triangle', color: 'error' },
    { title: 'En retard', value: dg.value.overdue, icon: 'tabler-clock-exclamation', color: 'warning' },
    { title: 'Validés', value: dg.value.validated, icon: 'tabler-circle-check', color: 'success' },
    { title: 'Retournés', value: dg.value.returned, icon: 'tabler-arrow-back-up', color: 'secondary' },
  ]
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Reporting"
      subtitle="Indicateurs de pilotage et exports"
      icon="tabler-chart-bar"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-file-spreadsheet"
          :loading="exporting"
          @click="downloadExport('dg', 'csv')"
        >
          CSV DG
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-printer"
          :loading="exporting"
          @click="downloadExport('dg', 'pdf')"
        >
          Imprimable DG
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          :loading="exporting"
          @click="downloadExport('direction', 'csv')"
        >
          CSV Direction
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading"
      class="mb-6"
    >
      <VProgressLinear indeterminate color="primary" />
    </div>

    <VRow
      v-if="dg"
      class="mb-6 match-height"
    >
      <VCol
        v-for="kpi in dgKpis"
        :key="kpi.title"
        cols="6"
        md="4"
        lg="2"
      >
        <VCard>
          <VCardText>
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              rounded
              size="40"
              class="mb-3"
            >
              <VIcon
                :icon="kpi.icon"
                size="22"
              />
            </VAvatar>
            <div class="text-caption text-medium-emphasis mb-1">
              {{ kpi.title }}
            </div>
            <div class="text-h5 font-weight-bold">
              {{ kpi.value ?? '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-building-bank"
                size="22"
              />
              Indicateurs DG
            </VCardTitle>
            <VCardSubtitle>Performance du circuit décisionnel</VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText v-if="dg">
            <VList density="comfortable" class="py-0">
              <VListItem
                title="Délai moyen de traitement"
                :subtitle="`${dg.avg_processing_days ?? '—'} jours`"
              />
              <VListItem
                title="Taux de retour"
                :subtitle="`${dg.return_rate ?? '—'} %`"
              />
              <VListItem
                title="Traités électroniquement"
                :subtitle="String(dg.electronic_treated ?? '—')"
              />
              <VListItem
                title="Respect des échéances"
                :subtitle="`${dg.deadline_respect_rate ?? '—'} %`"
              />
              <VListItem
                title="Exécution des décisions"
                :subtitle="`${dg.decision_execution_rate ?? '—'} %`"
              />
              <VListItem
                title="Instructions ouvertes / en retard"
                :subtitle="`${dg.instructions_open ?? 0} / ${dg.instructions_late ?? 0}`"
              />
            </VList>

            <div
              v-if="dg.avg_processing_by_structure"
              class="mt-4"
            >
              <div class="text-subtitle-2 mb-3">
                Délai moyen par structure
              </div>
              <div
                v-for="(days, code) in dg.avg_processing_by_structure"
                :key="code"
                class="d-flex justify-space-between align-center text-body-2 mb-2"
              >
                <VChip
                  size="small"
                  label
                  variant="tonal"
                >
                  {{ code }}
                </VChip>
                <span class="font-weight-medium">{{ days }} j</span>
              </div>
            </div>
          </VCardText>
          <VCardText
            v-else-if="!loading"
            class="parapheur-empty"
          >
            Indicateurs DG indisponibles
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-building-community"
                size="22"
              />
              Indicateurs direction
            </VCardTitle>
            <VCardSubtitle>Production et validation interne</VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText v-if="direction">
            <VRow dense>
              <VCol
                v-for="item in [
                  { t: 'Documents préparés', v: direction.prepared, c: 'primary' },
                  { t: 'En validation interne', v: direction.in_validation, c: 'info' },
                  { t: 'Transmis DG', v: direction.sent_dg, c: 'secondary' },
                  { t: 'Retournés', v: direction.returned, c: 'warning' },
                  { t: 'Validés', v: direction.validated, c: 'success' },
                  { t: 'En retard', v: direction.overdue, c: 'error' },
                ]"
                :key="item.t"
                cols="6"
              >
                <div class="pa-3 rounded border">
                  <div class="text-caption text-medium-emphasis mb-1">
                    {{ item.t }}
                  </div>
                  <div
                    class="text-h5 font-weight-bold"
                    :class="`text-${item.c}`"
                  >
                    {{ item.v ?? '—' }}
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText
            v-else-if="!loading"
            class="parapheur-empty"
          >
            Indicateurs direction indisponibles
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
