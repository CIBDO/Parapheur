<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'export', subject: 'Archives' } })

const { loading, fetchResponsibleReport, fetchDgReport, exportMetadata } = useArchives()
const responsible = ref<Record<string, any>>({})
const dg = ref<Record<string, any> | null>(null)
const exportCount = ref<number | null>(null)

const metricLabels: Record<string, string> = {
  records_total: 'Archives totales',
  boxes_total: 'Boîtes',
  transfers_pending: 'Versements en attente',
  retention_due: 'Échéances de conservation',
  holds_active: 'Gels actifs',
  dispositions_pending: 'Sorts finaux en cours',
  loans_open: 'Prêts ouverts',
  locations_saturated: 'Emplacements saturés',
}

const metricKeys = Object.keys(metricLabels)

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
          :loading="loading"
          @click="doExport"
        >
          Exporter métadonnées
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        v-for="key in metricKeys"
        :key="key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard class="parapheur-section-card">
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ metricLabels[key] }}
            </div>
            <div class="text-h5">
              {{ responsible[key] ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow class="mt-2">
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Par typologie</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="(responsible.by_typology || []).length">
              <VListItem
                v-for="row in responsible.by_typology"
                :key="row.typology || 'sans'"
                :title="row.typology || 'Non renseignée'"
                :subtitle="`${row.total} archive(s)`"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucune répartition.
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Par structure</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="(responsible.by_structure || []).length">
              <VListItem
                v-for="row in responsible.by_structure"
                :key="row.structure_id"
                :title="row.structure?.name || row.structure?.code || `Structure #${row.structure_id}`"
                :subtitle="`${row.total} archive(s)`"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucune répartition.
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
