<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import CorrespondenceDetailView from '@/components/courrier/CorrespondenceDetailView.vue'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Courrier',
  },
})

const route = useRoute()
const router = useRouter()
const { correspondence, loading, fetchCorrespondence, error } = useCorrespondence()
const id = Number(route.params.id)
const loadError = ref<string | null>(null)

onMounted(async () => {
  try {
    await fetchCorrespondence(id)
  }
  catch (e: any) {
    const status = e?.statusCode || e?.response?.status || e?.status
    loadError.value = status === 403
      ? 'Accès refusé à ce courrier.'
      : (error.value || e?.message || 'Impossible de charger le courrier.')
  }
})

async function refresh() {
  loadError.value = null
  try {
    await fetchCorrespondence(id)
  }
  catch (e: any) {
    const status = e?.statusCode || e?.response?.status || e?.status
    loadError.value = status === 403
      ? 'Accès refusé à ce courrier.'
      : (error.value || e?.message || 'Impossible de charger le courrier.')
  }
}
</script>

<template>
  <div
    v-if="loadError"
    class="pa-6"
  >
    <VAlert
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ loadError }}
    </VAlert>
    <VBtn
      variant="tonal"
      prepend-icon="tabler-arrow-left"
      @click="router.push({ name: 'courrier-entrants' })"
    >
      Retour aux entrants
    </VBtn>
  </div>
  <div v-else-if="correspondence && !loading">
    <CorrespondenceDetailView
      :correspondence="correspondence"
      direction="entrant"
      @refresh="refresh"
    />
  </div>
  <div
    v-else
    class="text-center py-10"
  >
    <VProgressCircular indeterminate />
  </div>
</template>
