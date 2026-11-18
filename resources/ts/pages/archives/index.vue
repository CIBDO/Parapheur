<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchDashboard } = useArchives()
const counts = ref<Record<string, number>>({})
const pending = ref<any[]>([])
const recent = ref<any[]>([])

const cards = computed(() => [
  { key: 'records_total', title: 'Archives', icon: 'tabler-archive', color: 'primary', to: 'archives-explorer' },
  { key: 'transfers_pending', title: 'À contrôler', icon: 'tabler-file-check', color: 'warning', to: 'archives-versements-a-controler' },
  { key: 'transfers_mine', title: 'Mes versements', icon: 'tabler-upload', color: 'info', to: 'archives-mes-versements' },
  { key: 'records_conserved', title: 'Conservées', icon: 'tabler-lock', color: 'success', to: 'archives-explorer' },
])

onMounted(async () => {
  const data = await fetchDashboard()
  counts.value = data.counts || {}
  pending.value = data.pending_transfers || []
  recent.value = data.recent_records || []
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
                {{ loading ? '…' : (counts[card.key] ?? 0) }}
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
              Aucune archive consultable pour le moment.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
