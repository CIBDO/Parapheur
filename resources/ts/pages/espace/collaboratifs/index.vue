<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { workspaceMemberRoleLabels, workspaceTypeLabels } from '@/utils/workspaceUi'
import { formatDateFr, labelOf } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const canCreate = computed(() => true)

const loading = ref(true)
const items = ref<any[]>([])
const search = ref('')
const dialog = ref(false)
const form = ref({ name: '', description: '', type: 'project' })
const errorMsg = ref('')

const typeOptions = [
  { title: 'Projet', value: 'project' },
  { title: 'Équipe', value: 'team' },
  { title: 'Partagé', value: 'shared' },
]

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q)
    return items.value

  return items.value.filter((ws: any) =>
    [ws.name, ws.description, ws.type].filter(Boolean).some((s: string) =>
      String(s).toLowerCase().includes(q),
    ),
  )
})

async function load() {
  loading.value = true
  try {
    const res = await $api('/workspace/collaborative')
    items.value = res.data || res.items || res || []
  }
  finally {
    loading.value = false
  }
}

async function create() {
  errorMsg.value = ''
  try {
    await $api('/workspace', {
      method: 'POST',
      body: form.value,
    })
    dialog.value = false
    form.value = { name: '', description: '', type: 'project' }
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Création impossible'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Espaces collaboratifs"
      subtitle="Travaillez en équipe sur des dossiers et documents partagés"
      icon="tabler-users"
    >
      <template #actions>
        <VBtn
          v-if="canCreate"
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Nouvel espace
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card mb-4">
      <VCardText class="d-flex flex-wrap gap-3 align-center">
        <AppTextField
          v-model="search"
          class="flex-grow-1"
          style="max-inline-size: 420px"
          placeholder="Rechercher un espace…"
          prepend-inner-icon="tabler-search"
          hide-details
          clearable
        />
        <VChip
          size="small"
          variant="tonal"
          color="primary"
        >
          {{ filtered.length }} espace{{ filtered.length > 1 ? 's' : '' }}
        </VChip>
      </VCardText>
    </VCard>

    <div
      v-if="loading"
      class="text-medium-emphasis"
    >
      Chargement…
    </div>

    <VRow v-else-if="filtered.length">
      <VCol
        v-for="ws in filtered"
        :key="ws.id"
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card h-100">
          <VCardItem>
            <VCardTitle>{{ ws.name }}</VCardTitle>
            <VCardSubtitle>
              {{ labelOf(workspaceTypeLabels, ws.type) }}
              · {{ ws.members_count ?? 0 }} membre{{ (ws.members_count ?? 0) > 1 ? 's' : '' }}
              <span v-if="ws.my_role">
                · {{ labelOf(workspaceMemberRoleLabels, ws.my_role) }}
              </span>
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <p class="text-body-2">
              {{ ws.description || 'Sans description' }}
            </p>
            <div class="text-caption text-medium-emphasis">
              Mis à jour le {{ formatDateFr(ws.updated_at) }}
            </div>
          </VCardText>
          <VCardActions>
            <VBtn
              variant="tonal"
              :to="{ name: 'espace-collaboratifs-id', params: { id: ws.id } }"
            >
              Gérer
            </VBtn>
            <VBtn
              color="primary"
              variant="tonal"
              :to="{ name: 'espace-dossiers', query: { workspace: ws.id } }"
            >
              Ouvrir
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>

    <VCard
      v-else
      class="parapheur-section-card text-center py-10"
    >
      <VIcon
        icon="tabler-users-group"
        size="40"
        class="mb-2 text-medium-emphasis"
      />
      <div class="text-body-1 font-weight-medium mb-1">
        Aucun espace collaboratif
      </div>
      <div class="text-caption text-medium-emphasis mb-4">
        Créez un espace projet ou équipe pour partager dossiers et documents.
      </div>
      <VBtn
        v-if="canCreate"
        color="primary"
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        Créer un espace
      </VBtn>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouvel espace collaboratif</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMsg"
            type="error"
            class="mb-3"
          >
            {{ errorMsg }}
          </VAlert>
          <VTextField
            v-model="form.name"
            label="Nom *"
            class="mb-3"
          />
          <VSelect
            v-model="form.type"
            :items="typeOptions"
            label="Type"
            class="mb-3"
          />
          <VTextarea
            v-model="form.description"
            label="Description"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!form.name.trim()"
            @click="create"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
