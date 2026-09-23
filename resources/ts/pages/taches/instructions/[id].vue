<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  formatTaskDue,
  instructionStatusLabels,
  taskPriorityColor,
  taskPriorityLabels,
  taskStatusColor,
  taskStatusLabels,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Instruction',
  },
})

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const acting = ref(false)
const instruction = ref<any>(null)
const showAddTask = ref(false)
const activeTab = ref('tasks')
const users = ref<{ id: number; name: string }[]>([])
const taskForm = ref({
  title: '',
  description: '',
  assignee_id: null as number | null,
  due_at: '',
  priority: 'normale',
})

const id = computed(() => String(route.params.id))

const isOverdue = computed(() => {
  if (!instruction.value?.due_date || ['executee', 'cloturee', 'annulee'].includes(instruction.value.status))
    return false

  return instruction.value.is_overdue || new Date(instruction.value.due_date) < new Date()
})

const tasksCount = computed(() => (instruction.value?.tasks || []).length)
const updatesCount = computed(() => (instruction.value?.updates || []).length)

const statusActions = [
  { value: 'a_faire', label: 'À faire', icon: 'tabler-clipboard-list', color: 'secondary' },
  { value: 'en_cours', label: 'En cours', icon: 'tabler-player-play', color: 'info' },
  { value: 'executee', label: 'Exécutée', icon: 'tabler-circle-check', color: 'success' },
  { value: 'cloturee', label: 'Clôturer', icon: 'tabler-lock', color: 'primary' },
] as const

const load = async () => {
  loading.value = true
  try {
    instruction.value = await $api(`/instructions/${id.value}`)
  }
  finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  const res = await $api('/meta/users')
  users.value = res.data ?? res
}

const setStatus = async (status: string) => {
  acting.value = true
  try {
    await $api(`/instructions/${id.value}/status`, {
      method: 'PATCH',
      body: { status },
    })
    await load()
  }
  finally {
    acting.value = false
  }
}

const addTask = async () => {
  acting.value = true
  try {
    await $api(`/instructions/${id.value}/tasks`, {
      method: 'POST',
      body: {
        ...taskForm.value,
        due_at: taskForm.value.due_at || null,
      },
    })
    showAddTask.value = false
    taskForm.value = { title: '', description: '', assignee_id: null, due_at: '', priority: 'normale' }
    await load()
    activeTab.value = 'tasks'
  }
  finally {
    acting.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadUsers()])
})
</script>

