<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Announcement',
  },
})

const items = ref<any[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const filters = reactive({
  q: '',
  category_id: null as number | null,
})
const categories = ref<any[]>([])

async function load() {
  loading.value = true
  const query: Record<string, string> = {}
  if (filters.q)
    query.q = filters.q
  if (filters.category_id)
    query.category_id = String(filters.category_id)
  const page = await $api<any>('/announcements', { query })
  items.value = page.data
  meta.value = page.meta
  loading.value = false
}

function when(value?: string | null) {
  if (!value)
    return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return ''

  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

function excerpt(item: any) {
  const text = (item.content || item.summary || '').replace(/\s+/g, ' ').trim()

  return text.length > 220 ? `${text.slice(0, 217)}…` : text
}

onMounted(async () => {
  categories.value = await $api('/announcement-categories')
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
        <VCol cols="12" md="6">
          <AppTextField v-model="filters.q" label="Rechercher une annonce" @keyup.enter="load" />
        </VCol>
        <VCol cols="12" md="4">
          <AppSelect v-model="filters.category_id" :items="categories" item-title="name" item-value="id" label="Catégorie" clearable @update:model-value="load" />
        </VCol>
        <VCol cols="12" md="2" class="d-flex align-center">
          <VBtn block @click="load">
            Voir
          </VBtn>
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
          <VCardTitle class="text-wrap">{{ item.title }}</VCardTitle>
          <VCardSubtitle>
            {{ [item.category?.name, when(item.published_at)].filter(Boolean).join(' · ') }}
          </VCardSubtitle>
          <template #append>
            <VChip v-if="item.acknowledgement_required && !item.acknowledged" size="small" color="warning">
              À confirmer
            </VChip>
          </template>
        </VCardItem>
        <VCardText v-if="excerpt(item)" class="text-body-2">
          {{ excerpt(item) }}
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <div class="text-caption mt-4 text-medium-emphasis">
    {{ meta.total }} annonce(s)
  </div>
</template>
