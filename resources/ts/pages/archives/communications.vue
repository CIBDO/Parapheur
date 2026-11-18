<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const {
  loading,
  fetchCommunications,
  authorizeCommunication,
  refuseCommunication,
} = useArchives()

const items = ref<any[]>([])
const refuseReason = ref('')

async function load() {
  const data = await fetchCommunications({ per_page: 50 })
  items.value = data.data || []
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Communications"
      subtitle="Demandes d’accès contrôlé aux archives"
      icon="tabler-share"
    />
    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="`${item.reference} · ${item.record?.title || item.record_id}`"
          :subtitle="`${item.status} · ${item.purpose || 'Sans motif'} · ${item.requester?.name || ''}`"
        >
          <template #append>
            <div class="d-flex gap-1">
              <VBtn
                v-if="item.status === 'demande'"
                size="small"
                color="success"
                @click="authorizeCommunication(item.id).then(load)"
              >
                Autoriser
              </VBtn>
              <VBtn
                v-if="item.status === 'demande'"
                size="small"
                color="error"
                @click="refuseReason = 'Refus'; refuseCommunication(item.id, refuseReason).then(load)"
              >
                Refuser
              </VBtn>
            </div>
          </template>
        </VListItem>
        <VListItem
          v-if="!items.length"
          title="Aucune demande"
        />
      </VList>
    </VCard>
  </div>
</template>
