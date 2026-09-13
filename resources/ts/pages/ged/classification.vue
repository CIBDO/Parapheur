<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useAbility } from '@/plugins/casl/composables/useAbility'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Ged',
  },
})

const ability = useAbility()
const canManage = computed(() => ability.can('manage', 'GedAdmin') || ability.can('manage', 'all'))

const tree = ref<any[]>([])
const loading = ref(true)
const dialog = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
  parent_id: null as number | null,
  code: '',
  name: '',
  structure_id: null as number | null,
  sort_order: 0,
})
const structures = ref<Array<{ id: number; name: string }>>([])
const flatParents = ref<Array<{ id: number; label: string }>>([])
const errorMessage = ref('')

const flatten = (nodes: any[], prefix = '', excludeId: number | null = null): Array<{ id: number; label: string }> => {
  const out: Array<{ id: number; label: string }> = []
  for (const n of nodes) {
    if (excludeId && n.id === excludeId)
      continue
    const label = prefix ? `${prefix} / ${n.name}` : n.name
    out.push({ id: n.id, label })
    if (n.children?.length)
      out.push(...flatten(n.children, label, excludeId))
  }

  return out
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/classification-nodes', { query: { all: 1 } })
    tree.value = res.data || []
    flatParents.value = flatten(tree.value, '', editingId.value)
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  structures.value = await $api('/meta/structures')
  await load()
})

const openCreate = (parentId: number | null = null) => {
  editingId.value = null
  form.value = { parent_id: parentId, code: '', name: '', structure_id: null, sort_order: 0 }
  flatParents.value = flatten(tree.value)
  dialog.value = true
}

const openEdit = (node: any) => {
  editingId.value = node.id
  form.value = {
    parent_id: node.parent_id,
    code: node.code,
    name: node.name,
    structure_id: node.structure_id,
    sort_order: node.sort_order ?? 0,
  }
  flatParents.value = flatten(tree.value, '', node.id)
  dialog.value = true
}

const save = async () => {
  errorMessage.value = ''
  try {
    if (editingId.value) {
      await $api(`/ged/classification-nodes/${editingId.value}`, {
        method: 'PUT',
        body: form.value,
      })
    }
    else {
      await $api('/ged/classification-nodes', {
        method: 'POST',
        body: form.value,
      })
    }
    dialog.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Erreur'
  }
}

const remove = async (id: number) => {
  if (!confirm('Supprimer ce nœud ?'))
    return
  try {
    await $api(`/ged/classification-nodes/${id}`, { method: 'DELETE' })
    await load()
  }
  catch (e: any) {
    alert(e?.data?.message || 'Suppression impossible')
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Plan de classement"
      subtitle="Arborescence logique dynamique (indépendante du stockage physique)"
      icon="tabler-sitemap"
    >
      <template #actions>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate(null)"
        >
          Nœud racine
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard
      class="parapheur-section-card"
      :loading="loading"
    >
      <VCardText>
        <ClassificationTreeNodes
          :nodes="tree"
          :can-manage="canManage"
          @add="openCreate"
          @edit="openEdit"
          @remove="remove"
        />
        <div
          v-if="!loading && !tree.length"
          class="parapheur-empty text-center py-8"
        >
          Aucun nœud de classement. Créez un nœud racine pour commencer.
        </div>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>{{ editingId ? 'Modifier le nœud' : 'Nouveau nœud' }}</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-3"
          >
            {{ errorMessage }}
          </VAlert>
          <AppSelect
            v-model="form.parent_id"
            :items="flatParents"
            item-title="label"
            item-value="id"
            label="Parent (vide = racine)"
            clearable
            class="mb-3"
          />
          <AppTextField
            v-model="form.code"
            label="Code"
            class="mb-3"
          />
          <AppTextField
            v-model="form.name"
            label="Libellé"
            class="mb-3"
          />
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure (optionnel)"
            clearable
            class="mb-3"
          />
          <AppTextField
            v-model.number="form.sort_order"
            label="Ordre d’affichage"
            type="number"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
