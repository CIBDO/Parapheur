<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing' },
})

const router = useRouter()
const { fetchServiceCatalog } = useTicketing()

const loading = ref(true)
const catalogs = ref<any[]>([])
const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q)
    return catalogs.value

  return catalogs.value
    .map(cat => ({
      ...cat,
      items: (cat.items || []).filter((i: any) =>
        String(i.name || '').toLowerCase().includes(q)
        || String(i.code || '').toLowerCase().includes(q)
        || String(i.description || '').toLowerCase().includes(q),
      ),
    }))
    .filter(cat =>
      cat.items.length
      || String(cat.name || '').toLowerCase().includes(q),
    )
})

onMounted(async () => {
  try {
    const res = await fetchServiceCatalog()
    catalogs.value = listItems(res).length ? listItems(res) : (Array.isArray(res) ? res : [])
  }
  finally {
    loading.value = false
  }
})

function requestService(item: any) {
  router.push({ name: 'ticketing-nouveau', query: { service_item_id: item.id } })
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Catalogue de services"
      subtitle="Parcourir les offres et créer une demande"
    >
      <template #actions>
        <AppTextField
          v-model="search"
          label="Filtrer"
          prepend-inner-icon="tabler-search"
          hide-details
          style="min-inline-size: 240px"
        />
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <div
      v-else-if="!filtered.length"
      class="text-center py-10 text-medium-emphasis"
    >
      Aucun service trouvé.
    </div>

    <div
      v-for="catalog in filtered"
      :key="catalog.id"
      class="mb-6"
    >
      <h3 class="text-h6 mb-3">
        {{ catalog.name }}
      </h3>
      <p
        v-if="catalog.description"
        class="text-body-2 text-medium-emphasis mb-3"
      >
        {{ catalog.description }}
      </p>
      <VRow dense>
        <VCol
          v-for="item in (catalog.items || [])"
          :key="item.id"
          cols="12"
          md="6"
          lg="4"
        >
          <VCard class="h-100">
            <VCardText>
              <div class="text-caption text-medium-emphasis mb-1">
                {{ item.code }}
              </div>
              <div class="text-body-1 font-weight-medium mb-2">
                {{ item.name }}
              </div>
              <div
                v-if="item.description"
                class="text-body-2 text-medium-emphasis mb-4"
              >
                {{ item.description }}
              </div>
              <VBtn
                color="primary"
                variant="tonal"
                size="small"
                prepend-icon="tabler-plus"
                @click="requestService(item)"
              >
                Demander
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
