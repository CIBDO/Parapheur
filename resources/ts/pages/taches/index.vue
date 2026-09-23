<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import {
  formatTaskDue,
  taskPriorityColor,
  taskPriorityLabels,
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

const filters = ref({
  q: '',
  status: '',
  mine: true as boolean | string,
  assigned_by_me: false as boolean | string,
  to_validate: false as boolean | string,
  overdue: false as boolean | string,
})

const view = computed(() => String(route.query.view || 'mine'))

const applyView = () => {
  filters.value.mine = view.value === 'mine'
  filters.value.assigned_by_me = view.value === 'assigned'
  filters.value.to_validate = view.value === 'validate'
  filters.value.overdue = view.value === 'overdue'
  if (view.value === 'all') {
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
      mine: filters.value.mine ? 1 : undefined,
      assigned_by_me: filters.value.assigned_by_me ? 1 : undefined,
      to_validate: filters.value.to_validate ? 1 : undefined,
      overdue: filters.value.overdue ? 1 : undefined,
    }),
  ])
}

watch(() => route.query.view, reload)
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
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push('/taches/nouvelle')"
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
            md="6"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="reload"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
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
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              block
              color="primary"
              @click="reload"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
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
  </div>
</template>
