<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { workspaceMemberRoleLabels, workspaceTypeLabels, formatBytes } from '@/utils/workspaceUi'
import { formatDateFr, formatDateTimeFr, labelOf } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const route = useRoute()
const id = computed(() => Number(route.params.id))

const loading = ref(true)
const tab = ref('membres')
const ws = ref<any>(null)
const members = ref<any[]>([])
const activity = ref<any[]>([])
const activityPage = ref(1)
const activityTotal = ref(0)
const storage = ref<any>(null)
const canManage = ref(false)
const canEditMeta = ref(false)
const users = ref<any[]>([])
const addOpen = ref(false)
const editOpen = ref(false)
const addForm = ref({ user_id: null as number | null, role: 'contributor' })
const editForm = ref({ name: '', description: '', type: 'project' })
const roleOptions = [
  { title: 'Gestionnaire', value: 'manager' },
  { title: 'Éditeur', value: 'editor' },
  { title: 'Contributeur', value: 'contributor' },
  { title: 'Lecteur', value: 'viewer' },
]
const typeOptions = [
  { title: 'Projet', value: 'project' },
  { title: 'Équipe', value: 'team' },
  { title: 'Partagé', value: 'shared' },
]
const errorMsg = ref('')
const successMsg = ref('')

const userItems = computed(() =>
  users.value
    .filter((u: any) => !members.value.some((m: any) => m.user_id === u.id || m.user?.id === u.id))
    .map((u: any) => ({
      title: `${u.name} (${u.email})`,
      value: u.id,
    })),
)

const activityIcon: Record<string, string> = {
  member_added: 'tabler-user-plus',
  member_removed: 'tabler-user-minus',
  member_role_changed: 'tabler-user-cog',
  folder_created: 'tabler-folder-plus',
  folder_moved: 'tabler-folder-share',
  document_added: 'tabler-file-plus',
  document_moved: 'tabler-file-arrow-right',
  workspace_updated: 'tabler-edit',
}

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [show, mem, act, u] = await Promise.all([
      $api(`/workspace/${id.value}`),
      $api(`/workspace/${id.value}/members`),
      $api(`/workspace/${id.value}/activity`, { query: { per_page: 20, page: activityPage.value } }),
      $api('/meta/users'),
    ])
    ws.value = show.workspace || show
    canManage.value = !!(show.can_manage_members ?? ws.value?.can_manage_members)
    canEditMeta.value = !!(show.can_manage_members ?? ws.value?.can_manage_members)
    storage.value = show.storage || null
    members.value = mem.data || mem || []
    activity.value = act.data || []
    activityTotal.value = Number(act.total ?? activity.value.length)
    users.value = u.data || u || []
    editForm.value = {
      name: ws.value?.name || '',
      description: ws.value?.description || '',
      type: ws.value?.type || 'project',
    }
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Chargement impossible'
  }
  finally {
    loading.value = false
  }
}

async function addMember() {
  if (!addForm.value.user_id)
    return
  errorMsg.value = ''
  try {
    await $api(`/workspace/${id.value}/members`, { method: 'POST', body: addForm.value })
    addOpen.value = false
    addForm.value = { user_id: null, role: 'contributor' }
    successMsg.value = 'Membre ajouté.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Ajout impossible'
  }
}

async function updateRole(m: any, role: string) {
  if (m.role === role)
    return
  errorMsg.value = ''
  try {
    await $api(`/workspace/${id.value}/members/${m.id}`, {
      method: 'PUT',
      body: { role },
    })
    successMsg.value = 'Rôle mis à jour.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Changement de rôle impossible'
  }
}

async function removeMember(m: any) {
  if (!confirm(`Retirer ${m.user?.name || 'ce membre'} ?`))
    return
  try {
    await $api(`/workspace/${id.value}/members/${m.id}`, { method: 'DELETE' })
    successMsg.value = 'Membre retiré.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Retrait impossible'
  }
}

async function saveMeta() {
  errorMsg.value = ''
  try {
    await $api(`/workspace/${id.value}`, {
      method: 'PATCH',
      body: editForm.value,
    })
    editOpen.value = false
    successMsg.value = 'Espace mis à jour.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Enregistrement impossible'
  }
}

