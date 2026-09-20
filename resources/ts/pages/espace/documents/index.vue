<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatBytes } from '@/utils/workspaceUi'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const router = useRouter()
const { workspace, load: loadHome } = useWorkspaceHome()
const loading = ref(true)
const uploading = ref(false)
const items = ref<any[]>([])
const page = ref(1)
const total = ref(0)
const search = ref('')
const errorMsg = ref('')
const successMsg = ref('')
const uploadInput = ref<HTMLInputElement | null>(null)

const headers = [
  { title: 'Document', key: 'object', sortable: false },
  { title: 'Dossier', key: 'folder', sortable: false, width: '180px' },
  { title: 'Taille', key: 'size', sortable: false, width: '110px' },
  { title: 'Modifié', key: 'updated_at', sortable: false, width: '140px' },
]

function documentLabel(item: any) {
  return item?.title || item?.object || item?.reference || `Document #${item?.id || '?'}`
}

function documentSize(item: any) {
  return item?.size
    ?? item?.latest_version?.size
    ?? item?.latestVersion?.size
    ?? 0
}

async function load() {
  await loadHome()
  if (!workspace.value?.id)
    return
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await $api(`/workspace/${workspace.value.id}/documents`, {
      query: {
        per_page: 25,
        page: page.value,
        q: search.value.trim() || undefined,
      },
    })
    items.value = res.data || []
    total.value = Number(res.total ?? items.value.length)
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Chargement impossible'
    items.value = []
    total.value = 0
  }
  finally {
    loading.value = false
  }
}

async function uploadFiles(files: FileList | File[]) {
  if (!workspace.value?.id || !files?.length)
    return
  uploading.value = true
  errorMsg.value = ''
  try {
    for (const file of Array.from(files)) {
      const form = new FormData()
      form.append('main_file', file)
      form.append('object', file.name)
      await $api(`/workspace/${workspace.value.id}/documents`, {
        method: 'POST',
        body: form,
      })
    }
    successMsg.value = `${Array.from(files).length} fichier(s) importé(s).`
    page.value = 1
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e?.data?.error || 'Import refusé'
  }
  finally {
    uploading.value = false
  }
}

async function onFilesSelected(ev: Event) {
  const input = ev.target as HTMLInputElement
  if (!input.files?.length)
    return
  await uploadFiles(input.files)
  input.value = ''
}

function openDocument(item: any) {
  if (!item?.id)
    return
  router.push({ name: 'espace-documents-id', params: { id: String(item.id) } })
}

function openFolder(item: any) {
  if (!item?.folder_id)
    return
  router.push({ name: 'espace-dossiers', query: { folder: String(item.folder_id) } })
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

onMounted(load)
watch(page, load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes documents"
      subtitle="Tous les fichiers de mon espace personnel"
      icon="tabler-files"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-folder"
          :to="{ name: 'espace-dossiers' }"
        >
          Mes dossiers
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-upload"
          :loading="uploading"
          @click="uploadInput?.click()"
        >
          Importer
        </VBtn>
        <input
          ref="uploadInput"
          type="file"
          multiple
          class="d-none"
          @change="onFilesSelected"
        >
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="successMsg"
      type="success"
      class="mb-4"
      closable
      @click:close="successMsg = ''"
    >
      {{ successMsg }}
    </VAlert>
    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex flex-wrap gap-3 align-center">
        <AppTextField
          v-model="search"
          class="flex-grow-1"
          style="min-inline-size: 220px; max-inline-size: 420px"
          label="Rechercher"
          placeholder="Titre, objet, référence…"
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
        />
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ total }} document{{ total > 1 ? 's' : '' }}
        </VChip>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        :items-per-page="25"
        item-value="id"
        hover
        @click:row="(_: any, ctx: any) => openDocument(ctx.item)"
      >
        <template #item.object="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <VAvatar
              size="36"
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-file" />
            </VAvatar>
            <div class="min-w-0">
              <RouterLink
                class="text-primary text-decoration-none font-weight-medium d-block text-truncate"
                :to="{ name: 'espace-documents-id', params: { id: String(item.id) } }"
                @click.stop
              >
                {{ documentLabel(item) }}
              </RouterLink>
              <div
                v-if="item.reference"
                class="text-caption text-medium-emphasis"
              >
                {{ item.reference }}
              </div>
            </div>
          </div>
        </template>

        <template #item.folder="{ item }">
          <VChip
            v-if="item.folder?.name"
            size="small"
            variant="tonal"
            prepend-icon="tabler-folder"
            class="cursor-pointer"
            @click.stop="openFolder(item)"
          >
            {{ item.folder.name }}
          </VChip>
          <span
            v-else
            class="text-medium-emphasis text-caption"
          >Racine</span>
        </template>

        <template #item.size="{ item }">
          {{ formatBytes(documentSize(item)) }}
        </template>

        <template #item.updated_at="{ item }">
          {{ formatDateFr(item.updated_at || item.added_at) }}
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <VIcon
              icon="tabler-files-off"
              size="40"
              class="mb-2 text-medium-emphasis"
            />
            <div class="text-body-1 font-weight-medium mb-1">
              Aucun document
            </div>
            <div class="text-caption text-medium-emphasis mb-4">
              Importez un fichier ou placez-en un dans vos dossiers (ex. Modèles).
            </div>
            <VBtn
              color="primary"
              prepend-icon="tabler-upload"
              :loading="uploading"
              @click="uploadInput?.click()"
            >
              Importer un document
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
