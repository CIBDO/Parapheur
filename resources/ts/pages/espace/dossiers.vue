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
const router = useRouter()
const { workspace, load: loadHome } = useWorkspaceHome()
const overrideWorkspaceId = ref<number | null>(
  route.query.workspace ? Number(route.query.workspace) : null,
)
const workspaceId = computed(() => overrideWorkspaceId.value ?? workspace.value?.id ?? null)
const {
  loading, folderId, breadcrumb, folders, documents, currentFolder, viewMode, load, openFolder,
} = useWorkspaceExplorer(workspaceId)

const collabWorkspace = ref<any>(null)
const isCollaborative = computed(() => !!overrideWorkspaceId.value)

const pageTitle = computed(() => {
  if (isCollaborative.value && collabWorkspace.value?.name)
    return `Dossiers — ${collabWorkspace.value.name}`
  return 'Mes dossiers'
})

const pageSubtitle = computed(() => {
  if (isCollaborative.value)
    return 'Espace collaboratif — importez et organisez les fichiers de l’équipe'
  return 'Naviguez, importez et organisez vos documents — y compris vos modèles personnels'
})

async function loadCollabMeta() {
  if (!overrideWorkspaceId.value) {
    collabWorkspace.value = null
    return
  }
  try {
    const show = await $api(`/workspace/${overrideWorkspaceId.value}`)
    collabWorkspace.value = show.workspace || show
  }
  catch {
    collabWorkspace.value = null
  }
}

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

const isEmpty = computed(() => !folders.value.length && !documents.value.length)
const isModelesFolder = computed(() =>
  (currentFolder.value?.name || '').toLowerCase() === 'modèles'
  || (currentFolder.value?.name || '').toLowerCase() === 'modeles',
)

const emptyTitle = computed(() => {
  if (isModelesFolder.value)
    return 'Aucun modèle pour le moment'
  if (currentFolder.value)
    return `Le dossier « ${currentFolder.value.name} » est vide`

  return 'Glissez-déposez des fichiers ici pour les importer'
})

const emptySubtitle = computed(() => {
  if (isModelesFolder.value) {
    return 'Importez vos fichiers types (lettre, note, bordereau…) ou créez un sous-dossier pour les organiser. Ces modèles restent personnels à votre espace.'
  }
  if (currentFolder.value) {
    return 'Importez des fichiers ou créez un sous-dossier pour commencer.'
  }

  return 'Ouvrez un dossier (ex. Modèles) pour y déposer du contenu, ou importez directement à la racine.'
})

onMounted(async () => {
  await loadHome()
  await loadCollabMeta()
  if (route.query.folder)
    folderId.value = Number(route.query.folder)
  await load()
})

watch(workspaceId, async (id) => {
  if (id)
    await load()
})

watch(overrideWorkspaceId, loadCollabMeta)

watch(folderId, (id) => {
  const query: Record<string, string> = {}
  if (route.query.workspace)
    query.workspace = String(route.query.workspace)
  if (id)
    query.folder = String(id)
  router.replace({ query })
})