watch(activityPage, async () => {
  const act = await $api(`/workspace/${id.value}/activity`, {
    query: { per_page: 20, page: activityPage.value },
  })
  activity.value = act.data || []
  activityTotal.value = Number(act.total ?? activity.value.length)
})

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="ws?.name || 'Espace collaboratif'"
      :subtitle="ws
        ? `${labelOf(workspaceTypeLabels, ws.type)} · ${members.length} membre${members.length > 1 ? 's' : ''}`
        : ''"
      icon="tabler-users"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :to="{ name: 'espace-collaboratifs' }"
        >
          Retour
        </VBtn>
        <VBtn
          v-if="canEditMeta"
          variant="tonal"
          prepend-icon="tabler-edit"
          @click="editOpen = true"
        >
          Modifier
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          :to="{ name: 'espace-dossiers', query: { workspace: id } }"
        >
          Ouvrir les dossiers
        </VBtn>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-user-plus"
          @click="addOpen = true"
        >
          Ajouter un membre
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="successMsg"
      type="success"
      class="mb-4"
      closable
      @click:close="successMsg = ''"
    >
      {{ successMsg }}
    </VAlert>
    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>

    <VCard
      v-if="ws && !loading"
      class="parapheur-section-card mb-4"
    >
      <VCardText class="d-flex flex-wrap gap-4">
        <div>
          <div class="text-caption text-medium-emphasis">
            Description
          </div>
          <div class="text-body-2">
            {{ ws.description || '—' }}
          </div>
        </div>
        <div v-if="storage">
          <div class="text-caption text-medium-emphasis">
            Stockage
          </div>
          <div class="text-body-2">
            {{ formatBytes(storage.used_bytes) }} / {{ formatBytes(storage.quota_bytes) }}
            <span
              v-if="storage.source"
              class="text-caption text-medium-emphasis"
            >({{ storage.source }})</span>
          </div>
        </div>
        <div v-if="ws.owner">
          <div class="text-caption text-medium-emphasis">
            Propriétaire
          </div>
          <div class="text-body-2">
            {{ ws.owner.name }}
          </div>
        </div>
      </VCardText>
    </VCard>

    <VTabs
      v-model="tab"
      class="mb-4"
    >
      <VTab value="membres">
        Membres
      </VTab>
      <VTab value="activite">
        Activité
      </VTab>
    </VTabs>

    <VTabsWindow v-model="tab">
      <VTabsWindowItem value="membres">
        <VCard class="parapheur-section-card">
          <VList v-if="members.length">
            <VListItem
              v-for="m in members"
              :key="m.id"
              :title="m.user?.name || m.user?.email"
              :subtitle="m.user?.email"
              prepend-icon="tabler-user"
            >
              <template #append>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="m.role === 'owner'"
                    size="small"
                    color="primary"
                    label
                  >
                    Propriétaire
                  </VChip>
                  <VSelect
                    v-else-if="canManage"
                    :model-value="m.role"
                    :items="roleOptions"
                    density="compact"
                    hide-details
                    style="min-inline-size: 160px"
                    @update:model-value="(v: string) => updateRole(m, v)"
                  />
                  <VChip
                    v-else
                    size="small"
                    variant="tonal"
                    label
                  >
                    {{ labelOf(workspaceMemberRoleLabels, m.role) }}
                  </VChip>
                  <VBtn
                    v-if="canManage && m.role !== 'owner'"
                    icon="tabler-trash"
                    size="x-small"
                    variant="text"
                    color="error"
                    @click="removeMember(m)"
                  />
                </div>
              </template>
            </VListItem>
          </VList>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucun membre.' }}
          </VCardText>
        </VCard>
      </VTabsWindowItem>

      <VTabsWindowItem value="activite">
        <VCard class="parapheur-section-card">
          <VList v-if="activity.length">
            <VListItem
              v-for="(a, idx) in activity"
              :key="a.id || idx"
              :title="a.summary"
              :subtitle="`${a.actor?.name || 'Système'} · ${formatDateTimeFr(a.created_at)}`"
              :prepend-icon="activityIcon[a.action] || 'tabler-activity'"
            />
          </VList>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucune activité récente.' }}
          </VCardText>
          <VCardActions v-if="activityTotal > 20">
            <VSpacer />
            <VPagination
              v-model="activityPage"
              :length="Math.ceil(activityTotal / 20)"
              density="compact"
              total-visible="5"
            />
          </VCardActions>
        </VCard>
      </VTabsWindowItem>
    </VTabsWindow>

    <VDialog
      v-model="addOpen"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Ajouter un membre</VCardTitle>
        <VCardText>
          <VAutocomplete
            v-model="addForm.user_id"
            :items="userItems"
            label="Utilisateur"
            class="mb-3"
          />
          <VSelect
            v-model="addForm.role"
            :items="roleOptions"
            label="Rôle"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="addOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!addForm.user_id"
            @click="addMember"
          >
            Ajouter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="editOpen"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Modifier l’espace</VCardTitle>
        <VCardText>
          <VTextField
            v-model="editForm.name"
            label="Nom"
            class="mb-3"
          />
          <VSelect
            v-model="editForm.type"
            :items="typeOptions"
            label="Type"
            class="mb-3"
          />
          <VTextarea
            v-model="editForm.description"
            label="Description"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="editOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!editForm.name.trim()"
            @click="saveMeta"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
