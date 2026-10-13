<script setup lang="ts">
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
const total = ref(0)
const viewMode = ref<'list' | 'grid'>('list')
const q = ref('')

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

const loadFolder = async (folderId: number | null) => {
  loading.value = true
  try {
    if (!folderId) {
      selectedFolder.value = null
      breadcrumb.value = []
      const res = await $api('/ged/documents', {
        query: { per_page: 40, sort: 'updated_at', dir: 'desc', q: q.value || undefined },
      })
      documents.value = res.data || []
      total.value = res.total || documents.value.length

      return
    }

    const res = await $api(`/ged/folders/${folderId}`, {
      query: { per_page: 40 },
    })
    selectedFolder.value = res.folder
    breadcrumb.value = res.breadcrumb || []
    documents.value = res.documents?.data || []
    total.value = res.documents?.total || documents.value.length
  }
  finally {
    loading.value = false
  }
}

const onSelectFolder = (item: any) => {
  if (item?.id)
    loadFolder(item.id)
}

const openDoc = (id: number) => router.push({ name: 'ged-id', params: { id } })

onMounted(async () => {
  await loadTree()
  await loadFolder(null)
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Explorer les documents"
      subtitle="Arborescence institutionnelle et catalogue"
      icon="tabler-folder-open"
    >
      <VBtn
        color="primary"
        prepend-icon="tabler-upload"
        :to="{ name: 'ged-nouveau' }"
      >
        Déposer
      </VBtn>
    </ParapheurPageHeader>

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
              variant="text"
              class="mb-2"
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
              @keyup.enter="loadFolder(selectedFolder?.id ?? null)"
            />
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
                    Aucun document.
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div class="text-caption text-medium-emphasis mt-2">
              {{ total }} document(s)
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
