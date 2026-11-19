<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchDashboard, fetchResponsibleReport } = useArchives()
const counts = ref<Record<string, number>>({})
const report = ref<Record<string, number>>({})
const pending = ref<any[]>([])
const recent = ref<any[]>([])

const cards = computed(() => [
  { key: 'records_total', title: 'Archives', icon: 'tabler-archive', color: 'primary', to: 'archives-explorer', source: 'counts' as const },
  { key: 'transfers_pending', title: 'À contrôler', icon: 'tabler-file-check', color: 'warning', to: 'archives-versements-controle', source: 'counts' as const },
  { key: 'transfers_mine', title: 'Mes versements', icon: 'tabler-upload', color: 'info', to: 'archives-mes-versements', source: 'counts' as const },
  { key: 'records_conserved', title: 'Conservées', icon: 'tabler-lock', color: 'success', to: 'archives-explorer', source: 'counts' as const },
  { key: 'boxes_total', title: 'Boîtes', icon: 'tabler-box', color: 'secondary', to: 'archives-boites', source: 'report' as const },
  { key: 'locations_saturated', title: 'Emplacements saturés', icon: 'tabler-alert-triangle', color: 'error', to: 'archives-occupation', source: 'report' as const },
  { key: 'loans_overdue', title: 'Prêts en retard', icon: 'tabler-clock-exclamation', color: 'warning', to: 'archives-prets', source: 'report' as const },
  { key: 'retention_due', title: 'Échéances conservation', icon: 'tabler-hourglass', color: 'info', to: 'archives-conservation', source: 'report' as const },
])

function valueFor(card: { key: string, source: 'counts' | 'report' }) {
  if (card.source === 'report')
    return report.value[card.key] ?? 0
  return counts.value[card.key] ?? 0
}

onMounted(async () => {
  const [data, responsible] = await Promise.all([
    fetchDashboard(),
    fetchResponsibleReport().catch(() => ({})),
  ])
  counts.value = data.counts || {}
  pending.value = data.pending_transfers || []
  recent.value = data.recent_records || []
  report.value = {
    boxes_total: responsible.boxes_total ?? 0,
    locations_saturated: responsible.locations_saturated ?? 0,
    loans_overdue: responsible.loans_overdue ?? 0,
    retention_due: responsible.retention_due ?? 0,
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Archives institutionnelles"
      subtitle="Cycle de vie, versements et consultation des fonds"
      icon="tabler-building-warehouse"
    />

    <VRow class="mb-4">
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="parapheur-section-card cursor-pointer"
          @click="router.push({ name: card.to })"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="card.icon" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ card.title }}
              </div>
              <div class="text-h5">
                {{ loading ? '…' : valueFor(card) }}
              </div>
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
            <VCardTitle>Versements en attente</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="pending.length">
              <VListItem
                v-for="item in pending"
                :key="item.id"
                :title="item.reference"
                :subtitle="item.title || 'Sans intitulé'"
                @click="router.push({ name: 'archives-versements-id', params: { id: item.id } })"
              >
                <template #append>
                  <VChip
                    size="small"
                    :color="transferStatusColor(item.status)"
                    label
                  >
                    {{ transferStatusLabels[item.status] || item.status }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucun versement à contrôler.
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
            <VCardTitle>Archives récentes</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="recent.length">
              <VListItem
                v-for="item in recent"
                :key="item.id"
                :title="item.title"
                :subtitle="item.permanent_id"
                @click="router.push({ name: 'archives-records-id', params: { id: item.id } })"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              Aucune archive récente.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
