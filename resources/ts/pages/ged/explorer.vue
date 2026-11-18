<script setup lang="ts">
import GedBackButton from '@/components/ged/GedBackButton.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  publicationStatusColor,
  publicationStatusLabels,
} from '@/utils/gedUi'
import {
  confidentialityLabels,
  formatDateFr,
  labelOf,
} from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Ged',
  },
})

const router = useRouter()
const loading = ref(true)
const folders = ref<any[]>([])
const selectedFolder = ref<any | null>(null)
const breadcrumb = ref<Array<{ id: number; name: string }>>([])
const documents = ref<any[]>([])
const facets = ref<Record<string, Array<{ value: string | number; label: string; total: number }>>>({})
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const lastPage = ref(1)
const viewMode = ref<'list' | 'grid'>('list')
const q = ref('')
const facetOrigin = ref<string | null>(null)
const facetConfidentiality = ref<string | null>(null)

const flattenForTree = (nodes: any[]): any[] =>
  (nodes || []).map(n => ({
    ...n,
    title: n.name,
    children: n.children?.length ? flattenForTree(n.children) : undefined,
  }))

const treeItems = computed(() => flattenForTree(folders.value))

const loadTree = async () => {
  const res = await $api('/ged/folders')
  folders.value = res.data || []
}

const loadFolder = async (folderId: number | null, resetPage = true) => {
  if (resetPage)
    page.value = 1

  loading.value = true
  try {
    if (!folderId) {
      selectedFolder.value = null
      breadcrumb.value = []
      const res = await $api('/ged/catalog', {
        query: {
          page: page.value,
          per_page: perPage.value,
          sort: 'updated_at',
          dir: 'desc',
          catalog: 'institutional',
          q: q.value || undefined,
          origin: facetOrigin.value || undefined,
          confidentiality: facetConfidentiality.value || undefined,
        },
      })
      documents.value = res.data || []
      total.value = res.total || documents.value.length
      lastPage.value = res.last_page || 1
      facets.value = res.facets || {}

      return
    }

    const res = await $api(`/ged/folders/${folderId}`, {
      query: {
        page: page.value,
        per_page: perPage.value,
        catalog: 'institutional',
        q: q.value || undefined,
        sort: 'updated_at',
        dir: 'desc',
      },
    })
    selectedFolder.value = res.folder
    breadcrumb.value = res.breadcrumb || []
    documents.value = res.documents?.data || []
    total.value = res.documents?.total || documents.value.length
    lastPage.value = res.documents?.last_page || 1
    facets.value = {}
  }
  finally {
    loading.value = false
  }
}

const onSelectFolder = (item: any) => {
  if (item?.id)
    loadFolder(item.id)
}

const applyFacet = (key: 'origin' | 'confidentiality', value: string | number | null) => {
  if (key === 'origin')
    facetOrigin.value = value ? String(value) : null
  else
    facetConfidentiality.value = value ? String(value) : null
  loadFolder(selectedFolder.value?.id ?? null)
}

const openDoc = (id: number) => router.push({ name: 'ged-id', params: { id } })

watch(page, () => loadFolder(selectedFolder.value?.id ?? null, false))

const shortcuts = [
  { title: 'Mes dépôts', icon: 'tabler-folder-user', to: 'ged-mes-documents' },
  { title: 'Favoris', icon: 'tabler-star', to: 'ged-favoris' },
  { title: 'Récents', icon: 'tabler-history', to: 'ged-recents' },
  { title: 'À valider', icon: 'tabler-file-check', to: 'ged-a-valider' },
  { title: 'Recherche', icon: 'tabler-search', to: 'ged-recherche' },
]

