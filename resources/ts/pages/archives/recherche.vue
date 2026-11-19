<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { recordStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const router = useRouter()
const { loading, fetchRecords } = useArchives()
const q = ref('')
const items = ref<any[]>([])
const searched = ref(false)

async function search() {
  searched.value = true
  const data = await fetchRecords({ q: q.value, per_page: 50 })
  items.value = data.data || []
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche avancée"
      subtitle="Recherche sécurisée dans les archives autorisées"
      icon="tabler-search"
    />
    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex gap-3 align-center">
        <VTextField
          v-model="q"
          label="Référence, titre, identifiant permanent…"
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
    <VCard
      v-if="searched"
      class="parapheur-section-card"
    >
      <VList>
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="item.title"
          :subtitle="[item.permanent_id, item.reference, item.structure?.code].filter(Boolean).join(' · ')"
          @click="router.push({ name: 'archives-records-id', params: { id: item.id } })"
        >
          <template #append>
            <VChip
              size="small"
              label
              color="secondary"
            >
              {{ recordStatusLabels[item.status] || item.status }}
            </VChip>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-if="!items.length"
        class="text-medium-emphasis"
      >
        Aucun résultat.
      </VCardText>
    </VCard>
  </div>
</template>
