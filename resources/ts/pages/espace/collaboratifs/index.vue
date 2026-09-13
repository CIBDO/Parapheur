<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { workspaceTypeLabels } from '@/utils/workspaceUi'
import { formatDateFr, labelOf } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const loading = ref(true)
const items = ref<any[]>([])
const dialog = ref(false)
const form = ref({ name: '', description: '', type: 'project' })
const errorMsg = ref('')

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
      subtitle="Projets et équipes partagés"
      icon="tabler-users"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Nouvel espace
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        v-for="ws in items"
        :key="ws.id"
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>{{ ws.name }}</VCardTitle>
            <VCardSubtitle>
              {{ labelOf(workspaceTypeLabels, ws.type) }} · {{ ws.members_count ?? '—' }} membres
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <p class="text-body-2">
              {{ ws.description || 'Sans description' }}
            </p>
            <div class="text-caption text-medium-emphasis">
              Dernière activité : {{ formatDateFr(ws.updated_at) }}
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

    <div
      v-if="!loading && !items.length"
      class="text-medium-emphasis"
    >
      Aucun espace collaboratif.
    </div>

    <VDialog
      v-model="dialog"
      max-width="480"
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
            label="Nom"
            class="mb-3"
          />
          <VTextarea
            v-model="form.description"
            label="Description"
            rows="2"
            class="mb-3"
          />
          <VSelect
            v-model="form.type"
            :items="[
              { title: 'Projet', value: 'project' },
              { title: 'Équipe', value: 'team' },
              { title: 'Partagé', value: 'shared' },
            ]"
            label="Type"
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
            @click="create"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
