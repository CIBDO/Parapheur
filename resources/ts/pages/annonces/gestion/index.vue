<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Announcement',
  },
})

const items = ref<any[]>([])
const loading = ref(true)

const labels: Record<string, string> = {
  draft: 'Brouillon',
  pending_validation: 'Brouillon',
  to_correct: 'Brouillon',
  approved: 'Prête',
  scheduled: 'Programmée',
  published: 'Publiée',
  expired: 'Terminée',
  archived: 'Archivée',
  rejected: 'Brouillon',
  cancelled: 'Retirée',
}

onMounted(async () => {
  const page = await $api<any>('/announcements/managed')
  items.value = page.data
  loading.value = false
})
</script>

<template>
  <ParapheurPageHeader
    title="Gestion des annonces"
    subtitle="Les annonces que vous avez rédigées"
    icon="tabler-edit"
  >
    <template #actions>
      <VBtn :to="{ name: 'annonces-gestion-nouvelle' }">
        Nouvelle annonce
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <VAlert v-if="!loading && !items.length" type="info" variant="tonal">
    Aucune annonce à gérer.
  </VAlert>
  <VCard v-else>
    <VTable>
      <thead>
        <tr>
          <th>Référence</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Structure</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>{{ item.reference }}</td>
          <td>{{ item.title }}</td>
          <td>{{ labels[item.status] || item.status }}</td>
          <td>{{ item.structure?.name }}</td>
          <td class="text-end">
            <VBtn size="small" variant="text" :to="{ name: 'annonces-gestion-id', params: { id: item.id } }">
              Ouvrir
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
