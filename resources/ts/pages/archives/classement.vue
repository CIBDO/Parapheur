<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'manage', subject: 'ArchivesAdmin' },
})

const {
  loading,
  error,
  fetchNodes,
  fetchFonds,
  fetchSeries,
  fetchMetaOptions,
  createNode,
  updateNode,
  deleteNode,
} = useArchives()

const nodes = ref<any[]>([])
const fonds = ref<any[]>([])
const series = ref<any[]>([])
const levelItems = ref<{ title: string; value: string }[]>([])
const dialog = ref(false)
const snack = ref(false)
const snackText = ref('')

function notify(msg: string) {
  snackText.value = msg
  snack.value = true
}
const editing = ref<any | null>(null)

const form = ref({
  code: '',
  name: '',
  level: 'serie',
  parent_id: null as number | null,
  fonds_id: null as number | null,
  series_id: null as number | null,
  is_active: true,
})

async function load() {
  const [n, f, meta] = await Promise.all([
    fetchNodes({ active_only: 0 }),
    fetchFonds(false),
    fetchMetaOptions(),
  ])
  nodes.value = n.data || []
  fonds.value = f.data || []
  levelItems.value = meta.data?.classification_levels || []
  if (!form.value.level && levelItems.value[0])
    form.value.level = levelItems.value[0].value
}

watch(() => form.value.fonds_id, async (id) => {
  if (!id) {
    series.value = []
    return
  }
  const res = await fetchSeries(id)
  series.value = res.data || []
})

function openCreate() {
  editing.value = null
  form.value = {
    code: '',
    name: '',
    level: levelItems.value.find(l => l.value === 'serie')?.value || levelItems.value[0]?.value || 'serie',
    parent_id: null,
    fonds_id: null,
    series_id: null,
    is_active: true,
  }
  dialog.value = true
}

function openEdit(node: any) {
  editing.value = node
  form.value = {
    code: node.code || '',
    name: node.name || '',
    level: node.level || 'serie',
    parent_id: node.parent_id ?? null,
    fonds_id: node.fonds_id ?? null,
    series_id: node.series_id ?? null,
    is_active: node.is_active !== false,
  }
  dialog.value = true
}

async function save() {
  try {
    if (editing.value) {
      await updateNode(editing.value.id, {
        name: form.value.name,
        level: form.value.level,
        fonds_id: form.value.fonds_id,
        series_id: form.value.series_id,
        is_active: form.value.is_active,
      })
    }
    else {
      await createNode({ ...form.value })
    }
    dialog.value = false
    notify('Nœud enregistré')
    await load()
  }
  catch (e: any) {
    notify(e?.message || error.value || 'Échec')
  }
}

async function remove(node: any) {
  if (!confirm(`Supprimer le nœud « ${node.code} » ?`))
    return
  try {
    await deleteNode(node.id)
    notify('Nœud supprimé')
    await load()
  }
  catch (e: any) {
    notify(e?.message || 'Suppression impossible')
  }
}

const levelLabel = (value: string) => levelItems.value.find(i => i.value === value)?.title || value

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Plan de classement"
      subtitle="Classement archivistique versionné — CRUD sur la base"
      icon="tabler-list-tree"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="openCreate"
        >
          Nouveau nœud
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VSnackbar
      v-model="snack"
      :timeout="4000"
    >{{ snackText }}</VSnackbar>

    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="node in nodes"
          :key="node.id"
          :title="`${node.path || node.code} — ${node.name}`"
          :subtitle="`${levelLabel(node.level)} · v${node.version} · ${node.is_active === false ? 'inactif' : 'actif'}`"
        >
          <template #append>
            <div class="d-flex gap-1">
              <VBtn
                size="small"
                variant="tonal"
                @click="openEdit(node)"
              >
                Éditer
              </VBtn>
              <VBtn
                size="small"
                color="error"
                variant="text"
                @click="remove(node)"
              >
                Suppr.
              </VBtn>
            </div>
          </template>
        </VListItem>
        <VListItem
          v-if="!nodes.length"
          title="Aucun nœud de classement"
        />
      </VList>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ editing ? 'Modifier le nœud' : 'Nouveau nœud' }}</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.code"
            label="Code"
            :disabled="!!editing"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
          />
          <VSelect
            v-model="form.level"
            :items="levelItems"
            item-title="title"
            item-value="value"
            label="Niveau"
          />
          <VSelect
            v-if="!editing"
            v-model="form.parent_id"
            :items="nodes.map(n => ({ title: n.path || n.code, value: n.id }))"
            label="Parent"
            clearable
          />
          <VSelect
            v-model="form.fonds_id"
            :items="fonds.map(f => ({ title: `${f.code} — ${f.name}`, value: f.id }))"
            label="Fonds"
            clearable
          />
          <VSelect
            v-model="form.series_id"
            :items="series.map(s => ({ title: `${s.code} — ${s.name}`, value: s.id }))"
            label="Série"
            clearable
            :disabled="!form.fonds_id"
          />
          <VSwitch
            v-model="form.is_active"
            label="Actif"
            hide-details
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
            :loading="loading"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
