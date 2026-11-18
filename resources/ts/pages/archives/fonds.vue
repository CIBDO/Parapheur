<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const { loading, fetchFonds, postJson } = useArchives()
const fonds = ref<any[]>([])
const dialog = ref(false)
const form = ref({ code: '', name: '', description: '' })

async function load() {
  const data = await fetchFonds()
  fonds.value = data.data || []
}

async function create() {
  await postJson('/archives/fonds', { ...form.value })
  dialog.value = false
  form.value = { code: '', name: '', description: '' }
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Fonds et séries"
      subtitle="Référentiel intellectuel des archives"
      icon="tabler-hierarchy-2"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouveau fonds
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        v-for="item in fonds"
        :key="item.id"
        cols="12"
        md="6"
      >
        <VCard
          class="parapheur-section-card"
          :loading="loading"
        >
          <VCardItem>
            <VCardTitle>{{ item.code }} — {{ item.name }}</VCardTitle>
            <VCardSubtitle>{{ item.description || 'Sans description' }}</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="text-caption mb-2">
              Séries
            </div>
            <VChip
              v-for="serie in (item.series || [])"
              :key="serie.id"
              class="ma-1"
              size="small"
              label
            >
              {{ serie.code }} — {{ serie.name }}
            </VChip>
            <div
              v-if="!(item.series || []).length"
              class="text-medium-emphasis"
            >
              Aucune série.
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Créer un fonds</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.code"
            label="Code"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
          />
          <VTextarea
            v-model="form.description"
            label="Description"
            rows="3"
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
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
