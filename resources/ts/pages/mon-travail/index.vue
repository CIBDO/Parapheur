<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import CreateTaskDialog from '@/components/tasks/CreateTaskDialog.vue'
import { useMyWork } from '@/composables/useMyWork'
import { formatTaskDue, taskPriorityColor, taskStatusColor, taskStatusLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'MyWork',
  },
})

const router = useRouter()
const { loading, data, load } = useMyWork()
const showCreateDialog = ref(false)

const cards = computed(() => [
  {
    key: 'a_faire',
    title: 'À faire',
    hint: 'Tâches ouvertes',
    color: 'primary',
    icon: 'tabler-checkbox',
    to: { name: 'taches' },
  },
  {
    key: 'a_valider',
    title: 'À valider',
    hint: 'Décision attendue',
    color: 'info',
    icon: 'tabler-checks',
    to: { name: 'taches', query: { view: 'validate' } },
  },
  {
    key: 'en_retard',
    title: 'En retard',
    hint: 'Échéance dépassée',
    color: 'error',
    icon: 'tabler-alert-triangle',
    to: { name: 'taches', query: { view: 'overdue' } },
  },
  {
    key: 'aujourdhui',
    title: 'Aujourd’hui',
    hint: 'À traiter ce jour',
    color: 'warning',
    icon: 'tabler-calendar-event',
    to: { name: 'taches-calendrier' },
  },
  {
    key: 'en_attente',
    title: 'En attente',
    hint: 'Bloquées / pause',
    color: 'secondary',
    icon: 'tabler-clock-pause',
    to: { name: 'taches' },
  },
  {
    key: 'instructions',
    title: 'Instructions',
    hint: 'Directives ouvertes',
    color: 'success',
    icon: 'tabler-list-check',
    to: { name: 'taches-instructions' },
  },
])

const quickActions = [
  { title: 'Nouvelle tâche', icon: 'tabler-checkbox', color: 'primary', action: 'create-task' as const },
  { title: 'Instructions', icon: 'tabler-list-check', color: 'success', route: 'taches-instructions' },
  { title: 'Kanban', icon: 'tabler-layout-kanban', color: 'info', route: 'taches-kanban' },
  { title: 'Tickets', icon: 'tabler-ticket', color: 'warning', route: 'ticketing' },
  { title: 'Courrier', icon: 'tabler-mail', color: 'secondary', route: 'courrier' },
  { title: 'Parapheur', icon: 'tabler-file', color: 'primary', route: 'parapheur' },
]

const onQuickAction = (action: typeof quickActions[number]) => {
  if ('action' in action && action.action === 'create-task') {
    showCreateDialog.value = true

    return
  }
  if ('route' in action && action.route)
    router.push({ name: action.route })
}

onMounted(() => load(12))

const otherIcon = (type?: string) => {
  switch (type) {
    case 'courrier': return 'tabler-mail'
    case 'ticket': return 'tabler-ticket'
    case 'parapheur': return 'tabler-file'
    case 'meeting': return 'tabler-users-group'
    case 'appointment': return 'tabler-calendar-event'
    default: return 'tabler-click'
  }
}
</script>

