<script setup lang="ts">
import AnnouncementForm from '@/components/announcements/AnnouncementForm.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Announcement',
  },
})

const route = useRoute()
const title = ref('Modifier l\'annonce')

onMounted(async () => {
  const item = await $api<any>(`/announcements/${route.params.id}`)
  title.value = item.title || title.value
})
</script>

<template>
  <ParapheurPageHeader
    :title="title"
    subtitle="La publication reprend le message tel que vous le laissez."
    icon="tabler-speakerphone"
  >
    <template #actions>
      <VBtn
        variant="text"
        :to="{ name: 'annonces-id', params: { id: route.params.id } }"
      >
        Voir
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <AnnouncementForm :announcement-id="Number(route.params.id)" />
</template>
