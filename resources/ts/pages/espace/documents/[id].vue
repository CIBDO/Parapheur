<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import WorkspaceShareDialog from '@/components/espace/WorkspaceShareDialog.vue'
import WorkspaceDocumentActionsDialog from '@/components/espace/WorkspaceDocumentActionsDialog.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatBytes } from '@/utils/workspaceUi'
import { formatDateFr } from '@/utils/parapheurUi'
import { isOnlyOfficeEditableDocument } from '@/utils/meetingsUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const route = useRoute()
const docId = computed(() => Number(route.params.id))
const { workspace, load: loadHome } = useWorkspaceHome()
const workspaceId = computed(() => workspace.value?.id ?? null)

const loading = ref(true)
const doc = ref<any>(null)
const errorMsg = ref('')
const shareOpen = ref(false)
const actionsOpen = ref(false)
const favorited = ref(false)
const previewMode = ref<'none' | 'pdf' | 'onlyoffice'>('none')

const version = computed(() => doc.value?.latest_version || doc.value?.versions?.[0])
const streamUrl = computed(() => version.value?.stream_url || version.value?.preview?.url || '')
const isPdf = computed(() => String(version.value?.mime_type || '').includes('pdf'))
const isOffice = computed(() => isOnlyOfficeEditableDocument({ latest_version: version.value }))

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    await loadHome()
    try {
      doc.value = await $api(`/ged/documents/${docId.value}`)
    }
    catch {
      const res = await $api(`/workspace/${workspaceId.value}/documents?per_page=100`)
      const items = res.data || res.items || []
      doc.value = items.find((d: any) => d.id === docId.value) || null
    }
    if (!doc.value)
      errorMsg.value = 'Document introuvable'
    else if (isPdf.value)
      previewMode.value = 'pdf'
    else if (isOffice.value)
      previewMode.value = 'onlyoffice'

    favorited.value = !!doc.value?.is_favorite
    if (doc.value?.id) {
      const favs = await $api('/workspace/favorites').catch(() => ({ data: [] }))
      favorited.value = (favs.data || []).some((f: any) => f.kind === 'document' && f.favoritable_id === doc.value.id)
    }
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Chargement impossible'
  }
  finally {
    loading.value = false
  }
}

async function toggleFavorite() {
  const res = await $api('/workspace/favorites/toggle', {
    method: 'POST',
    body: { type: 'document', id: docId.value },
  })
  favorited.value = !!res.favorited
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="doc?.title || doc?.object || 'Document'"
      subtitle="Fiche document — Mon espace"
      icon="tabler-file"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :color="favorited ? 'warning' : undefined"
          :prepend-icon="favorited ? 'tabler-star-filled' : 'tabler-star'"
          @click="toggleFavorite"
        >
          {{ favorited ? 'Favori' : 'Favoris' }}
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-share"
          @click="shareOpen = true"
        >
          Partager
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-arrows-exchange"
          @click="actionsOpen = true"
        >
          Actions
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
    >
      {{ errorMsg }}
    </VAlert>

    <VAlert
      v-if="isOffice"
      type="info"
      variant="tonal"
      class="mb-4"
      density="comfortable"
    >
      Édition collaborative OnlyOffice : plusieurs membres peuvent ouvrir ce document simultanément ; les modifications sont synchronisées.
    </VAlert>

    <VRow v-if="doc && !loading">
      <VCol
        cols="12"
        md="4"
      >
        <VCard class="parapheur-section-card mb-4">
          <VCardItem>
            <VCardTitle>Métadonnées</VCardTitle>
          </VCardItem>
          <VCardText>
            <div class="text-body-2 mb-2">
              <strong>Référence :</strong> {{ doc.reference || '—' }}
            </div>
            <div class="text-body-2 mb-2">
              <strong>Origine :</strong> {{ doc.origin }}
            </div>
            <div class="text-body-2 mb-2">
              <strong>Modifié :</strong> {{ formatDateFr(doc.updated_at) }}
            </div>
            <div class="text-body-2 mb-2">
              <strong>Taille :</strong> {{ formatBytes(version?.size) }}
            </div>
            <div class="text-body-2">
              <strong>Fichier :</strong> {{ version?.original_name || '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Prévisualisation</VCardTitle>
          </VCardItem>
          <VCardText>
            <div
              v-if="previewMode === 'pdf' && streamUrl"
              style="min-height: 70vh"
            >
              <iframe
                :src="streamUrl"
                style="width: 100%; height: 70vh; border: 0"
                title="PDF"
              />
            </div>
            <OnlyOfficeEditor
              v-else-if="previewMode === 'onlyoffice' && version"
              :document-id="docId"
              :version-id="version.id"
            />
            <div
              v-else
              class="text-medium-emphasis"
            >
              Prévisualisation non disponible pour ce type de fichier.
              Utilisez le parapheur / téléchargement sécurisé si besoin.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <WorkspaceShareDialog
      v-if="workspaceId"
      v-model="shareOpen"
      :workspace-id="workspaceId"
      :document-id="docId"
    />
    <WorkspaceDocumentActionsDialog
      v-if="workspaceId && doc"
      v-model="actionsOpen"
      :workspace-id="workspaceId"
      :document-id="docId"
      :document-title="doc.title || doc.object"
    />
  </div>
</template>