<template>
  <div class="instruction-detail">
    <ParapheurPageHeader
      :title="instruction?.title || 'Instruction'"
      :subtitle="instruction?.reference || 'Fiche instruction'"
      icon="tabler-list-check"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push({ name: 'taches-instructions' })"
        >
          Retour
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showAddTask = true"
        >
          Tâche d’exécution
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading && !instruction"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="instruction">
      <VAlert
        v-if="isOverdue"
        type="error"
        variant="tonal"
        class="mb-4"
        density="comfortable"
        icon="tabler-alert-triangle"
      >
        Échéance dépassée — {{ formatTaskDue(instruction.due_date) }}
      </VAlert>

      <VRow dense>
        <VCol
          cols="12"
          lg="8"
        >
          <VCard class="mb-4">
            <VCardText class="pa-5">
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip
                  size="small"
                  :color="isOverdue ? 'error' : 'primary'"
                  variant="flat"
                >
                  {{ instructionStatusLabels[instruction.status] || instruction.status }}
                </VChip>
                <VChip
                  size="small"
                  :color="taskPriorityColor(instruction.priority)"
                  variant="tonal"
                >
                  {{ taskPriorityLabels[instruction.priority] || instruction.priority }}
                </VChip>
                <VChip
                  v-if="instruction.due_date"
                  size="small"
                  :color="isOverdue ? 'error' : 'default'"
                  variant="tonal"
                  prepend-icon="tabler-calendar-event"
                >
                  {{ formatTaskDue(instruction.due_date) }}
                </VChip>
                <VChip
                  v-if="isOverdue"
                  size="small"
                  color="error"
                  variant="tonal"
                >
                  En retard
                </VChip>
              </div>

              <div class="text-body-1 text-high-emphasis mb-1">
                Contenu
              </div>
              <div
                class="text-body-2"
                :class="{ 'text-medium-emphasis': !instruction.body }"
                style="white-space: pre-wrap"
              >
                {{ instruction.body || 'Aucun contenu détaillé.' }}
              </div>
            </VCardText>

            <VDivider />

            <VCardText class="d-flex flex-wrap gap-2 py-3">
              <VBtn
                v-for="s in statusActions"
                :key="s.value"
                size="small"
                :variant="instruction.status === s.value ? 'flat' : 'tonal'"
                :color="instruction.status === s.value ? s.color : 'default'"
                :prepend-icon="s.icon"
                :loading="acting"
                :disabled="acting || instruction.status === s.value"
                @click="setStatus(s.value)"
              >
                {{ s.label }}
              </VBtn>
            </VCardText>
          </VCard>

          <VCard>
            <VTabs
              v-model="activeTab"
              density="comfortable"
              color="primary"
              class="px-2"
            >
              <VTab value="tasks">
                <VIcon
                  start
                  icon="tabler-checkbox"
                />
                Tâches d’exécution
                <VChip
                  v-if="tasksCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ tasksCount }}
                </VChip>
              </VTab>
              <VTab value="updates">
                <VIcon
                  start
                  icon="tabler-history"
                />
                Historique
                <VChip
                  v-if="updatesCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ updatesCount }}
                </VChip>
              </VTab>
            </VTabs>

            <VDivider />

            <VWindow v-model="activeTab">
              <VWindowItem value="tasks">
                <div
                  v-if="!(instruction.tasks || []).length"
                  class="text-center py-10 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-clipboard-off"
                    size="40"
                    class="mb-2"
                  />
                  <div class="mb-1">
                    Aucune tâche liée
                  </div>
                  <div class="text-caption mb-4">
                    Créez une tâche d’exécution pour suivre le travail demandé.
                  </div>
                  <VBtn
                    size="small"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="showAddTask = true"
                  >
                    Ajouter une tâche
                  </VBtn>
                </div>

                <VList
                  v-else
                  lines="two"
                >
                  <VListItem
                    v-for="t in (instruction.tasks || [])"
                    :key="t.id"
                    class="cursor-pointer"
                    @click="router.push(`/taches/${t.id}`)"
                  >
                    <template #prepend>
                      <VAvatar
                        :color="taskStatusColor(t.status)"
                        variant="tonal"
                        rounded
                        size="40"
                      >
                        <VIcon
                          icon="tabler-checkbox"
                          size="20"
                        />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium">
                      {{ t.title }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      {{ t.reference || `#${t.id}` }}
                      · {{ t.assignee?.name || 'Non affectée' }}
                      · {{ formatTaskDue(t.due_at) }}
                    </VListItemSubtitle>
                    <template #append>
                      <VChip
                        size="small"
                        :color="taskStatusColor(t.status)"
                        variant="tonal"
                      >
                        {{ taskStatusLabels[t.status] || t.status }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>

                <template v-if="(instruction.tasks || []).length">
                  <VDivider />
                  <VCardText class="d-flex justify-end">
                    <VBtn
                      size="small"
                      color="primary"
                      prepend-icon="tabler-plus"
                      @click="showAddTask = true"
                    >
                      Ajouter une tâche
                    </VBtn>
                  </VCardText>
                </template>
              </VWindowItem>

              <VWindowItem value="updates">
                <div
                  v-if="!(instruction.updates || []).length"
                  class="text-center py-10 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-clock-off"
                    size="40"
                    class="mb-2"
                  />
                  <div>Aucune mise à jour</div>
                </div>
                <VTimeline
                  v-else
                  density="compact"
                  side="end"
                  class="pa-5"
                >
                  <VTimelineItem
                    v-for="u in (instruction.updates || [])"
                    :key="u.id"
                    size="x-small"
                    dot-color="primary"
                  >
                    <div class="text-body-2 font-weight-medium">
                      {{ u.body || instructionStatusLabels[u.status] || u.status || 'Mise à jour' }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ u.user?.name || 'Système' }}
                      <span v-if="u.created_at">
                        · {{ new Date(u.created_at).toLocaleString('fr-FR') }}
                      </span>
                    </div>
                  </VTimelineItem>
                </VTimeline>
              </VWindowItem>
            </VWindow>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard class="mb-4">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="info"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-users"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Acteurs
              </VCardTitle>
              <VCardSubtitle>
                Émetteur et destinataire
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VList density="comfortable">
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-user-up"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Émetteur
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ instruction.issuer?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-user-check"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Destinataire
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ instruction.assignee?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-building"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Structure
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ instruction.structure?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-calendar-event"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Échéance
                </VListItemTitle>
                <VListItemSubtitle
                  class="text-body-2"
                  :class="isOverdue ? 'text-error' : 'text-high-emphasis'"
                >
                  {{ formatTaskDue(instruction.due_date) }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCard>

          <VCard class="mb-4">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-flag"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Statut actuel
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <VChip
                :color="isOverdue ? 'error' : 'primary'"
                variant="tonal"
                class="mb-3"
              >
                {{ instructionStatusLabels[instruction.status] || instruction.status }}
              </VChip>
              <div class="text-caption text-medium-emphasis">
                Utilisez la barre d’actions de la fiche pour faire évoluer le statut.
              </div>
            </VCardText>
          </VCard>

          <VCard>
            <VCardText class="d-flex justify-space-between py-3">
              <div class="text-center flex-grow-1">
                <div class="text-h6">
                  {{ tasksCount }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Tâches
                </div>
              </div>
              <VDivider vertical />
              <div class="text-center flex-grow-1">
                <div class="text-h6">
                  {{ updatesCount }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Mises à jour
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog
      v-model="showAddTask"
      max-width="560"
    >
      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="40"
            >
              <VIcon
                icon="tabler-plus"
                size="22"
              />
            </VAvatar>
          </template>
          <VCardTitle>Nouvelle tâche d’exécution</VCardTitle>
          <VCardSubtitle>
            Rattachée à cette instruction
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <AppTextField
            v-model="taskForm.title"
            label="Objet *"
            class="mb-3"
          />
          <AppTextarea
            v-model="taskForm.description"
            label="Description"
            rows="3"
            class="mb-3"
          />
          <AppSelect
            v-model="taskForm.assignee_id"
            label="Responsable *"
            :items="users.map(u => ({ title: u.name, value: u.id }))"
            class="mb-3"
          />
          <AppSelect
            v-model="taskForm.priority"
            label="Priorité"
            :items="Object.entries(taskPriorityLabels).map(([value, title]) => ({ title, value }))"
            class="mb-3"
          />
          <AppTextField
            v-model="taskForm.due_at"
            label="Échéance"
            type="date"
          />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="showAddTask = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="acting"
            :disabled="!taskForm.title || !taskForm.assignee_id"
            @click="addTask"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
