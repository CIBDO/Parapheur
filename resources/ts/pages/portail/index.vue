<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'PortalApp',
  },
})

interface PortalAppItem {
  id: number
  code: string
  name: string
  url: string
  icon: string | null
  color: string | null
  description: string | null
  category: string | null
  sort_order: number
  is_active: boolean
  open_in_new_tab: boolean
}

const items = ref<PortalAppItem[]>([])
const categories = ref<string[]>([])
const loading = ref(false)
const errorMessage = ref('')
const search = ref('')
const selectedCategory = ref<string | null>(null)

const categoryItems = computed(() =>
  categories.value.map(c => ({ title: c, value: c })),
)

const grouped = computed(() => {
  const map = new Map<string, PortalAppItem[]>()
  for (const app of items.value) {
    const key = app.category?.trim() || 'Autres'
    if (!map.has(key))
      map.set(key, [])
    map.get(key)!.push(app)
  }

  return Array.from(map.entries()).map(([category, apps]) => ({ category, apps }))
})

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Chargement impossible')
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const query: Record<string, string> = { active_only: '1' }
    if (search.value.trim())
      query.q = search.value.trim()
    if (selectedCategory.value)
      query.category = selectedCategory.value

    const [apps, cats] = await Promise.all([
      $api<PortalAppItem[]>('/portal-apps', { query }),
      $api<string[]>('/portal-apps/categories'),
    ])
    items.value = Array.isArray(apps) ? apps : []
    categories.value = Array.isArray(cats) ? cats : []
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    loading.value = false
  }
}

const openApp = (app: PortalAppItem) => {
  if (!app.url)
    return
  if (app.open_in_new_tab)
    window.open(app.url, '_blank', 'noopener,noreferrer')
  else
    window.location.href = app.url
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Portail des applications"
      subtitle="Accédez aux applications et outils de l’institution"
      icon="tabler-apps"
    >
      <template #actions>
        <VBtn
          v-if="$can('manage', 'PortalApp')"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-settings"
          :to="{ name: 'portail-admin' }"
        >
          Configurer
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="mb-4">
      <VCardText>
        <VRow dense>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="search"
              label="Rechercher une application"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
              @click:clear="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="selectedCategory"
              :items="categoryItems"
              label="Catégorie"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              block
              color="primary"
              @click="load"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <VAlert
      v-else-if="!items.length"
      type="info"
      variant="tonal"
    >
      Aucune application disponible pour le moment.
    </VAlert>

    <template v-else>
      <div
        v-for="group in grouped"
        :key="group.category"
        class="mb-6"
      >
        <h5 class="text-h6 mb-3">
          {{ group.category }}
        </h5>
        <VRow>
          <VCol
            v-for="app in group.apps"
            :key="app.id"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <VCard
              class="h-100 portal-app-card"
              hover
              @click="openApp(app)"
            >
              <VCardText class="d-flex flex-column gap-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="app.color || 'primary'"
                    variant="tonal"
                    size="46"
                    rounded
                  >
                    <VIcon
                      :icon="app.icon || 'tabler-apps'"
                      size="24"
                    />
                  </VAvatar>
                  <div class="flex-grow-1 min-w-0">
                    <div class="text-subtitle-1 font-weight-medium text-truncate">
                      {{ app.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ app.code }}
                    </div>
                  </div>
                  <VIcon
                    icon="tabler-external-link"
                    size="18"
                    class="text-disabled"
                  />
                </div>
                <p
                  v-if="app.description"
                  class="text-body-2 text-medium-emphasis mb-0 portal-app-desc"
                >
                  {{ app.description }}
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>
  </div>
</template>

<style scoped>
.portal-app-card {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.portal-app-card:hover {
  transform: translateY(-2px);
}

.portal-app-desc {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
