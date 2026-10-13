<script setup lang="ts">
import AnnouncementForm from '@/components/announcements/AnnouncementForm.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useAnnouncementStore } from '@/stores/announcements'

definePage({
  meta: {
    action: 'create',
    subject: 'Announcement',
  },
})

const route = useRoute()
const store = useAnnouncementStore()
const item = ref<any>(null)
const message = ref('')
const error = ref('')

const labels: Record<string, string> = {
  draft: 'Brouillon',
  pending_validation: 'En validation',
  to_correct: 'À corriger',
  approved: 'Approuvée',
  scheduled: 'Programmée',
  published: 'Publiée',
  expired: 'Expirée',
  archived: 'Archivée',
  rejected: 'Rejetée',
  cancelled: 'Annulée',
}

async function load() {
  item.value = await $api(`/announcements/${route.params.id}`)
}

async function act(path: string, body?: Record<string, unknown>) {
  error.value = ''
  message.value = ''
  try {
    item.value = await $api(`/announcements/${route.params.id}/${path}`, { method: 'POST', body })
    message.value = 'Action enregistrée.'
    store.invalidate()
    await store.load(true)
  }
  catch (err: any) {
    error.value = err?.data?.message ?? 'Action refusée.'
  }
}

onMounted(load)
</script>

<template>
  <ParapheurPageHeader
    :title="item?.title || 'Annonce'"
    :subtitle="item ? `${item.reference} · ${labels[item.status] || item.status}` : ''"
    icon="tabler-speakerphone"
  >
    <template #actions>
      <VBtn variant="text" :to="{ name: 'annonces-id', params: { id: route.params.id } }">
        Aperçu
      </VBtn>
      <VBtn
        v-if="$can('read', 'AnnouncementStats')"
        variant="tonal"
        :to="{ name: 'annonces-gestion-id-accuses', params: { id: route.params.id } }"
      >
        Accusés
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <VAlert v-if="message" type="success" variant="tonal" class="mb-4">
    {{ message }}
  </VAlert>
  <VAlert v-if="error" type="error" variant="tonal" class="mb-4">
    {{ error }}
  </VAlert>

  <div v-if="item" class="d-flex flex-wrap gap-2 mb-4">
    <VBtn
      v-if="['draft', 'to_correct', 'rejected'].includes(item.status)"
      @click="act('submit')"
    >
      Soumettre
    </VBtn>
    <VBtn
      v-if="item.status === 'pending_validation' && $can('validate', 'Announcement')"
      color="success"
      @click="act('approve')"
    >
      Approuver
    </VBtn>
    <VBtn
      v-if="item.status === 'pending_validation' && $can('validate', 'Announcement')"
      color="warning"
      variant="tonal"
      @click="act('request-correction', { comment: 'Merci de corriger le texte.' })"
    >
      Demander une correction
    </VBtn>
    <VBtn
      v-if="item.status === 'pending_validation' && $can('validate', 'Announcement')"
      color="error"
      variant="tonal"
      @click="act('reject', { comment: 'Annonce rejetée.' })"
    >
      Rejeter
    </VBtn>
    <VBtn
      v-if="$can('validate', 'Announcement') && (item.status === 'approved' || item.status === 'scheduled' || (item.status === 'draft' && item.category?.validation_circuit === 'direct'))"
      @click="act('publish')"
    >
      Publier
    </VBtn>
    <VBtn
      v-if="item.status === 'published' && $can('validate', 'Announcement')"
      variant="tonal"
      color="warning"
      @click="act('unpublish')"
    >
      Dépublier
    </VBtn>
    <VBtn
      v-if="['published', 'expired'].includes(item.status) && ($can('validate', 'Announcement') || $can('manage', 'Announcement'))"
      variant="text"
      @click="act('archive')"
    >
      Archiver
    </VBtn>
  </div>

  <AnnouncementForm v-if="item" :announcement-id="Number(route.params.id)" @saved="load" />

  <VCard v-if="item?.revisions?.length" class="mt-4">
    <VCardTitle>Révisions</VCardTitle>
    <VCardText>
      <div v-for="revision in item.revisions" :key="revision.version" class="mb-2">
        Version {{ revision.version }} — {{ revision.title }}
        <span class="text-caption">({{ revision.change_note }})</span>
      </div>
    </VCardText>
  </VCard>
</template>
