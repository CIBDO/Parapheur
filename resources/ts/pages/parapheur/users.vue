<script setup lang="ts">
definePage({
  meta: {
    action: 'manage',
    subject: 'User',
  },
})

interface StructureOption {
  id: number
  code: string
  name: string
}

interface RoleOption {
  id: number
  name: string
}

interface UserItem {
  id: number
  name: string
  first_name?: string | null
  last_name?: string | null
  title?: string | null
  phone?: string | null
  email: string
  is_active: boolean
  structure_id: number | null
  position_title?: string | null
  role?: string | null
  roles?: string[]
  structure?: StructureOption | null
}

const users = ref<UserItem[]>([])
const structures = ref<StructureOption[]>([])
const roles = ref<RoleOption[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isDialogOpen = ref(false)
const editingId = ref<number | null>(null)
const isPasswordVisible = ref(false)

const filters = ref({
  q: '',
  structure_id: null as number | null,
  role: null as string | null,
  is_active: null as boolean | null,
})

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  title: '',
  position_title: '',
  structure_id: null as number | null,
  role: 'Agent',
  password: '',
  is_active: true,
})

const dialogTitle = computed(() =>
  editingId.value ? 'Modifier l’utilisateur' : 'Nouvel utilisateur',
)

const resetForm = () => {
  form.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    title: '',
    position_title: '',
    structure_id: null,
    role: roles.value.find(r => r.name === 'Agent')?.name || roles.value[0]?.name || 'Agent',
    password: '',
    is_active: true,
  }
  editingId.value = null
  isPasswordVisible.value = false
  errorMessage.value = ''
}

const loadMeta = async () => {
  const [structureList, roleList] = await Promise.all([
    $api('/meta/structures'),
    $api('/users/roles'),
  ])
  structures.value = structureList
  roles.value = roleList
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const query: Record<string, string | number | boolean> = {}
    if (filters.value.q)
      query.q = filters.value.q
    if (filters.value.structure_id)
      query.structure_id = filters.value.structure_id
    if (filters.value.role)
      query.role = filters.value.role
    if (filters.value.is_active !== null && filters.value.is_active !== undefined)
      query.is_active = filters.value.is_active

    users.value = await $api('/users', { query })
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Impossible de charger les utilisateurs'
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  resetForm()
  isDialogOpen.value = true
}

const openEdit = (item: UserItem) => {
  editingId.value = item.id
  form.value = {
    first_name: item.first_name || '',
    last_name: item.last_name || '',
    email: item.email,
    phone: item.phone || '',
    title: item.title || '',
    position_title: item.position_title || '',
    structure_id: item.structure_id,
    role: item.role || item.roles?.[0] || 'Agent',
    password: '',
    is_active: item.is_active,
  }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Échec de l’opération')
}

const saveUser = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const body: Record<string, unknown> = { ...form.value }

    if (editingId.value) {
      if (!body.password)
        delete body.password

      await $api(`/users/${editingId.value}`, { method: 'PUT', body })
      successMessage.value = 'Utilisateur mis à jour'
    }
    else {
      delete body.password
      await $api('/users', { method: 'POST', body })
      successMessage.value = 'Utilisateur créé — identifiants envoyés par e-mail'
    }

    isDialogOpen.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    saving.value = false
  }
}

