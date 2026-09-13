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

const loading = ref(true)
const viewed = ref<any[]>([])
const modified = ref<any[]>([])

onMounted(async () => {
  try {
    const res = await $api('/workspace/recent')
    viewed.value = res.viewed || []
    modified.value = res.modified || res.data || []
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Récents"
      subtitle="Consultés et modifiés récemment"
      icon="tabler-history"
    />
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Consultés</VCardTitle>
          </VCardItem>
          <VList v-if="viewed.length">
            <VListItem
              v-for="doc in viewed"
              :key="doc.id"
              :title="doc.title || doc.object"
              :subtitle="formatDateFr(doc.viewed_at || doc.updated_at)"
              prepend-icon="tabler-eye"
              :to="{ name: 'espace-documents-id', params: { id: doc.id } }"
            />
          </VList>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucun document consulté récemment.' }}
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Modifiés</VCardTitle>
          </VCardItem>
          <VList v-if="modified.length">
            <VListItem
              v-for="doc in modified"
              :key="doc.id"
              :title="doc.title || doc.object"
              :subtitle="formatDateFr(doc.updated_at)"
              prepend-icon="tabler-edit"
              :to="{ name: 'espace-documents-id', params: { id: doc.id } }"
            />
          </VList>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucune modification récente.' }}
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
