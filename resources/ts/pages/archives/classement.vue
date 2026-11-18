<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const { loading, fetchNodes, postJson } = useArchives()
const nodes = ref<any[]>([])
const dialog = ref(false)
const form = ref({ code: '', name: '', level: 'serie', parent_id: null as number | null })

async function load() {
  const data = await fetchNodes({ active_only: 1 })
  nodes.value = data.data || []
}

async function create() {
  await postJson('/archives/classification-nodes', {
    code: form.value.code,
    name: form.value.name,
    level: form.value.level,
    parent_id: form.value.parent_id,
  })
  dialog.value = false
  form.value = { code: '', name: '', level: 'serie', parent_id: null }
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Plan de classement"
      subtitle="Classement archivistique versionné"
      icon="tabler-list-tree"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouveau nœud
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="node in nodes"
          :key="node.id"
          :title="`${node.path || node.code} — ${node.name}`"
          :subtitle="`Niveau ${node.level} · v${node.version}`"
        />
        <VListItem
          v-if="!nodes.length"
          title="Aucun nœud de classement"
        />
      </VList>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouveau nœud</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.code"
            label="Code"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
          />
          <VSelect
            v-model="form.level"
            :items="['fonds', 'sous_fonds', 'serie', 'sous_serie', 'dossier', 'piece']"
            label="Niveau"
          />
          <VSelect
            v-model="form.parent_id"
            :items="nodes.map(n => ({ title: n.path || n.code, value: n.id }))"
            label="Parent"
            clearable
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
