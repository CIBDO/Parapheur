<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'Role',
  },
})

interface PermissionItem {
  id: number
  name: string
}

interface RoleItem {
  id: number
  name: string
  users_count: number
  permissions: string[]
  permission_ids: number[]
}

const permissionLabels: Record<string, string> = {
  'admin.access': 'Accès administration',
  'documents.create': 'Créer des documents',
  'documents.act': 'Traiter des documents',
  'documents.vise': 'Viser des documents',
  'documents.validate': 'Valider des documents',
  'dashboard.dg': 'Tableau de bord DG',
  'dashboard.direction': 'Tableau de bord direction',
  'instructions.manage': 'Gérer les instructions',
  'meetings.manage': 'Gérer les réunions',
  'reporting.view': 'Consulter le reporting',
  'delegations.manage': 'Gérer les délégations',
}

const roles = ref<RoleItem[]>([])
const permissions = ref<PermissionItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isRoleDialogOpen = ref(false)
const isPermissionDialogOpen = ref(false)
const editingRoleId = ref<number | null>(null)

const roleForm = ref({
  name: '',
  permissions: [] as string[],
})

const permissionForm = ref({
  name: '',
})

const dialogTitle = computed(() =>
  editingRoleId.value ? 'Modifier le rôle' : 'Nouveau rôle',
)

const labelFor = (name: string) => permissionLabels[name] || name

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Échec de l’opération')
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const [roleList, permissionList] = await Promise.all([
      $api('/roles'),
      $api('/permissions'),
    ])
    roles.value = roleList
    permissions.value = permissionList
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    loading.value = false
  }
}

const resetRoleForm = () => {
  roleForm.value = { name: '', permissions: [] }
  editingRoleId.value = null
  errorMessage.value = ''
}

const openCreateRole = () => {
  resetRoleForm()
  isRoleDialogOpen.value = true
}

const openEditRole = (role: RoleItem) => {
  editingRoleId.value = role.id
  roleForm.value = {
    name: role.name,
    permissions: [...role.permissions],
  }
  errorMessage.value = ''
  isRoleDialogOpen.value = true
}

const togglePermission = (name: string) => {
  const index = roleForm.value.permissions.indexOf(name)
  if (index >= 0)
    roleForm.value.permissions.splice(index, 1)
  else
    roleForm.value.permissions.push(name)
}

const selectAllPermissions = () => {
  roleForm.value.permissions = permissions.value.map(p => p.name)
}

const clearPermissions = () => {
  roleForm.value.permissions = []
}

const saveRole = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (editingRoleId.value) {
      await $api(`/roles/${editingRoleId.value}`, {
        method: 'PUT',
        body: roleForm.value,
      })
      successMessage.value = 'Rôle mis à jour'
    }
    else {
      await $api('/roles', {
        method: 'POST',
        body: roleForm.value,
      })
      successMessage.value = 'Rôle créé'
    }

    isRoleDialogOpen.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    saving.value = false
  }
}

const removeRole = async (role: RoleItem) => {
  if (!confirm(`Supprimer le rôle « ${role.name} » ?`))
    return

  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api(`/roles/${role.id}`, { method: 'DELETE' })
    successMessage.value = 'Rôle supprimé'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

const savePermission = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api('/permissions', {
      method: 'POST',
      body: permissionForm.value,
    })
    permissionForm.value = { name: '' }
    isPermissionDialogOpen.value = false
    successMessage.value = 'Permission créée'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    saving.value = false
  }
}

