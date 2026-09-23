<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import CreateTaskDialog from '@/components/tasks/CreateTaskDialog.vue'
import { useTasks } from '@/composables/useTasks'
import {
  formatTaskDue,
  taskPriorityColor,
  taskPriorityLabels,
  taskSourceLabels,
  taskStatusColor,
  taskStatusLabels,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Task',
  },
})

const router = useRouter()
const route = useRoute()
const { loading, items, meta, dashboard, list, loadDashboard } = useTasks()

const showCreateDialog = ref(false)
const showAdvanced = ref(false)
const filters = ref({
  q: '',
  status: '',
  priority: '',
  source_kind: '',
  due_from: '',
  due_to: '',
  tag: '',
  mine: true as boolean | string,
  assigned_by_me: false as boolean | string,
  to_validate: false as boolean | string,
  overdue: false as boolean | string,
  team: false as boolean | string,
})

const view = computed(() => String(route.query.view || 'mine'))

const applyView = () => {
  filters.value.mine = view.value === 'mine'
  filters.value.assigned_by_me = view.value === 'assigned'
  filters.value.to_validate = view.value === 'validate'
  filters.value.overdue = view.value === 'overdue'
  filters.value.team = view.value === 'team'
  if (view.value === 'all' || view.value === 'team') {
    filters.value.mine = false
    filters.value.assigned_by_me = false
    filters.value.to_validate = false
    filters.value.overdue = false
  }
}

const title = computed(() => {
  switch (view.value) {
    case 'assigned': return 'Tâches imputées par moi'
    case 'validate': return 'À valider'
    case 'overdue': return 'Tâches en retard'
    case 'team': return 'Tâches de mon équipe'
    case 'all': return 'Toutes les tâches'
    default: return 'Mes tâches'
  }
})

const reload = async () => {
  applyView()
  await Promise.all([
    loadDashboard(),
    list({
      q: filters.value.q || undefined,
      status: filters.value.status || undefined,
      priority: filters.value.priority || undefined,
      source_kind: filters.value.source_kind || undefined,
      due_from: filters.value.due_from || undefined,
      due_to: filters.value.due_to || undefined,
      tag: filters.value.tag || undefined,
      instruction_id: route.query.instruction_id || undefined,
      mine: filters.value.mine ? 1 : undefined,
      assigned_by_me: filters.value.assigned_by_me ? 1 : undefined,
      to_validate: filters.value.to_validate ? 1 : undefined,
      overdue: filters.value.overdue ? 1 : undefined,
      team: filters.value.team ? 1 : undefined,
    }),
  ])
}

watch(() => route.query.view, reload)
watch(() => route.query.instruction_id, reload)
onMounted(reload)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="title"
      subtitle="Pilotage des tâches et instructions transversales"
      icon="tabler-checkbox"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-layout-kanban"
          @click="router.push('/taches/kanban')"
        >
          Kanban
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showCreateDialog = true"
        >
          Nouvelle tâche
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="reload"
        >
          Actualiser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow class="mb-4">
      <VCol
        cols="6"
        md="3"
      >
        <VCard><VCardText>À faire : <strong>{{ dashboard.a_faire ?? 0 }}</strong></VCardText></VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard><VCardText>En retard : <strong>{{ dashboard.en_retard ?? 0 }}</strong></VCardText></VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard><VCardText>À valider : <strong>{{ dashboard.a_valider ?? 0 }}</strong></VCardText></VCard>
      </VCol>
      <VCol
        cols="6"
        md="3"
      >
        <VCard><VCardText>Aujourd’hui : <strong>{{ dashboard.aujourdhui ?? 0 }}</strong></VCardText></VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche (réf., objet, agent…)"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="reload"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.status"
              label="Statut"
              clearable
              :items="Object.entries(taskStatusLabels).map(([value, title]) => ({ title, value }))"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.priority"
              label="Priorité"
              clearable
              :items="Object.entries(taskPriorityLabels).map(([value, title]) => ({ title, value }))"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center gap-2"
          >
            <VBtn
              color="primary"
              @click="reload"
            >
              Filtrer
            </VBtn>
            <VBtn
              icon="tabler-filter"
              variant="tonal"
              :color="showAdvanced ? 'primary' : undefined"
              @click="showAdvanced = !showAdvanced"
            />
          </VCol>
        </VRow>
        <VExpandTransition>
          <VRow
            v-if="showAdvanced"
            class="mt-1"
          >
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="filters.source_kind"
                label="Source"
                clearable
                :items="Object.entries(taskSourceLabels).map(([value, title]) => ({ title, value }))"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="filters.due_from"
                label="Échéance du"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="filters.due_to"
                label="Échéance au"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="filters.tag"
                label="Tag"
                clearable
              />
            </VCol>
          </VRow>
        </VExpandTransition>
      </VCardText>
      <VDivider />
      <VTable>
        <thead>
          <tr>
            <th>Référence</th>
            <th>Objet</th>
            <th>Responsable</th>
            <th>Priorité</th>
            <th>Statut</th>
            <th>Échéance</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            style="cursor: pointer"
            @click="router.push(`/taches/${item.id}`)"
          >
            <td>{{ item.reference }}</td>
            <td>{{ item.title }}</td>
            <td>{{ item.assignee?.name || '—' }}</td>
            <td>
              <VChip
                size="small"
                :color="taskPriorityColor(item.priority)"
              >
                {{ taskPriorityLabels[item.priority] || item.priority }}
              </VChip>
            </td>
            <td>
              <VChip
                size="small"
                :color="taskStatusColor(item.status)"
              >
                {{ taskStatusLabels[item.status] || item.status }}
              </VChip>
            </td>
            <td :class="{ 'text-error': item.due_at && new Date(item.due_at) < new Date() && !['validee', 'annulee'].includes(item.status) }">
              {{ formatTaskDue(item.due_at) }}
            </td>
          </tr>
          <tr v-if="!items.length && !loading">
            <td
              colspan="6"
              class="text-center text-medium-emphasis py-8"
            >
              Aucune tâche
            </td>
          </tr>
        </tbody>
      </VTable>
      <VCardText
        v-if="meta.total"
        class="text-caption"
      >
        {{ meta.total }} résultat(s)
      </VCardText>
    </VCard>

    <CreateTaskDialog
      v-model:is-dialog-visible="showCreateDialog"
      :redirect-on-create="false"
      @created="reload"
    />
  </div>
</template>
