<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const { loading, fetchSupervision, fetchPackages, verifyBatch } = useArchives()
const supervision = ref<Record<string, any>>({})
const packages = ref<any[]>([])
const batchResult = ref<Record<string, any> | null>(null)

async function load() {
  const [sup, pkgs] = await Promise.all([
    fetchSupervision(),
    fetchPackages({ per_page: 20 }),
  ])
  supervision.value = sup
  packages.value = pkgs.data || []
}

async function runBatch() {
  batchResult.value = await verifyBatch(50)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Préservation numérique"
      subtitle="Paquets OAIS (SIP/AIP/DIP), intégrité, réplication et supervision"
      icon="tabler-shield-lock"
    >
      <template #actions>
        <VBtn
          color="primary"
          :loading="loading"
          @click="runBatch"
        >
          Lancer contrôle d’intégrité
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow class="mb-4">
      <VCol
        v-for="key in ['integrity_failures', 'records_at_risk', 'packages_sealed', 'packages_replicated', 'locations_saturated', 'loans_overdue']"
        :key="key"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard class="parapheur-section-card">
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ key }}
            </div>
            <div class="text-h5">
              {{ supervision[key] ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VAlert
      v-if="batchResult"
      class="mb-4"
      type="info"
      variant="tonal"
    >
      Batch : {{ batchResult.checked }} contrôlés — OK {{ batchResult.ok }} — échecs {{ batchResult.failed }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Paquets archivistiques</VCardTitle>
          </VCardItem>
          <VList :loading="loading">
            <VListItem
              v-for="pkg in packages"
              :key="pkg.id"
              :title="`${pkg.reference} · ${pkg.package_type}`"
              :subtitle="`${pkg.status} · ${pkg.record?.permanent_id || '—'} · ${pkg.package_checksum?.slice(0, 12) || 'sans checksum'}…`"
            />
            <VListItem
              v-if="!packages.length"
              title="Aucun paquet"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Échecs récents</VCardTitle>
          </VCardItem>
          <VList>
            <VListItem
              v-for="fail in (supervision.recent_failures || [])"
              :key="fail.id"
              :title="fail.record?.permanent_id || `check #${fail.id}`"
              :subtitle="`${fail.result} · ${fail.message || ''}`"
            />
            <VListItem
              v-if="!(supervision.recent_failures || []).length"
              title="Aucun échec récent"
            />
          </VList>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
