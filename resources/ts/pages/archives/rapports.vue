<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const { loading, fetchResponsibleReport, fetchDgReport, exportMetadata } = useArchives()
const responsible = ref<Record<string, any>>({})
const dg = ref<Record<string, any> | null>(null)
const exportCount = ref<number | null>(null)

onMounted(async () => {
  responsible.value = await fetchResponsibleReport()
  try {
    dg.value = await fetchDgReport()
  }
  catch {
    dg.value = null
  }
})

async function doExport() {
  const data = await exportMetadata()
  exportCount.value = (data.data || []).length
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Rapports archives"
      subtitle="Indicateurs responsables et vision consolidée DG (sans contenus confidentiels)"
      icon="tabler-chart-bar"
    >
      <template #actions>
        <VBtn
          color="primary"
          variant="tonal"
          @click="doExport"
        >
          Exporter métadonnées
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow :loading="loading">
      <VCol
        v-for="key in ['records_total', 'boxes_total', 'transfers_pending', 'retention_due', 'holds_active', 'dispositions_pending', 'loans_open', 'locations_saturated']"
        :key="key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard class="parapheur-section-card">
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ key }}
            </div>
            <div class="text-h5">
              {{ responsible[key] ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard
      v-if="dg"
      class="parapheur-section-card mt-4"
    >
      <VCardItem>
        <VCardTitle>Vue DG</VCardTitle>
      </VCardItem>
      <VCardText>
        <div>Versements en attente : {{ dg.transfers?.pending ?? 0 }}</div>
        <div>Emplacements saturés : {{ dg.occupation?.saturated ?? 0 }}</div>
        <div>Échéances conservation : {{ dg.compliance?.retention_due ?? 0 }}</div>
        <div>Prêts en retard : {{ dg.loans_overdue ?? 0 }}</div>
      </VCardText>
    </VCard>

    <div
      v-if="exportCount !== null"
      class="mt-3 text-medium-emphasis"
    >
      Export : {{ exportCount }} fiches métadonnées.
    </div>
  </div>
</template>
