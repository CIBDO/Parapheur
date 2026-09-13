<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, labelOf, originLabels, statusColor, statusLabels } from '@/utils/gedUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Ged',
  },
})

const router = useRouter()
const loading = ref(true)
const counts = ref<Record<string, number>>({})
const indicators = ref<any>(null)
const recent = ref<any[]>([])

const tiles = computed(() => [
  { key: 'actifs', title: 'Documents actifs', icon: 'tabler-folder', color: 'primary', to: 'ged-documents' },
  { key: 'archives', title: 'Archivés', icon: 'tabler-archive', color: 'secondary', to: 'ged-archives' },
  { key: 'ce_mois', title: 'Ajoutés ce mois', icon: 'tabler-calendar-plus', color: 'info', to: 'ged-documents' },
  { key: 'a_traiter', title: 'À traiter', icon: 'tabler-inbox', color: 'warning', to: 'ged-a-traiter' },
  { key: 'favoris', title: 'Mes favoris', icon: 'tabler-star', color: 'warning', to: 'ged-favoris' },
  { key: 'non_classes', title: 'Non classés', icon: 'tabler-folder-question', color: 'warning', to: 'ged-documents' },
  { key: 'mes_documents', title: 'Mes documents', icon: 'tabler-folder-user', color: 'success', to: 'ged-mes-documents' },
  { key: 'geles', title: 'Gelés', icon: 'tabler-lock', color: 'error', to: 'ged-documents' },
])

onMounted(async () => {
  try {
    const res = await $api('/ged/dashboard')
    counts.value = res.counts || {}
    indicators.value = res.indicators || null
    recent.value = res.recent || []
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="GED — Tableau de bord"
      subtitle="Patrimoine documentaire du Bureau Numérique DGTCP"
      icon="tabler-folders"
    >
      <VBtn
        color="primary"
        prepend-icon="tabler-file-plus"
        :to="{ name: 'ged-nouveau' }"
      >
        Nouveau document
      </VBtn>
      <VBtn
        variant="tonal"
        prepend-icon="tabler-search"
        class="ms-2"
        :to="{ name: 'ged-recherche' }"
      >
        Rechercher
      </VBtn>
    </ParapheurPageHeader>

    <VRow class="mb-6">
      <VCol
        v-for="tile in tiles"
        :key="tile.key"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="parapheur-folder-tile cursor-pointer"
          @click="router.push({ name: tile.to })"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="tile.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="tile.icon" />
            </VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ loading ? '…' : (counts[tile.key] ?? 0) }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ tile.title }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow
      v-if="indicators"
      class="mb-6"
    >
      <VCol
        cols="12"
        md="4"
      >
        <VCard class="parapheur-section-card">
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              Volume accessible
            </div>
            <div class="text-h4">
              {{ indicators.volume_total }}
            </div>
            <div class="text-body-2 mt-2">
              Non classés : {{ indicators.unclassified }} · Métadonnées incomplètes : {{ indicators.missing_metadata }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Top types</VCardTitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="row in (indicators.by_type || []).slice(0, 8)"
                :key="row.type_id"
                label
              >
                {{ row.type || '—' }} : {{ row.total }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="parapheur-section-card">
      <VCardItem>
        <VCardTitle>Récemment ajoutés</VCardTitle>
      </VCardItem>
      <VDivider />
      <VTable>
        <thead>
          <tr>
            <th>Référence</th>
            <th>Objet</th>
            <th>Origine</th>
            <th>Statut</th>
            <th>Date</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="doc in recent"
            :key="doc.id"
          >
            <td>{{ doc.reference }}</td>
            <td>{{ doc.title || doc.object }}</td>
            <td>{{ labelOf(originLabels, doc.origin) }}</td>
            <td>
              <VChip
                size="small"
                :color="statusColor(doc.status)"
                label
              >
                {{ labelOf(statusLabels, doc.status) }}
              </VChip>
            </td>
            <td>{{ formatDateFr(doc.document_date || doc.created_at) }}</td>
            <td>
              <VBtn
                size="small"
                variant="text"
                :to="{ name: 'ged-id', params: { id: String(doc.id) } }"
              >
                Ouvrir
              </VBtn>
            </td>
          </tr>
          <tr v-if="!loading && !recent.length">
            <td
              colspan="6"
              class="text-center text-medium-emphasis py-6"
            >
              Aucun document récent accessible.
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
