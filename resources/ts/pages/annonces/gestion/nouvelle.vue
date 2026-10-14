<script setup lang="ts">
import AnnouncementForm from '@/components/announcements/AnnouncementForm.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Announcement',
  },
})
</script>

<template>
  <ParapheurPageHeader
    title="Nouvelle annonce"
    subtitle="Un titre, un message, les destinataires"
    icon="tabler-speakerphone"
  />
  <AnnouncementForm />
</template>
