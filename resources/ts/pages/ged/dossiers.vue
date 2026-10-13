<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { publicationWorkflowOptions } from '@/utils/gedUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'GedAdmin',
  },
})

const loading = ref(true)
const folders = ref<any[]>([])
const structures = ref<any[]>([])
const users = ref<any[]>([])
const dialog = ref(false)
const aclDialog = ref(false)
const selectedFolder = ref<any | null>(null)
const errorMessage = ref('')
const form = ref({
  parent_id: null as number | null,
  name: '',
  code: '',
  description: '',
  kind: 'institutional',
  structure_id: null as number | null,
  default_publication_workflow: 'controlled',
  inherit_permissions: true,
})
const aclForm = ref({
  user_id: null as number | null,
  structure_id: null as number | null,
  role_name: '',
  ability: 'view',
  is_exception: false,
})

const kindOptions = [
  { value: 'institutional', title: 'Institutionnel' },
  { value: 'structure', title: 'Structure' },
  { value: 'restricted', title: 'Restreint' },
]

const abilityOptions = [
  { value: 'view', title: 'Consulter' },
  { value: 'download', title: 'Télécharger' },
  { value: 'upload', title: 'Téléverser' },
  { value: 'edit', title: 'Modifier' },
  { value: 'validate', title: 'Valider' },
  { value: 'publish', title: 'Publier' },
  { value: 'share', title: 'Partager' },
  { value: 'manage_acl', title: 'Gérer les permissions' },
]

const flatten = (nodes: any[], depth = 0): any[] => {
  const rows: any[] = []
  for (const n of nodes || []) {
    rows.push({ ...n, label: `${'— '.repeat(depth)}${n.name}` })
    if (n.children?.length)
      rows.push(...flatten(n.children, depth + 1))
  }

  return rows
}

const flatFolders = computed(() => flatten(folders.value))

const load = async () => {
  loading.value = true
  try {
    const [tree, structs, people] = await Promise.all([
      $api('/ged/folders', { query: { include_archived: true } }),
      $api('/meta/structures'),
      $api('/meta/users'),
    ])
    folders.value = tree.data || []
    structures.value = structs.data || structs || []
    users.value = people.data || people || []
  }
  finally {
    loading.value = false
  }
}

const save = async () => {
  errorMessage.value = ''
  try {
    await $api('/ged/folders', { method: 'POST', body: form.value })
    dialog.value = false
    form.value = {
      parent_id: null,
      name: '',
      code: '',
      description: '',
      kind: 'institutional',
      structure_id: null,
      default_publication_workflow: 'controlled',
      inherit_permissions: true,
    }
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Erreur lors de la création'
  }
}

const archiveFolder = async (folder: any) => {
  await $api(`/ged/folders/${folder.id}`, {
    method: 'PUT',
    body: { is_archived: !folder.is_archived },
  })
  await load()
}

const openAcl = async (folder: any) => {
  const res = await $api(`/ged/folders/${folder.id}`)
  selectedFolder.value = res.folder
  aclDialog.value = true
}

const grantAcl = async () => {
  if (!selectedFolder.value)
    return
  errorMessage.value = ''
  try {
    await $api(`/ged/folders/${selectedFolder.value.id}/share`, {
      method: 'POST',
      body: {
        ...aclForm.value,
        role_name: aclForm.value.role_name || null,
      },
    })
    await openAcl(selectedFolder.value)
    aclForm.value = {
      user_id: null,
      structure_id: null,
      role_name: '',
      ability: 'view',
      is_exception: false,
    }
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Attribution impossible'
  }
}

const revokeAcl = async (rule: any) => {
  if (!selectedFolder.value)
    return
  await $api(`/ged/folders/${selectedFolder.value.id}/share/${rule.id}`, { method: 'DELETE' })
  await openAcl(selectedFolder.value)
}

