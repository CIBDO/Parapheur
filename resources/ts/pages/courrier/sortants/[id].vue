<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'
import CorrespondenceDetailView from '@/components/courrier/CorrespondenceDetailView.vue'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const route = useRoute()
const { correspondence, fetchCorrespondence, loading } = useCorrespondence()
const id = Number(route.params.id)

onMounted(() => fetchCorrespondence(id))

async function refresh() {
  await fetchCorrespondence(id)
}
</script>

<template>
  <div v-if="correspondence && !loading">
    <CorrespondenceDetailView 
      :correspondence="correspondence" 
      direction="sortant"
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
