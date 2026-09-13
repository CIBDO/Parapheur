<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import WorkspaceShareDialog from '@/components/espace/WorkspaceShareDialog.vue'
import WorkspaceDocumentActionsDialog from '@/components/espace/WorkspaceDocumentActionsDialog.vue'
import { useWorkspaceExplorer, useWorkspaceHome } from '@/composables/useWorkspace'
import { formatBytes } from '@/utils/workspaceUi'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const route = useRoute()
const { workspace, load: loadHome } = useWorkspaceHome()
const overrideWorkspaceId = ref<number | null>(
  route.query.workspace ? Number(route.query.workspace) : null,
)
const workspaceId = computed(() => overrideWorkspaceId.value ?? workspace.value?.id ?? null)
const {
  loading, folderId, breadcrumb, folders, documents, viewMode, load, openFolder,
} = useWorkspaceExplorer(workspaceId)

const createFolderDialog = ref(false)
const newFolderName = ref('')
const uploadInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const errorMsg = ref('')
const successMsg = ref('')
const shareOpen = ref(false)
const shareDocId = ref<number | null>(null)
const shareFolderId = ref<number | null>(null)
const actionsOpen = ref(false)
const actionsDoc = ref<any>(null)
const isDragOver = ref(false)
const dropTargetFolderId = ref<number | null | undefined>(undefined)
const dragging = ref<{ kind: 'document' | 'folder'; id: number } | null>(null)

onMounted(async () => {
  await loadHome()
  if (route.query.folder)
    folderId.value = Number(route.query.folder)
  await load()
})

watch(workspaceId, async (id) => {
  if (id)
    await load()
})

function openShareDoc(doc: any) {
  shareDocId.value = doc.id
  shareFolderId.value = null
  shareOpen.value = true
}

function openShareFolder(folder: any) {
  shareDocId.value = null
  shareFolderId.value = folder.id
  shareOpen.value = true
}

function openActions(doc: any) {
  actionsDoc.value = doc
  actionsOpen.value = true
}

async function deleteDoc(doc: any) {
  if (!workspaceId.value || !confirm('Mettre ce document à la corbeille ?'))
    return
  await $api(`/workspace/${workspaceId.value}/documents/${doc.id}`, { method: 'DELETE' })
  await load()
}

async function createFolder() {
  if (!workspaceId.value || !newFolderName.value.trim())
    return
  errorMsg.value = ''
  try {
    await $api(`/workspace/${workspaceId.value}/folders`, {
      method: 'POST',
      body: {
        name: newFolderName.value.trim(),
        parent_id: folderId.value,
      },
    })
    createFolderDialog.value = false
    newFolderName.value = ''
    successMsg.value = 'Dossier créé.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Création impossible'
  }
}

