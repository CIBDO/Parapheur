<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Announcement',
  },
})

const route = useRoute()
const items = ref<any[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const filters = reactive({
  q: '',
  category_id: null as number | null,
  importance_id: null as number | null,
  status: '',
  unread: false,
  acknowledgement: false,
  archive: false,
})
const categories = ref<any[]>([])
const importances = ref<any[]>([])

const statusItems = [
  { title: 'Publiée', value: 'published' },
  { title: 'Expirée', value: 'expired' },
  { title: 'Archivée', value: 'archived' },
]

async function load() {
  loading.value = true
  const query: Record<string, string> = {}
  if (filters.q)
    query.q = filters.q
  if (filters.category_id)
    query.category_id = String(filters.category_id)
  if (filters.importance_id)
    query.importance_id = String(filters.importance_id)
  if (filters.status)
    query.status = filters.status
  if (filters.unread || route.query.unread)
    query.unread = '1'
  if (filters.acknowledgement || route.query.acknowledgement)
    query.acknowledgement = '1'
  if (filters.archive || route.query.archive)
    query.archive = '1'
  const page = await $api<any>('/announcements', { query })
  items.value = page.data
  meta.value = page.meta
  loading.value = false
}

onMounted(async () => {
  filters.acknowledgement = route.query.acknowledgement === '1'
  filters.archive = route.query.archive === '1'
  filters.unread = route.query.unread === '1'
  ;[categories.value, importances.value] = await Promise.all([
    $api('/announcement-categories'),
    $api('/announcement-importances'),
  ])
  await load()
})
</script>

<template>
  <ParapheurPageHeader
    title="Annonces"
    subtitle="Communications internes de la DGTCP"
    icon="tabler-speakerphone"
  >
    <template #actions>
      <VBtn
        v-if="$can('create', 'Announcement')"
        :to="{ name: 'annonces-gestion-nouvelle' }"
      >
        Nouvelle annonce
      </VBtn>
    </template>
  </ParapheurPageHeader>

  <VCard class="mb-4">
    <VCardText>
      <VRow dense>
        <VCol cols="12" md="4">
          <AppTextField v-model="filters.q" label="Recherche" @keyup.enter="load" />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppSelect v-model="filters.category_id" :items="categories" item-title="name" item-value="id" label="Catégorie" clearable />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppSelect v-model="filters.importance_id" :items="importances" item-title="name" item-value="id" label="Importance" clearable />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppSelect v-model="filters.status" :items="statusItems" label="Statut" clearable />
        </VCol>
        <VCol cols="12" md="2" class="d-flex align-center">
          <VBtn block @click="load">
            Filtrer
          </VBtn>
        </VCol>
        <VCol cols="12" class="d-flex flex-wrap gap-4">
          <VSwitch v-model="filters.unread" label="Non consultées" density="compact" @update:model-value="load" />
          <VSwitch v-model="filters.acknowledgement" label="À confirmer" density="compact" @update:model-value="load" />
          <VSwitch v-model="filters.archive" label="Archives" density="compact" @update:model-value="load" />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>

  <div v-if="loading" class="text-center py-8">
    <VProgressCircular indeterminate />
  </div>
  <VAlert v-else-if="!items.length" type="info" variant="tonal">
    Aucune annonce ne correspond à votre sélection.
  </VAlert>
  <VRow v-else>
    <VCol v-for="item in items" :key="item.id" cols="12">
      <VCard :to="{ name: 'annonces-id', params: { id: item.id } }">
        <VCardItem>
          <template #prepend>
            <VAvatar :color="item.importance?.color || 'primary'" variant="tonal">
              <VIcon icon="tabler-speakerphone" />
            </VAvatar>
          </template>
          <VCardTitle>{{ item.title }}</VCardTitle>
          <VCardSubtitle>
            {{ item.category?.name }} · {{ item.structure?.name }} · {{ item.reference }}
          </VCardSubtitle>
          <template #append>
            <div class="d-flex gap-2">
              <VChip v-if="!item.viewed" size="small" color="primary" variant="tonal">
                Non lue
              </VChip>
              <VChip v-if="item.acknowledgement_required && !item.acknowledged" size="small" color="warning">
                À confirmer
              </VChip>
              <VChip size="small" :color="item.importance?.color || 'primary'" variant="tonal">
                {{ item.importance?.name }}
              </VChip>
            </div>
          </template>
        </VCardItem>
        <VCardText v-if="item.summary">
          {{ item.summary }}
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <div class="text-caption mt-4 text-medium-emphasis">
    {{ meta.total }} annonce(s)
  </div>
</template>
