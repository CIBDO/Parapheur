<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useAnnouncementStore } from '@/stores/announcements'

definePage({
  meta: {
    action: 'read',
    subject: 'Announcement',
  },
})

const route = useRoute()
const store = useAnnouncementStore()
const item = ref<any>(null)
const error = ref('')
const loading = ref(true)

async function load() {
  loading.value = true
  error.value = ''
  try {
    item.value = await $api(`/announcements/${route.params.id}`)
  }
  catch {
    error.value = 'Cette annonce est introuvable ou vous n\'y avez pas accès.'
  }
  finally {
    loading.value = false
  }
}

async function acknowledge() {
  await $api(`/announcements/${route.params.id}/acknowledge`, { method: 'POST' })
  store.invalidate()
  await load()
  await store.load(true)
}

async function favorite() {
  await $api(`/announcements/${route.params.id}/favorite`, { method: 'POST' })
  await load()
}

async function copyLink() {
  await navigator.clipboard.writeText(`${window.location.origin}/annonces/${route.params.id}`)
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="text-center py-10">
    <VProgressCircular indeterminate />
  </div>
  <VAlert v-else-if="error" type="warning" variant="tonal">
    {{ error }}
  </VAlert>
  <div v-else-if="item">
    <ParapheurPageHeader
      :title="item.title"
      :subtitle="`${item.reference} · ${item.category?.name ?? ''}`"
      icon="tabler-speakerphone"
    >
      <template #actions>
        <VBtn variant="tonal" @click="favorite">
          {{ item.favorite ? 'Retirer des favoris' : 'Favori' }}
        </VBtn>
        <VBtn variant="text" @click="copyLink">
          Copier le lien
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="item.acknowledgement_required && !item.acknowledged"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      Cette annonce demande une confirmation de lecture. Ouvrir la page ne suffit pas.
      <template #append>
        <VBtn color="warning" @click="acknowledge">
          Confirmer la lecture
        </VBtn>
      </template>
    </VAlert>

    <VRow>
      <VCol cols="12" md="8">
        <VCard>
          <VCardText>
            <p v-if="item.summary" class="text-body-1 mb-4">
              {{ item.summary }}
            </p>
            <div class="announcement-body" v-html="item.body" />
          </VCardText>
        </VCard>
        <VCard v-if="item.attachments?.length" class="mt-4">
          <VCardTitle>Pièces jointes</VCardTitle>
          <VCardText>
            <VList>
              <VListItem
                v-for="file in item.attachments"
                :key="file.id"
                :to="{ name: 'ged-id', params: { id: file.document_id } }"
                :title="file.label || `Document ${file.document_id}`"
                prepend-icon="tabler-file"
              />
            </VList>
          </VCardText>
        </VCard>
        <VCard v-if="item.links?.length" class="mt-4">
          <VCardTitle>Liens internes</VCardTitle>
          <VCardText class="d-flex flex-column gap-2">
            <RouterLink v-for="link in item.links" :key="link.id" :to="link.url">
              {{ link.label }}
            </RouterLink>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText class="d-flex flex-column gap-2">
            <div><strong>Importance :</strong> {{ item.importance?.name }}</div>
            <div><strong>Structure :</strong> {{ item.structure?.name }}</div>
            <div><strong>Auteur :</strong> {{ item.author?.name }}</div>
            <div><strong>Statut :</strong> {{ item.status }}</div>
            <VBtn
              v-if="$can('create', 'Announcement')"
              variant="tonal"
              :to="{ name: 'annonces-gestion-id', params: { id: item.id } }"
            >
              Gestion
            </VBtn>
          </VCardText>
        </VCard>
        <VCard v-if="item.events?.length" class="mt-4">
          <VCardTitle>Historique</VCardTitle>
          <VCardText>
            <div v-for="event in item.events" :key="event.created_at + event.event" class="mb-2">
              <div class="font-weight-medium">{{ event.event }}</div>
              <div class="text-caption">{{ event.actor }} · {{ event.created_at }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.announcement-body :deep(table) {
  border-collapse: collapse;
  inline-size: 100%;
}

.announcement-body :deep(td),
.announcement-body :deep(th) {
  border: 1px solid rgba(0, 0, 0, 0.12);
  padding: 0.4rem;
}
</style>