async function navigateToFolder(id: number | null) {
  await openFolder(id)
}

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
    successMsg.value = isModelesFolder.value
      ? 'Sous-dossier créé dans Modèles.'
      : 'Dossier créé.'
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
    successMsg.value = isModelesFolder.value
      ? `${Array.from(files).length} modèle(s) ajouté(s).`
      : `${Array.from(files).length} fichier(s) importé(s).`
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
  // Les dossiers système restent à la racine : on ne les déplace pas
  if (kind === 'folder') {
    const folder = folders.value.find((f: any) => f.id === id)
    if (folder?.is_system) {
      ev.preventDefault()
      return
    }
  }
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

  const files = ev.dataTransfer?.files
  if (files?.length && workspaceId.value) {
    await navigateToFolder(folderIdTarget)
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

function folderSubtitle(folder: any) {
  if (folder.description)
    return folder.description
  if (folder.is_system && folder.name === 'Modèles')
    return 'Ouvrir pour y ajouter vos modèles personnels'
  if (folder.is_system)
    return 'Dossier système — cliquez pour ouvrir'

  return 'Cliquez pour ouvrir · poignée pour déplacer'
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="pageTitle"
      :subtitle="pageSubtitle"
      icon="tabler-folder"
    >
      <template #actions>
        <VBtn
          v-if="isCollaborative"
          variant="tonal"
          :to="{ name: 'espace-collaboratifs-id', params: { id: overrideWorkspaceId } }"
        >
          Retour à l’espace
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-folder-plus"
          @click="createFolderDialog = true"
        >
          {{ isModelesFolder ? 'Nouveau sous-dossier' : 'Nouveau dossier' }}
        </VBtn>
        <VBtn
          variant="tonal"
          :prepend-icon="isModelesFolder ? 'tabler-file-plus' : 'tabler-upload'"
          :loading="uploading"
          @click="uploadInput?.click()"
        >
          {{ isModelesFolder ? 'Ajouter un modèle' : 'Importer' }}
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

    <VAlert
      v-if="isCollaborative && collabWorkspace"
      type="info"
      variant="tonal"
      class="mb-4"
      density="comfortable"
    >
      Vous naviguez dans l’espace collaboratif <strong>{{ collabWorkspace.name }}</strong>.
      Les fichiers importés ici sont visibles par les membres selon leurs droits.
    </VAlert>

    <VAlert
      v-if="isModelesFolder"
      type="info"
      variant="tonal"
      class="mb-4"
      density="comfortable"
    >
      Dossier <strong>Modèles</strong> — importez ici vos documents types. Vous pouvez créer autant de sous-dossiers et de fichiers que nécessaire.
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex flex-wrap align-center gap-2">
        <VBtn
          size="small"
          variant="text"
          :color="dropTargetFolderId === null ? 'primary' : undefined"
          @click="navigateToFolder(null)"
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
            @click="navigateToFolder(crumb.id)"
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
            v-if="isEmpty"
            class="workspace-dropzone__hint text-center py-10 mb-2"
            :class="{ 'workspace-dropzone__hint--active': isDragOver }"
          >
            <VIcon
              :icon="isModelesFolder ? 'tabler-files' : 'tabler-cloud-upload'"
              size="44"
              class="mb-2"
              :color="isDragOver ? 'primary' : undefined"
            />
            <div class="text-body-1 font-weight-medium">
              {{ emptyTitle }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1 mx-auto"
                 style="max-inline-size: 36rem"
            >
              {{ emptySubtitle }}
            </div>
            <div class="d-flex justify-center flex-wrap gap-2 mt-5">
              <VBtn
                color="primary"
                :prepend-icon="isModelesFolder ? 'tabler-file-plus' : 'tabler-upload'"
                :loading="uploading"
                @click="uploadInput?.click()"
              >
                {{ isModelesFolder ? 'Importer mon modèle' : 'Importer des fichiers' }}
              </VBtn>
              <VBtn
                variant="tonal"
                prepend-icon="tabler-folder-plus"
                @click="createFolderDialog = true"
              >
                {{ isModelesFolder ? 'Créer un sous-dossier' : 'Nouveau dossier' }}
              </VBtn>
            </div>
          </div>

          <div
            v-else
            class="workspace-dropzone__hint workspace-dropzone__hint--compact text-center py-4 mb-4"
            :class="{ 'workspace-dropzone__hint--active': isDragOver }"
          >
            <div class="text-body-2 text-medium-emphasis">
              Déposez des fichiers ici pour les importer dans
              <strong>{{ currentFolder?.name || 'la racine' }}</strong>
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
                class="folder-card h-100"
                :class="{ 'border-primary': dropTargetFolderId === f.id }"
                @dragover="onFolderDragOver(f.id, $event)"
                @drop="onFolderDrop(f.id, $event)"
              >
                <VCardText
                  class="text-center cursor-pointer"
                  @click="navigateToFolder(f.id)"
                >
                  <VIcon
                    :icon="f.name === 'Modèles' ? 'tabler-files' : 'tabler-folder'"
                    size="40"
                    :color="f.name === 'Modèles' ? 'primary' : 'warning'"
                  />
                  <div class="mt-2 text-body-2 font-weight-medium">
                    {{ f.name }}
                  </div>
                  <div
                    v-if="f.description || f.is_system"
                    class="text-caption text-medium-emphasis mt-1"
                  >
                    {{ folderSubtitle(f) }}
                  </div>
                </VCardText>
                <div class="d-flex justify-center gap-1 pb-2">
                  <VBtn
                    v-if="!f.is_system"
                    icon="tabler-grip-vertical"
                    size="x-small"
                    variant="text"
                    title="Glisser pour déplacer"
                    draggable="true"
                    @click.stop
                    @dragstart="onDragStartItem('folder', f.id, $event)"
                    @dragend="onDragEndItem"
                  />
                  <VBtn
                    icon="tabler-share"
                    size="x-small"
                    variant="text"
                    @click.stop="openShareFolder(f)"
                  />
                </div>
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

          <VList
            v-else-if="folders.length || documents.length"
            class="folder-list"
          >
            <VListItem
              v-for="f in folders"
              :key="`lf-${f.id}`"
              :title="f.name"
              :subtitle="folderSubtitle(f)"
              :class="{ 'bg-primary-lighten': dropTargetFolderId === f.id }"
              class="folder-list-item"
              @click="navigateToFolder(f.id)"
              @dragover="onFolderDragOver(f.id, $event)"
              @drop="onFolderDrop(f.id, $event)"
            >
              <template #prepend>
                <VAvatar
                  size="40"
                  :color="f.name === 'Modèles' ? 'primary' : 'warning'"
                  variant="tonal"
                  class="me-3"
                >
                  <VIcon :icon="f.name === 'Modèles' ? 'tabler-files' : 'tabler-folder'" />
                </VAvatar>
              </template>
              <template #append>
                <VChip
                  v-if="f.is_system"
                  size="x-small"
                  variant="tonal"
                  class="me-2"
                >
                  Système
                </VChip>
                <VBtn
                  v-if="!f.is_system"
                  icon="tabler-grip-vertical"
                  size="x-small"
                  variant="text"
                  title="Glisser pour déplacer"
                  draggable="true"
                  class="me-1"
                  @click.stop
                  @dragstart="onDragStartItem('folder', f.id, $event)"
                  @dragend="onDragEndItem"
                />
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
        <VCardTitle>
          {{ isModelesFolder ? 'Nouveau sous-dossier dans Modèles' : 'Nouveau dossier' }}
        </VCardTitle>
        <VCardText>
          <VTextField
            v-model="newFolderName"
            :label="isModelesFolder ? 'Nom du sous-dossier' : 'Nom du dossier'"
            autofocus
            :hint="isModelesFolder
              ? 'Ex. Correspondance, Notes de service, Bordereaux…'
              : 'Le dossier sera créé dans l’emplacement courant'"
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

.workspace-dropzone__hint--compact {
  border-style: dashed;
  opacity: 0.9;
}

.workspace-dropzone__hint--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}

.folder-card {
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.folder-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.45);
}

.folder-list-item {
  cursor: pointer;
  border-radius: 8px;
  margin-block: 2px;
}

.folder-list-item:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.cursor-grab {
  cursor: grab;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
