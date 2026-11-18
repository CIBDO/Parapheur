<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

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
          :subtitle="`${item.permanent_id} · ${item.reference || 'sans référence'}`"
          @click="router.push({ name: 'archives-records-id', params: { id: item.id } })"
        />
        <VListItem
          v-if="!items.length"
          title="Aucun résultat"
        />
      </VList>
    </VCard>
  </div>
</template>
