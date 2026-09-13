<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useAbility } from '@/plugins/casl/composables/useAbility'

definePage({
  meta: { action: 'read', subject: 'Ged' },
})

const ability = useAbility()
const canManage = computed(() => ability.can('manage', 'GedAdmin') || ability.can('manage', 'all'))

const tree = ref<any[]>([])
const loading = ref(true)
const dialog = ref(false)
const form = ref({
  parent_id: null as number | null,
  code: '',
  name: '',
  structure_id: null as number | null,
})
const structures = ref<Array<{ id: number; name: string }>>([])
const flatParents = ref<Array<{ id: number; label: string }>>([])
const errorMessage = ref('')

const flatten = (nodes: any[], prefix = ''): Array<{ id: number; label: string }> => {
  const out: Array<{ id: number; label: string }> = []
  for (const n of nodes) {
    const label = prefix ? `${prefix} / ${n.name}` : n.name
    out.push({ id: n.id, label })
    if (n.children?.length)
      out.push(...flatten(n.children, label))
  }

  return out
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/classification-nodes', { query: { all: 1 } })
    tree.value = res.data || []
    flatParents.value = flatten(tree.value)
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
  form.value = { parent_id: parentId, code: '', name: '', structure_id: null }
  dialog.value = true
}

const save = async () => {
  errorMessage.value = ''
  try {
    await $api('/ged/classification-nodes', {
      method: 'POST',
      body: form.value,
    })
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
      subtitle="Arborescence logique (indépendante du stockage physique)"
      icon="tabler-sitemap"
    >
      <VBtn
        v-if="canManage"
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreate(null)"
      >
        Nœud racine
      </VBtn>
    </ParapheurPageHeader>

    <VCard
      class="parapheur-section-card"
      :loading="loading"
    >
      <VCardText>
        <template
          v-for="node in tree"
          :key="node.id"
        >
          <div class="mb-2">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-folder" />
              <strong>{{ node.code }}</strong>
              — {{ node.name }}
              <VSpacer />
              <VBtn
                v-if="canManage"
                size="x-small"
                variant="text"
                icon="tabler-plus"
                @click="openCreate(node.id)"
              />
              <VBtn
                v-if="canManage"
                size="x-small"
                variant="text"
                color="error"
                icon="tabler-trash"
                @click="remove(node.id)"
              />
            </div>
            <div
              v-for="child in node.children || []"
              :key="child.id"
              class="ms-6 mt-2"
            >
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-folder"
                  size="18"
                />
                <span><strong>{{ child.code }}</strong> — {{ child.name }}</span>
                <VSpacer />
                <VBtn
                  v-if="canManage"
                  size="x-small"
                  variant="text"
                  icon="tabler-plus"
                  @click="openCreate(child.id)"
                />
                <VBtn
                  v-if="canManage"
                  size="x-small"
                  variant="text"
                  color="error"
                  icon="tabler-trash"
                  @click="remove(child.id)"
                />
              </div>
              <div
                v-for="grand in child.children || []"
                :key="grand.id"
                class="ms-6 mt-1 text-body-2"
              >
                <VIcon
                  icon="tabler-file"
                  size="16"
                  class="me-1"
                />
                <strong>{{ grand.code }}</strong> — {{ grand.name }}
                <VBtn
                  v-if="canManage"
                  size="x-small"
                  variant="text"
                  color="error"
                  icon="tabler-trash"
                  class="ms-2"
                  @click="remove(grand.id)"
                />
              </div>
            </div>
          </div>
        </template>
        <div
          v-if="!loading && !tree.length"
          class="parapheur-empty text-center py-8"
        >
          Aucun nœud de classement.
        </div>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouveau nœud</VCardTitle>
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
            label="Parent"
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
            label="Structure"
            clearable
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