async function uploadFiles(files: FileList | File[]) {
  if (!workspaceId.value || !files?.length)
    return
  uploading.value = true
  errorMsg.value = ''
  try {
    for (const file of Array.from(files)) {
      const form = new FormData()
      form.append('main_file', file)
      form.append('object', file.name)
      if (folderId.value)
        form.append('folder_id', String(folderId.value))
      await $api(`/workspace/${workspaceId.value}/documents`, {
        method: 'POST',
        body: form,
      })
    }
    successMsg.value = `${Array.from(files).length} fichier(s) importé(s).`
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e?.data?.error || 'Upload refusé (quota ou format)'
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

function onDragStartItem(kind: 'document' | 'folder', id: number, ev: DragEvent) {
  dragging.value = { kind, id }
  ev.dataTransfer?.setData('application/x-workspace-item', JSON.stringify({ kind, id }))
  ev.dataTransfer!.effectAllowed = 'move'
}

function onDragEndItem() {
  dragging.value = null
  dropTargetFolderId.value = undefined
  isDragOver.value = false
}

function hasOsFiles(ev: DragEvent) {
  return Array.from(ev.dataTransfer?.types || []).includes('Files')
    && !ev.dataTransfer?.types.includes('application/x-workspace-item')
}

function onZoneDragOver(ev: DragEvent) {
  ev.preventDefault()
  isDragOver.value = true
  if (ev.dataTransfer)
    ev.dataTransfer.dropEffect = hasOsFiles(ev) ? 'copy' : 'move'
}

function onZoneDragLeave(ev: DragEvent) {
  const target = ev.currentTarget as HTMLElement
  const related = ev.relatedTarget as Node | null
  if (related && target.contains(related))
    return
  isDragOver.value = false
  dropTargetFolderId.value = undefined
}

async function moveItemToFolder(targetFolderId: number | null) {
  if (!workspaceId.value || !dragging.value)
    return
  const item = dragging.value
  if (item.kind === 'folder' && item.id === targetFolderId)
    return

  errorMsg.value = ''
  try {
    if (item.kind === 'document') {
      await $api(`/workspace/${workspaceId.value}/documents/${item.id}/move`, {
        method: 'POST',
        body: { folder_id: targetFolderId },
      })
    }
    else {
      await $api(`/workspace/${workspaceId.value}/folders/${item.id}/move`, {
        method: 'POST',
        body: { parent_id: targetFolderId },
      })
    }
    successMsg.value = 'Élément déplacé.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Déplacement impossible'
  }
  finally {
    onDragEndItem()
  }
}

async function onZoneDrop(ev: DragEvent) {
  ev.preventDefault()
  isDragOver.value = false

  const raw = ev.dataTransfer?.getData('application/x-workspace-item')
  if (raw) {
    try {
      dragging.value = JSON.parse(raw)
    }
    catch { /* ignore */ }
    const target = dropTargetFolderId.value === undefined ? folderId.value : dropTargetFolderId.value
    await moveItemToFolder(target ?? null)
    return
  }

  const files = ev.dataTransfer?.files
  if (files?.length)
    await uploadFiles(files)
}

function onFolderDragOver(folderIdTarget: number, ev: DragEvent) {
  ev.preventDefault()
  ev.stopPropagation()
  dropTargetFolderId.value = folderIdTarget
  isDragOver.value = true
}

async function onFolderDrop(folderIdTarget: number, ev: DragEvent) {
  ev.preventDefault()
  ev.stopPropagation()
  dropTargetFolderId.value = folderIdTarget

  const raw = ev.dataTransfer?.getData('application/x-workspace-item')
  if (raw) {
    try {
      dragging.value = JSON.parse(raw)
    }
    catch { /* ignore */ }
    await moveItemToFolder(folderIdTarget)
    return
  }

  // Dépôt de fichiers OS dans un sous-dossier : on ouvre le dossier puis upload
  const files = ev.dataTransfer?.files
  if (files?.length && workspaceId.value) {
    folderId.value = folderIdTarget
    await load()
    await uploadFiles(files)
  }
  onDragEndItem()
}

async function dropOnRoot(ev: DragEvent) {
  ev.preventDefault()
  ev.stopPropagation()
  dropTargetFolderId.value = null
  const raw = ev.dataTransfer?.getData('application/x-workspace-item')
  if (raw) {
    try {
      dragging.value = JSON.parse(raw)
    }
    catch { /* ignore */ }
    await moveItemToFolder(null)
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes dossiers"
      subtitle="Créez des dossiers, importez et glissez-déposez vos fichiers"
      icon="tabler-folder"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-folder-plus"
          @click="createFolderDialog = true"
        >
          Nouveau dossier
        </VBtn>
        <VBtn
          variant="tonal"
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
      <VCardText class="d-flex flex-wrap align-center gap-2">
        <VBtn
          size="small"
          variant="text"
          :color="dropTargetFolderId === null ? 'primary' : undefined"
          @click="openFolder(null)"
          @dragover.prevent="dropTargetFolderId = null"
          @drop="dropOnRoot"
        >
          Racine
        </VBtn>
        <template
          v-for="(crumb, idx) in breadcrumb"
          :key="crumb.id"
        >
          <VIcon
            icon="tabler-chevron-right"
            size="16"
          />
          <VBtn
            size="small"
            variant="text"
            :disabled="idx === breadcrumb.length - 1"
            :color="dropTargetFolderId === crumb.id ? 'primary' : undefined"
            @click="openFolder(crumb.id)"
            @dragover.prevent.stop="dropTargetFolderId = crumb.id"
            @drop.stop="onFolderDrop(crumb.id, $event)"
          >
            {{ crumb.name }}
          </VBtn>
        </template>
        <VSpacer />
        <VBtnToggle
          v-model="viewMode"
          density="compact"
          mandatory
          divided
        >
          <VBtn
            value="list"
            icon="tabler-list"
            size="small"
          />
          <VBtn
            value="grid"
            icon="tabler-layout-grid"
            size="small"
          />
        </VBtnToggle>
      </VCardText>
    </VCard>

    <VCard
      class="parapheur-section-card workspace-dropzone"
      :class="{ 'workspace-dropzone--active': isDragOver }"
      @dragover="onZoneDragOver"
      @dragleave="onZoneDragLeave"
      @drop="onZoneDrop"
    >
      <VCardText>
        <div
          v-if="loading"
          class="text-medium-emphasis"
        >
          Chargement…
        </div>
        <template v-else>
          <div
            class="workspace-dropzone__hint text-center py-8 mb-4"
            :class="{ 'workspace-dropzone__hint--active': isDragOver }"
          >
            <VIcon
              icon="tabler-cloud-upload"
              size="40"
              class="mb-2"
              :color="isDragOver ? 'primary' : undefined"
            />
            <div class="text-body-1 font-weight-medium">
              Glissez-déposez des fichiers ici pour les importer
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              Vous pouvez aussi déposer un document ou un dossier sur un autre dossier pour le déplacer.
            </div>
            <div class="d-flex justify-center flex-wrap gap-2 mt-4">
              <VBtn
                color="primary"
                prepend-icon="tabler-folder-plus"
                @click="createFolderDialog = true"
              >
                Nouveau dossier
              </VBtn>
              <VBtn
                variant="tonal"
                prepend-icon="tabler-upload"
                :loading="uploading"
                @click="uploadInput?.click()"
              >
                Importer des fichiers
              </VBtn>
            </div>
          </div>

          <VRow v-if="viewMode === 'grid' && (folders.length || documents.length)">
            <VCol
              v-for="f in folders"
              :key="`f-${f.id}`"
              cols="6"
              sm="4"
              md="3"
            >
              <VCard
                variant="outlined"
                class="cursor-pointer"
                draggable="true"
                :class="{ 'border-primary': dropTargetFolderId === f.id }"
                @click="openFolder(f.id)"
                @dragstart="onDragStartItem('folder', f.id, $event)"
                @dragend="onDragEndItem"
                @dragover="onFolderDragOver(f.id, $event)"
                @drop="onFolderDrop(f.id, $event)"
              >
                <VCardText class="text-center">
                  <VIcon
                    icon="tabler-folder"
                    size="40"
                    color="warning"
                  />
                  <div class="mt-2 text-body-2">
                    {{ f.name }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              v-for="doc in documents"
              :key="`d-${doc.id}`"
              cols="6"
              sm="4"
              md="3"
            >
              <VCard
                variant="outlined"
                class="cursor-grab"
                draggable="true"
                @dragstart="onDragStartItem('document', doc.id, $event)"
                @dragend="onDragEndItem"
              >
                <VCardText
                  class="text-center"
                  @click="$router.push({ name: 'espace-documents-id', params: { id: doc.id } })"
                >
                  <VIcon
                    icon="tabler-file"
                    size="40"
                    color="primary"
                  />
                  <div class="mt-2 text-body-2">
                    {{ doc.title || doc.object }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ formatBytes(doc.latest_version?.size) }}
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <VList v-else-if="folders.length || documents.length">
            <VListItem
              v-for="f in folders"
              :key="`lf-${f.id}`"
              :title="f.name"
              subtitle="Glisser pour déplacer · déposer ici pour y mettre un élément"
              prepend-icon="tabler-folder"
              draggable="true"
              :class="{ 'bg-primary-lighten': dropTargetFolderId === f.id }"
              @click="openFolder(f.id)"
              @dragstart="onDragStartItem('folder', f.id, $event)"
              @dragend="onDragEndItem"
              @dragover="onFolderDragOver(f.id, $event)"
              @drop="onFolderDrop(f.id, $event)"
            >
              <template #append>
                <VBtn
                  icon="tabler-share"
                  size="x-small"
                  variant="text"
                  @click.stop="openShareFolder(f)"
                />
              </template>
            </VListItem>
            <VListItem
              v-for="doc in documents"
              :key="`ld-${doc.id}`"
              :title="doc.title || doc.object"
              :subtitle="formatDateFr(doc.updated_at)"
              prepend-icon="tabler-file"
              draggable="true"
              @dragstart="onDragStartItem('document', doc.id, $event)"
              @dragend="onDragEndItem"
              :to="{ name: 'espace-documents-id', params: { id: doc.id } }"
            >
              <template #append>
                <span class="text-caption text-medium-emphasis me-2">
                  {{ formatBytes(doc.latest_version?.size) }}
                </span>
                <VMenu>
                  <template #activator="{ props: menuProps }">
                    <VBtn
                      v-bind="menuProps"
                      icon="tabler-dots-vertical"
                      size="x-small"
                      variant="text"
                      @click.prevent
                    />
                  </template>
                  <VList density="compact">
                    <VListItem
                      title="Partager"
                      prepend-icon="tabler-share"
                      @click="openShareDoc(doc)"
                    />
                    <VListItem
                      title="GED / Parapheur / Liaisons"
                      prepend-icon="tabler-arrows-exchange"
                      @click="openActions(doc)"
                    />
                    <VListItem
                      title="Supprimer"
                      prepend-icon="tabler-trash"
                      @click="deleteDoc(doc)"
                    />
                  </VList>
                </VMenu>
              </template>
            </VListItem>
          </VList>
        </template>
      </VCardText>
    </VCard>

    <VDialog
      v-model="createFolderDialog"
      max-width="420"
    >
      <VCard>
        <VCardTitle>Nouveau dossier</VCardTitle>
        <VCardText>
          <VTextField
            v-model="newFolderName"
            label="Nom du dossier"
            autofocus
            hint="Le dossier sera créé dans l’emplacement courant"
            persistent-hint
            @keyup.enter="createFolder"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="createFolderDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!newFolderName.trim()"
            @click="createFolder"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <WorkspaceShareDialog
      v-if="workspaceId"
      v-model="shareOpen"
      :workspace-id="workspaceId"
      :document-id="shareDocId"
      :folder-id="shareFolderId"
    />

    <WorkspaceDocumentActionsDialog
      v-if="workspaceId && actionsDoc"
      v-model="actionsOpen"
      :workspace-id="workspaceId"
      :document-id="actionsDoc.id"
      :document-title="actionsDoc.title || actionsDoc.object"
      @done="load"
    />
  </div>
</template>

<style scoped>
.workspace-dropzone {
  min-block-size: 320px;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.workspace-dropzone--active {
  box-shadow: inset 0 0 0 2px rgb(var(--v-theme-primary));
}

.workspace-dropzone__hint {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.workspace-dropzone__hint--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}

.cursor-grab {
  cursor: grab;
}
</style>