const removeUser = async (item: UserItem) => {
  if (!confirm(`Supprimer ou désactiver « ${item.name} » (${item.email}) ?`))
    return

  errorMessage.value = ''
  successMessage.value = ''

  try {
    const res = await $api(`/users/${item.id}`, { method: 'DELETE' })
    successMessage.value = res?.message || 'Utilisateur traité'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

const toggleActive = async (item: UserItem) => {
  const nextActive = !item.is_active
  const label = nextActive ? 'activer' : 'désactiver'

  if (!confirm(`Voulez-vous vraiment ${label} le compte « ${item.name} » ?`))
    return

  errorMessage.value = ''
  successMessage.value = ''

  try {
    await $api(`/users/${item.id}`, {
      method: 'PUT',
      body: {
        first_name: item.first_name,
        last_name: item.last_name,
        email: item.email,
        phone: item.phone,
        title: item.title,
        position_title: item.position_title,
        structure_id: item.structure_id,
        role: item.role || item.roles?.[0],
        is_active: nextActive,
      },
    })
    successMessage.value = nextActive ? 'Compte activé' : 'Compte désactivé'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

onMounted(async () => {
  await loadMeta()
  await load()
})
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          Utilisateurs
        </h4>
        <p class="text-body-1 mb-0 text-medium-emphasis">
          Comptes, rôles et rattachement aux structures
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-user-plus"
        @click="openCreate"
      >
        Nouvel utilisateur
      </VBtn>
    </div>

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
      v-if="errorMessage && !isDialogOpen"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche"
              placeholder="Nom, e-mail, fonction…"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.structure_id"
              :items="structures"
              :item-title="(i: StructureOption) => `${i.code} — ${i.name}`"
              item-value="id"
              label="Structure"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.role"
              :items="roles"
              item-title="name"
              item-value="name"
              label="Rôle"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-end"
          >
            <VBtn
              block
              color="primary"
              variant="tonal"
              :loading="loading"
              @click="load"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable
        :items="users"
        :loading="loading"
        :headers="[
          { title: 'Utilisateur', key: 'name' },
          { title: 'E-mail', key: 'email' },
          { title: 'Structure', key: 'structure' },
          { title: 'Fonction', key: 'position_title' },
          { title: 'Rôle', key: 'role' },
          { title: 'Statut', key: 'is_active' },
          { title: '', key: 'actions', sortable: false },
        ]"
        item-value="id"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              color="primary"
              variant="tonal"
              size="36"
            >
              <span class="text-sm">{{ (item.name || '?').slice(0, 1).toUpperCase() }}</span>
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.name }}
              </div>
              <div
                v-if="item.title"
                class="text-caption text-medium-emphasis"
              >
                {{ item.title }}
              </div>
            </div>
          </div>
        </template>

        <template #item.structure="{ item }">
          <span v-if="item.structure">{{ item.structure.code }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <template #item.position_title="{ item }">
          {{ item.position_title || '—' }}
        </template>

        <template #item.role="{ item }">
          <VChip
            size="small"
            label
            color="primary"
            variant="tonal"
          >
            {{ item.role || '—' }}
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            size="small"
            label
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Actif' : 'Inactif' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <VTooltip location="top">
              <template #activator="{ props: tip }">
                <IconBtn
                  v-bind="tip"
                  :color="item.is_active ? 'warning' : 'success'"
                  @click="toggleActive(item)"
                >
                  <VIcon :icon="item.is_active ? 'tabler-user-off' : 'tabler-user-check'" />
                </IconBtn>
              </template>
              <span>{{ item.is_active ? 'Désactiver le compte' : 'Activer le compte' }}</span>
            </VTooltip>

            <VTooltip location="top">
              <template #activator="{ props: tip }">
                <IconBtn
                  v-bind="tip"
                  @click="openEdit(item)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>
              </template>
              <span>Modifier</span>
            </VTooltip>

            <VTooltip location="top">
              <template #activator="{ props: tip }">
                <IconBtn
                  v-bind="tip"
                  color="error"
                  @click="removeUser(item)"
                >
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
              <span>Supprimer</span>
            </VTooltip>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="isDialogOpen"
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

          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.first_name"
                label="Prénom"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.last_name"
                label="Nom"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.email"
                label="Adresse e-mail"
                type="email"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.phone"
                label="Téléphone"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.title"
                :items="[
                  { title: 'M.', value: 'M.' },
                  { title: 'Mme', value: 'Mme' },
                  { title: 'Mlle', value: 'Mlle' },
                  { title: 'Dr', value: 'Dr' },
                  { title: 'Pr', value: 'Pr' },
                  { title: 'Me', value: 'Me' },
                ]"
                label="Civilité"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.position_title"
                label="Fonction"
                placeholder="Chef de division"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.structure_id"
                :items="structures"
                :item-title="(i: StructureOption) => `${i.code} — ${i.name}`"
                item-value="id"
                label="Structure"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.role"
                :items="roles"
                item-title="name"
                item-value="name"
                label="Rôle"
              />
            </VCol>
            <VCol
              v-if="editingId"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.password"
                label="Nouveau mot de passe (optionnel)"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                autocomplete="new-password"
                hint="Laisser vide pour conserver le mot de passe actuel"
                persistent-hint
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>
            <VCol
              v-else
              cols="12"
            >
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-0"
              >
                Un mot de passe temporaire sera généré et envoyé automatiquement à l’adresse e-mail saisie.
              </VAlert>
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                label="Compte actif"
                color="primary"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveUser"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
