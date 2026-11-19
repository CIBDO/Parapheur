<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'ArchivesDisposition' } })

const {
  loading,
  fetchDispositions,
  getJson,
  authorizeDisposition,
  refuseDisposition,
  executeDisposition,
} = useArchives()

const items = ref<any[]>([])
const selected = ref<any | null>(null)

async function load() {
  const data = await fetchDispositions()
  items.value = data.data || []
}

async function open(id: number) {
  selected.value = await getJson(`/archives/dispositions/${id}`)
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Sort final"
      subtitle="Proposition / autorisation / exécution séparées — aucune destruction one-click"
      icon="tabler-gavel"
    />

    <VRow>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="parapheur-section-card">
          <VList :loading="loading">
            <VListItem
              v-for="d in items"
              :key="d.id"
              :title="d.reference"
              :subtitle="`${d.title} · ${d.status} · ${d.items_count || 0} pièces`"
              @click="open(d.id)"
            />
            <VListItem
              v-if="!items.length"
              title="Aucune proposition"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="7"
      >
        <VCard
          v-if="selected"
          class="parapheur-section-card"
        >
          <VCardItem>
            <VCardTitle>{{ selected.reference }}</VCardTitle>
            <VCardSubtitle>{{ selected.status }} · PV {{ selected.pv_reference || '—' }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VBtn
                v-if="selected.status === 'en_validation' || selected.status === 'proposition'"
                color="success"
                @click="authorizeDisposition(selected.id).then(() => open(selected.id)).then(load)"
              >
                Autoriser
              </VBtn>
              <VBtn
                v-if="selected.status === 'en_validation' || selected.status === 'proposition'"
                color="error"
                @click="refuseDisposition(selected.id, 'Refus autorité').then(() => open(selected.id)).then(load)"
              >
                Refuser
              </VBtn>
              <VBtn
                v-if="selected.status === 'autorisee'"
                color="warning"
                @click="executeDisposition(selected.id).then(() => open(selected.id)).then(load)"
              >
                Exécuter
              </VBtn>
            </div>
            <VList>
              <VListItem
                v-for="item in selected.items || []"
                :key="item.id"
                :title="item.record?.permanent_id || String(item.record_id)"
                :subtitle="`${item.status} · ${item.record?.title || ''}`"
              />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
