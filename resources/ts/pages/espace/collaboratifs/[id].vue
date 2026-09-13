<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { workspaceMemberRoleLabels, workspaceTypeLabels } from '@/utils/workspaceUi'
import { formatDateFr, formatDateTimeFr, labelOf } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Workspace',
  },
})

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))

const loading = ref(true)
const tab = ref('membres')
const ws = ref<any>(null)
const members = ref<any[]>([])
const activity = ref<any[]>([])
const users = ref<any[]>([])
const addOpen = ref(false)
const addForm = ref({ user_id: null as number | null, role: 'contributor' })
const roleOptions = [
  { title: 'Gestionnaire', value: 'manager' },
  { title: 'Éditeur', value: 'editor' },
  { title: 'Contributeur', value: 'contributor' },
  { title: 'Lecteur', value: 'viewer' },
]
const errorMsg = ref('')

async function load() {
  loading.value = true
  try {
    const [show, mem, act, u] = await Promise.all([
      $api(`/workspace/${id.value}`),
      $api(`/workspace/${id.value}/members`),
      $api(`/workspace/${id.value}/activity`),
      $api('/meta/users'),
    ])
    ws.value = show.workspace || show
    members.value = mem.data || mem || []
    activity.value = act.data || []
    users.value = u.data || u || []
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
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Changement de rôle impossible'
  }
}

async function removeMember(m: any) {
  if (!confirm('Retirer ce membre ?'))
    return
  await $api(`/workspace/${id.value}/members/${m.id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="ws?.name || 'Espace collaboratif'"
      :subtitle="ws ? `${labelOf(workspaceTypeLabels, ws.type)} · ${members.length} membres` : ''"
      icon="tabler-users"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :to="{ name: 'espace-dossiers', query: { workspace: id } }"
        >
          Ouvrir les dossiers
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-user-plus"
          @click="addOpen = true"
        >
          Ajouter un membre
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
    >
      {{ errorMsg }}
    </VAlert>

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
          <VList>
            <VListItem
              v-for="m in members"
              :key="m.id"
              :title="m.user?.name || m.user?.email"
              :subtitle="labelOf(workspaceMemberRoleLabels, m.role)"
              prepend-icon="tabler-user"
            >
              <template #append>
                <div class="d-flex align-center gap-2">
                  <VSelect
                    v-if="m.role !== 'owner'"
                    :model-value="m.role"
                    :items="roleOptions"
                    density="compact"
                    hide-details
                    style="max-width: 160px"
                    @update:model-value="(v: string) => updateRole(m, v)"
                  />
                  <VBtn
                    v-if="m.role !== 'owner'"
                    size="small"
                    variant="text"
                    color="error"
                    @click="removeMember(m)"
                  >
                    Retirer
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VTabsWindowItem>
      <VTabsWindowItem value="activite">
        <VCard class="parapheur-section-card">
          <VTimeline
            v-if="activity.length"
            side="end"
            density="compact"
            class="pa-4"
          >
            <VTimelineItem
              v-for="a in activity"
              :key="a.id"
              size="small"
              dot-color="primary"
            >
              <div class="text-body-2">
                {{ a.summary }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ a.actor?.name }} · {{ formatDateTimeFr(a.created_at) }}
              </div>
            </VTimelineItem>
          </VTimeline>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            {{ loading ? 'Chargement…' : 'Aucune activité pour le moment.' }}
          </VCardText>
        </VCard>
      </VTabsWindowItem>
    </VTabsWindow>

    <VDialog
      v-model="addOpen"
      max-width="420"
    >
      <VCard>
        <VCardTitle>Ajouter un membre</VCardTitle>
        <VCardText>
          <VSelect
            v-model="addForm.user_id"
            :items="users.map((u: any) => ({ title: u.name || u.email, value: u.id }))"
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
            @click="addMember"
          >
            Ajouter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