const removePermission = async (permission: PermissionItem) => {
  if (!confirm(`Supprimer la permission « ${permission.name} » ?`))
    return

  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api(`/permissions/${permission.id}`, { method: 'DELETE' })
    successMessage.value = 'Permission supprimée'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Rôles & permissions"
      subtitle="Paramétrage RBAC des profils e-Parapheur"
      icon="tabler-lock-access"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-key"
          @click="isPermissionDialogOpen = true; errorMessage = ''"
        >
          Nouvelle permission
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-shield-plus"
          @click="openCreateRole"
        >
          Nouveau rôle
        </VBtn>
      </template>
    </ParapheurPageHeader>


    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMessage = ''"
    >
      {{ successMessage }}
    </VAlert>

    <VAlert
      v-if="errorMessage && !isRoleDialogOpen && !isPermissionDialogOpen"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        lg="7"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Rôles</VCardTitle>
            <VCardSubtitle>Profils et droits associés</VCardSubtitle>
          </VCardItem>

          <VDataTable
            :items="roles"
            :loading="loading"
            :headers="[
              { title: 'Rôle', key: 'name' },
              { title: 'Utilisateurs', key: 'users_count' },
              { title: 'Permissions', key: 'permissions' },
              { title: '', key: 'actions', sortable: false },
            ]"
            item-value="id"
          >
            <template #item.permissions="{ item }">
              <div class="d-flex flex-wrap gap-1 py-2">
                <VChip
                  v-for="perm in item.permissions.slice(0, 3)"
                  :key="perm"
                  size="x-small"
                  label
                  color="primary"
                  variant="tonal"
                >
                  {{ labelFor(perm) }}
                </VChip>
                <VChip
                  v-if="item.permissions.length > 3"
                  size="x-small"
                  label
                  variant="tonal"
                >
                  +{{ item.permissions.length - 3 }}
                </VChip>
                <span
                  v-if="!item.permissions.length"
                  class="text-medium-emphasis text-caption"
                >Aucune</span>
              </div>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex justify-end gap-1">
                <IconBtn @click="openEditRole(item)">
                  <VIcon icon="tabler-edit" />
                </IconBtn>
                <IconBtn
                  color="error"
                  :disabled="item.name === 'Administrateur'"
                  @click="removeRole(item)"
                >
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="5"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Permissions</VCardTitle>
            <VCardSubtitle>Droits atomiques du système</VCardSubtitle>
          </VCardItem>

          <VList lines="two">
            <VListItem
              v-for="permission in permissions"
              :key="permission.id"
            >
              <VListItemTitle>{{ labelFor(permission.name) }}</VListItemTitle>
              <VListItemSubtitle>{{ permission.name }}</VListItemSubtitle>
              <template #append>
                <IconBtn
                  color="error"
                  @click="removePermission(permission)"
                >
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <!-- Dialog rôle -->
    <VDialog
      v-model="isRoleDialogOpen"
      max-width="720"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ dialogTitle }}</VCardTitle>
        </VCardItem>

        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <AppTextField
            v-model="roleForm.name"
            label="Nom du rôle"
            class="mb-4"
            placeholder="Ex. Chef de bureau"
          />

          <div class="d-flex flex-wrap justify-space-between align-center gap-2 mb-3">
            <h6 class="text-h6 mb-0">
              Permissions
            </h6>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="text"
                @click="selectAllPermissions"
              >
                Tout cocher
              </VBtn>
              <VBtn
                size="small"
                variant="text"
                @click="clearPermissions"
              >
                Tout décocher
              </VBtn>
            </div>
          </div>

          <VRow dense>
            <VCol
              v-for="permission in permissions"
              :key="permission.id"
              cols="12"
              sm="6"
            >
              <VCheckbox
                :model-value="roleForm.permissions.includes(permission.name)"
                :label="labelFor(permission.name)"
                density="compact"
                hide-details
                @update:model-value="togglePermission(permission.name)"
              />
              <div class="text-caption text-medium-emphasis ms-8 mb-2">
                {{ permission.name }}
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isRoleDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveRole"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog permission -->
    <VDialog
      v-model="isPermissionDialogOpen"
      max-width="480"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Nouvelle permission</VCardTitle>
        </VCardItem>

        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>

          <AppTextField
            v-model="permissionForm.name"
            label="Code permission"
            placeholder="ex. documents.archive"
            hint="Minuscules, chiffres, points ou tirets uniquement"
            persistent-hint
          />
        </VCardText>

        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isPermissionDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="savePermission"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