onMounted(async () => {
  await loadTree()
  await loadFolder(null)
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Explorer"
      subtitle="Bibliothèque institutionnelle — documents versés uniquement (hors brouillons)"
      icon="tabler-folder-open"
    >
      <template #back>
        <GedBackButton to="ged" />
      </template>
      <VBtn
        variant="tonal"
        prepend-icon="tabler-search"
        :to="{ name: 'ged-recherche' }"
      >
        Recherche
      </VBtn>
      <VBtn
        color="primary"
        prepend-icon="tabler-upload"
        :to="{ name: 'ged-nouveau' }"
      >
        Déposer
      </VBtn>
    </ParapheurPageHeader>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <VBtn
        v-for="item in shortcuts"
        :key="item.to"
        size="small"
        variant="tonal"
        :prepend-icon="item.icon"
        :to="{ name: item.to }"
      >
        {{ item.title }}
      </VBtn>
    </div>

    <VRow>
      <VCol
        cols="12"
        md="4"
        lg="3"
      >
        <VCard class="parapheur-section-card">
          <VCardTitle class="text-body-1">
            Dossiers
          </VCardTitle>
          <VCardText>
            <VBtn
              size="small"
              :variant="selectedFolder ? 'text' : 'tonal'"
              color="primary"
              class="mb-2"
              block
              @click="loadFolder(null)"
            >
              Tous les documents
            </VBtn>
            <VTreeview
              :items="treeItems"
              item-title="title"
              item-value="id"
              open-all
              density="compact"
              activatable
              @click:select="(_: any, { id, value }: any) => value && onSelectFolder({ id })"
              @update:activated="(ids: any) => ids?.[0] && loadFolder(Number(ids[0]))"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="8"
        lg="9"
      >
        <VCard class="mb-4 parapheur-section-card">
          <VCardText>
            <div class="d-flex flex-wrap align-center gap-3 mb-3">
              <div class="text-body-2 font-weight-medium">
                {{ selectedFolder?.name || 'Tous les documents' }}
              </div>
              <VBreadcrumbs
                v-if="breadcrumb.length"
                :items="[{ title: 'Racine', disabled: false }, ...breadcrumb.map(b => ({ title: b.name, disabled: false }))]"
                density="compact"
                class="pa-0"
              />
              <VSpacer />
              <VBtnToggle
                v-model="viewMode"
                mandatory
                density="compact"
                variant="outlined"
              >
                <VBtn
                  value="list"
                  icon="tabler-list"
                />
                <VBtn
                  value="grid"
                  icon="tabler-layout-grid"
                />
              </VBtnToggle>
            </div>
            <AppTextField
              v-model="q"
              label="Filtrer dans le dossier…"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              class="mb-3"
              @keyup.enter="loadFolder(selectedFolder?.id ?? null)"
            />
            <div
              v-if="!selectedFolder && (facets.origin?.length || facets.confidentiality?.length)"
              class="d-flex flex-wrap gap-2"
            >
              <VChip
                size="small"
                :variant="facetOrigin ? 'flat' : 'tonal'"
                :color="facetOrigin ? 'primary' : undefined"
                @click="applyFacet('origin', null)"
              >
                Toutes origines
              </VChip>
              <VChip
                v-for="f in facets.origin || []"
                :key="`o-${f.value}`"
                size="small"
                :variant="facetOrigin === String(f.value) ? 'flat' : 'tonal'"
                :color="facetOrigin === String(f.value) ? 'primary' : undefined"
                @click="applyFacet('origin', f.value)"
              >
                {{ f.label }} ({{ f.total }})
              </VChip>
              <VChip
                v-for="f in facets.confidentiality || []"
                :key="`c-${f.value}`"
                size="small"
                :variant="facetConfidentiality === String(f.value) ? 'flat' : 'outlined'"
                @click="applyFacet('confidentiality', facetConfidentiality === String(f.value) ? null : f.value)"
              >
                {{ labelOf(confidentialityLabels, String(f.value)) }} ({{ f.total }})
              </VChip>
            </div>
          </VCardText>
        </VCard>

        <VCard class="parapheur-section-card">
          <VCardText>
            <div
              v-if="loading"
              class="text-center py-8"
            >
              <VProgressCircular indeterminate />
            </div>
            <template v-else-if="viewMode === 'grid'">
              <VRow v-if="documents.length">
                <VCol
                  v-for="doc in documents"
                  :key="doc.id"
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <VCard
                    variant="outlined"
                    class="h-100 cursor-pointer"
                    @click="openDoc(doc.id)"
                  >
                    <VCardText>
                      <div class="text-subtitle-2 text-truncate">
                        {{ doc.title || doc.object }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ doc.reference }}
                      </div>
                      <VChip
                        v-if="doc.publication_status"
                        size="x-small"
                        class="mt-2"
                        :color="publicationStatusColor[doc.publication_status] || 'secondary'"
                      >
                        {{ labelOf(publicationStatusLabels, doc.publication_status) }}
                      </VChip>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
              <div
                v-else
                class="text-medium-emphasis text-center py-8"
              >
                Aucun document dans ce dossier.
              </div>
            </template>
            <VTable v-else>
              <thead>
                <tr>
                  <th>Référence</th>
                  <th>Titre</th>
                  <th>Publication</th>
                  <th>Confidentialité</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="doc in documents"
                  :key="doc.id"
                  class="cursor-pointer"
                  @click="openDoc(doc.id)"
                >
                  <td>{{ doc.reference }}</td>
                  <td>{{ doc.title || doc.object }}</td>
                  <td>
                    <VChip
                      v-if="doc.publication_status"
                      size="small"
                      :color="publicationStatusColor[doc.publication_status] || 'secondary'"
                    >
                      {{ labelOf(publicationStatusLabels, doc.publication_status) }}
                    </VChip>
                  </td>
                  <td>{{ labelOf(confidentialityLabels, doc.confidentiality) }}</td>
                  <td>{{ formatDateFr(doc.document_date || doc.created_at) }}</td>
                </tr>
                <tr v-if="!documents.length">
                  <td
                    colspan="5"
                    class="text-center text-medium-emphasis py-6"
                  >
                    Aucun document versé dans ce périmètre.
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div class="d-flex flex-wrap align-center justify-space-between mt-3 gap-2">
              <div class="text-caption text-medium-emphasis">
                {{ total }} document(s)
              </div>
              <VPagination
                v-if="lastPage > 1"
                v-model="page"
                :length="lastPage"
                density="compact"
                total-visible="5"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
