<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const router = useRouter()
const q = ref('')
const loading = ref(false)
const results = ref<any[]>([])

const provenanceColor: Record<string, string> = {
  ged: 'primary',
  personal: 'success',
  shared: 'warning',
  reference: 'info',
}

async function search() {
  loading.value = true
  try {
    const res = await $api(`/search?q=${encodeURIComponent(q.value)}`)
    results.value = res.data || []
  }
  finally {
    loading.value = false
  }
}

function openResult(item: any) {
  if (item.url)
    router.push(item.url)
}

onMounted(() => {
  const route = useRoute()
  if (route.query.q) {
    q.value = String(route.query.q)
    search()
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche documentaire"
      subtitle="GED · Mon espace · Partagés · Références"
      icon="tabler-search"
    />

    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex gap-3">
        <VTextField
          v-model="q"
          label="Rechercher…"
          prepend-inner-icon="tabler-search"
          hide-details
          @keyup.enter="search"
        />
        <VBtn
          color="primary"
          :loading="loading"
          @click="search"
        >
          Rechercher
        </VBtn>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VList v-if="results.length">
        <VListItem
          v-for="(item, idx) in results"
          :key="`${item.provenance}-${item.id}-${idx}`"
          :title="item.title"
          :subtitle="item.subtitle"
          @click="openResult(item)"
        >
          <template #prepend>
            <VChip
              size="small"
              :color="provenanceColor[item.provenance] || 'secondary'"
              label
              class="me-3"
            >
              {{ item.provenance_label }}
            </VChip>
          </template>
          <template #append>
            <span class="text-caption text-medium-emphasis">
              {{ formatDateFr(item.updated_at) }}
            </span>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        {{ loading ? 'Recherche…' : 'Aucun résultat. Lancez une recherche.' }}
      </VCardText>
    </VCard>
  </div>
</template>