const toggleInherit = async () => {
  if (!selectedFolder.value)
    return
  await $api(`/ged/folders/${selectedFolder.value.id}`, {
    method: 'PUT',
    body: { inherit_permissions: !selectedFolder.value.inherit_permissions },
  })
  await openAcl(selectedFolder.value)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Gestion des dossiers"
      subtitle="Arborescence, circuits et permissions (héritage contrôlé)"
      icon="tabler-folders"
    >
      <VBtn
        color="primary"
        prepend-icon="tabler-folder-plus"
        @click="dialog = true"
      >
        Nouveau dossier
      </VBtn>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">
      <VCardText>
        <div
          v-if="loading"
          class="text-center py-8"
        >
          <VProgressCircular indeterminate />
        </div>
        <VTable v-else>
          <thead>
            <tr>
              <th>Nom</th>
              <th>Code</th>
              <th>Type</th>
              <th>Structure</th>
              <th>Circuit</th>
              <th>Héritage</th>
              <th>Statut</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="folder in flatFolders"
              :key="folder.id"
            >
              <td>{{ folder.label || folder.name }}</td>
              <td>{{ folder.code }}</td>
              <td>{{ folder.kind }}</td>
              <td>{{ folder.structure?.name || '—' }}</td>
              <td>{{ folder.default_publication_workflow }}</td>
              <td>{{ folder.inherit_permissions ? 'Oui' : 'Non' }}</td>
              <td>
                <VChip
                  size="small"
                  :color="folder.is_archived ? 'secondary' : 'success'"
                >
                  {{ folder.is_archived ? 'Archivé' : 'Actif' }}
                </VChip>
              </td>
              <td class="text-no-wrap">
                <VBtn
                  size="small"
                  variant="text"
                  @click="openAcl(folder)"
                >
                  Permissions
                </VBtn>
                <VBtn
                  size="small"
                  variant="text"
                  @click="archiveFolder(folder)"
                >
                  {{ folder.is_archived ? 'Réactiver' : 'Archiver' }}
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Nouveau dossier</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-3"
          >
            {{ errorMessage }}
          </VAlert>
          <AppTextField
            v-model="form.name"
            label="Nom"
            class="mb-3"
          />
          <AppTextField
            v-model="form.code"
            label="Code"
            class="mb-3"
          />
          <AppSelect
            v-model="form.parent_id"
            :items="flatFolders"
            item-title="label"
            item-value="id"
            label="Dossier parent"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="form.kind"
            :items="kindOptions"
            item-title="title"
            item-value="value"
            label="Nature"
            class="mb-3"
          />
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="form.default_publication_workflow"
            :items="publicationWorkflowOptions"
            item-title="title"
            item-value="value"
            label="Circuit de publication"
            class="mb-3"
          />
          <VCheckbox
            v-model="form.inherit_permissions"
            label="Hériter des permissions du parent"
          />
          <AppTextarea
            v-model="form.description"
            label="Description"
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
            :disabled="!form.name"
            @click="save"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="aclDialog"
      max-width="720"
    >
      <VCard v-if="selectedFolder">
        <VCardTitle>
          Permissions — {{ selectedFolder.name }}
        </VCardTitle>
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-3"
          >
            Héritage :
            <strong>{{ selectedFolder.inherit_permissions ? 'activé' : 'désactivé' }}</strong>
            — les exceptions refusent explicitement un droit malgré l’héritage.
          </VAlert>
          <VBtn
            size="small"
            class="mb-4"
            variant="tonal"
            @click="toggleInherit"
          >
            {{ selectedFolder.inherit_permissions ? 'Désactiver l’héritage' : 'Activer l’héritage' }}
          </VBtn>

          <VTable density="compact" class="mb-4">
            <thead>
              <tr>
                <th>Cible</th>
                <th>Droit</th>
                <th>Exception</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="rule in selectedFolder.access_rules || []"
                :key="rule.id"
              >
                <td>
                  {{ rule.user?.name || rule.structure?.name || rule.role_name || '—' }}
                </td>
                <td>{{ rule.ability }}</td>
                <td>{{ rule.is_exception ? 'Oui' : 'Non' }}</td>
                <td>
                  <VBtn
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="revokeAcl(rule)"
                  >
                    Révoquer
                  </VBtn>
                </td>
              </tr>
              <tr v-if="!(selectedFolder.access_rules || []).length">
                <td
                  colspan="4"
                  class="text-medium-emphasis"
                >
                  Aucune règle explicite.
                </td>
              </tr>
            </tbody>
          </VTable>

          <VDivider class="mb-4" />
          <div class="text-subtitle-2 mb-2">
            Ajouter une permission
          </div>
          <UserAutocomplete
            v-model="aclForm.user_id"
            :items="users"
            label="Agent"
            class="mb-2"
          />
          <AppSelect
            v-model="aclForm.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure"
            clearable
            class="mb-2"
          />
          <AppTextField
            v-model="aclForm.role_name"
            label="Rôle (optionnel)"
            class="mb-2"
          />
          <AppSelect
            v-model="aclForm.ability"
            :items="abilityOptions"
            item-title="title"
            item-value="value"
            label="Droit"
            class="mb-2"
          />
          <VCheckbox
            v-model="aclForm.is_exception"
            label="Exception (refus explicite)"
          />
          <VBtn
            color="primary"
            class="mt-2"
            @click="grantAcl"
          >
            Attribuer
          </VBtn>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="aclDialog = false"
          >
            Fermer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