<template>
  <div class="mon-travail-dashboard">
    <ParapheurPageHeader
      title="Mon Travail"
      subtitle="Synthèse de vos actions à traiter aujourd’hui"
      icon="tabler-layout-dashboard"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load()"
        >
          Actualiser
        </VBtn>
        <VMenu>
          <template #activator="{ props }">
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              v-bind="props"
            >
              Nouveau
            </VBtn>
          </template>
          <VList>
            <VListItem
              prepend-icon="tabler-checkbox"
              title="Nouvelle tâche"
              @click="showCreateDialog = true"
            />
            <VListItem
              prepend-icon="tabler-list-check"
              title="Nouvelle instruction"
              @click="router.push({ name: 'taches-instructions' })"
            />
            <VListItem
              prepend-icon="tabler-ticket"
              title="Nouveau ticket"
              @click="router.push({ name: 'ticketing-nouveau' })"
            />
            <VListItem
              prepend-icon="tabler-mail"
              title="Nouveau courrier"
              @click="router.push({ name: 'courrier-entrants-nouveau' })"
            />
            <VListItem
              prepend-icon="tabler-users-group"
              title="Nouvelle réunion"
              @click="router.push('/parapheur/reunions/nouvelle')"
            />
            <VListItem
              prepend-icon="tabler-file-plus"
              title="Nouveau document"
              @click="router.push({ name: 'parapheur-nouveau' })"
            />
          </VList>
        </VMenu>
      </template>
    </ParapheurPageHeader>

    <!-- Actions rapides -->
    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap gap-2 py-3">
        <VBtn
          v-for="action in quickActions"
          :key="action.title"
          size="small"
          variant="tonal"
          :color="action.color"
          :prepend-icon="action.icon"
          @click="onQuickAction(action)"
        >
          {{ action.title }}
        </VBtn>
        <VSpacer />
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'taches' }"
        >
          Mes tâches
        </VBtn>
        <VBtn
          size="small"
          variant="text"
          :to="{ name: 'taches-calendrier' }"
        >
          Calendrier
        </VBtn>
      </VCardText>
    </VCard>

    <!-- KPI -->
    <div
      v-if="loading && !Object.keys(data.counts).length"
      class="text-center py-8"
    >
      <VProgressCircular indeterminate />
    </div>
    <VRow
      v-else
      dense
      class="mb-4"
    >
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="12"
        sm="6"
        md="4"
        lg="2"
      >
        <VCard
          class="kpi-card cursor-pointer h-100"
          @click="router.push(card.to)"
        >
          <VCardText class="d-flex align-center gap-4 pa-4">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="48"
            >
              <VIcon
                :icon="card.icon"
                size="26"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-h4 font-weight-semibold lh-1 mb-1">
                {{ data.counts[card.key] ?? 0 }}
              </div>
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ card.title }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ card.hint }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow dense>
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-checkbox"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Mes tâches
            </VCardTitle>
            <VCardSubtitle>
              Actions prioritaires à traiter
            </VCardSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                :to="{ name: 'taches' }"
              >
                Voir toutes
              </VBtn>
            </template>
          </VCardItem>
          <VDivider />
          <VList
            v-if="data.tasks.length"
            lines="two"
          >
            <VListItem
              v-for="task in data.tasks"
              :key="task.id"
              :title="task.title"
              :subtitle="task.reference"
              class="cursor-pointer"
              @click="router.push(task.url)"
            >
              <template #append>
                <div class="text-end">
                  <VChip
                    size="small"
                    :color="taskStatusColor(task.status)"
                    variant="tonal"
                    class="mb-1"
                  >
                    {{ taskStatusLabels[task.status] || task.status }}
                  </VChip>
                  <div
                    class="text-caption"
                    :class="{ 'text-error': task.is_overdue }"
                  >
                    {{ formatTaskDue(task.due_at) }}
                  </div>
                </div>
              </template>
            </VListItem>
          </VList>
          <div
            v-else-if="!loading"
            class="text-center py-10 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-clipboard-off"
              size="40"
              class="mb-2"
            />
            <div>Aucune tâche ouverte</div>
          </div>
          <div
            v-else
            class="text-center py-10"
          >
            <VProgressCircular
              indeterminate
              size="32"
            />
          </div>
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
                color="success"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-list-check"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Instructions
            </VCardTitle>
            <VCardSubtitle>
              Directives à exécuter
            </VCardSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                :to="{ name: 'taches-instructions' }"
              >
                Voir
              </VBtn>
            </template>
          </VCardItem>
          <VDivider />
          <VList v-if="data.instructions.length">
            <VListItem
              v-for="ins in data.instructions"
              :key="ins.id"
              :title="ins.title"
              :subtitle="ins.reference || 'Instruction'"
              class="cursor-pointer"
              @click="router.push(ins.url || `/taches/instructions/${ins.id}`)"
            >
              <template #append>
                <VChip
                  size="small"
                  :color="ins.is_overdue ? 'error' : taskPriorityColor(ins.priority)"
                  variant="tonal"
                >
                  {{ formatTaskDue(ins.due_at) }}
                </VChip>
              </template>
            </VListItem>
          </VList>
          <div
            v-else-if="!loading"
            class="text-center py-8 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-list-check"
              size="36"
              class="mb-2"
            />
            <div>Aucune instruction ouverte</div>
          </div>
          <div
            v-else
            class="text-center py-8"
          >
            <VProgressCircular
              indeterminate
              size="28"
            />
          </div>
        </VCard>

        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-click"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Autres actions
            </VCardTitle>
            <VCardSubtitle>
              Courrier, tickets, parapheur…
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VList v-if="data.other.length">
            <VListItem
              v-for="(item, idx) in data.other"
              :key="idx"
              :title="item.label"
              :prepend-icon="otherIcon(item.type)"
              class="cursor-pointer"
              @click="router.push(item.url)"
            />
          </VList>
          <div
            v-else-if="!loading"
            class="text-center py-8 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-circle-check"
              size="36"
              class="mb-2"
            />
            <div>Pas d’autre action signalée</div>
          </div>
          <div
            v-else
            class="text-center py-8"
          >
            <VProgressCircular
              indeterminate
              size="28"
            />
          </div>
        </VCard>
      </VCol>
    </VRow>

    <CreateTaskDialog
      v-model:is-dialog-visible="showCreateDialog"
      @created="load()"
    />
  </div>
</template>

<style scoped>
.kpi-card {
  transition: box-shadow 0.18s ease, transform 0.18s ease;
}

.kpi-card:hover {
  box-shadow: 0 6px 18px rgba(var(--v-theme-on-surface), 0.08);
  transform: translateY(-1px);
}
</style>
