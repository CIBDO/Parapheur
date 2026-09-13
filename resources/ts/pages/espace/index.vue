<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useWorkspaceHome } from '@/composables/useWorkspace'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const router = useRouter()
const {
  loading, workspace, storage, recent, favorites, folders, storageLabel, storagePct, load,
} = useWorkspaceHome()

onMounted(load)

const tiles = computed(() => [
  { title: 'Mes dossiers', icon: 'tabler-folder', color: 'primary', to: 'espace-dossiers', count: folders.value.length },
  { title: 'Favoris', icon: 'tabler-star', color: 'warning', to: 'espace-favoris', count: favorites.value.length },
  { title: 'Récents', icon: 'tabler-history', color: 'info', to: 'espace-recents', count: recent.value.length },
  { title: 'Partagés', icon: 'tabler-share', color: 'success', to: 'espace-partages', count: null },
  { title: 'Collaboratifs', icon: 'tabler-users', color: 'secondary', to: 'espace-collaboratifs', count: null },
  { title: 'Bibliothèque', icon: 'tabler-books', color: 'primary', to: 'espace-bibliotheque', count: null },
])
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mon espace documentaire"
      subtitle="Espace de travail quotidien — documents personnels et collaboratifs"
      icon="tabler-cloud"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-folder"
          :to="{ name: 'espace-dossiers' }"
        >
          Ouvrir mes dossiers
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard
      v-if="storage"
      class="mb-6 parapheur-section-card"
    >
      <VCardText>
        <div class="d-flex justify-space-between align-center mb-2">
          <div class="text-body-1 font-weight-medium">
            Stockage utilisé
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ storageLabel }}
          </div>
        </div>
        <VProgressLinear
          :model-value="storagePct"
          :color="storagePct >= (storage.warn_threshold_percent || 80) ? 'warning' : 'primary'"
          height="10"
          rounded
        />
      </VCardText>
    </VCard>

    <VRow class="mb-6">
      <VCol
        v-for="tile in tiles"
        :key="tile.to"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard
          class="parapheur-folder-tile cursor-pointer"
          @click="router.push({ name: tile.to })"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="tile.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="tile.icon" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ tile.title }}
              </div>
              <div
                v-if="tile.count !== null"
                class="text-body-2 text-medium-emphasis"
              >
                {{ loading ? '…' : tile.count }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Dossiers racine</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="folders.length">
              <VListItem
                v-for="f in folders"
                :key="f.id"
                :title="f.name"
                prepend-icon="tabler-folder"
                :to="{ name: 'espace-dossiers', query: { folder: f.id } }"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              {{ loading ? 'Chargement…' : 'Aucun dossier pour le moment.' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Récemment consultés</VCardTitle>
          </VCardItem>
          <VCardText>
            <VList v-if="recent.length">
              <VListItem
                v-for="doc in recent"
                :key="doc.id"
                :title="doc.title || doc.object"
                :subtitle="formatDateFr(doc.updated_at || doc.document_date)"
                prepend-icon="tabler-file"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis"
            >
              {{ loading ? 'Chargement…' : 'Aucun document récent.' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-if="workspace"
      class="text-caption text-medium-emphasis mt-4"
    >
      Espace : {{ workspace.name }} ({{ workspace.type }})
    </div>
  </div>
</template>
