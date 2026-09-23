<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import {
  formatTaskDue,
  taskKanbanColumnOrder,
  taskPriorityColor,
  taskStatusColor,
  taskStatusLabels,
} from '@/utils/tasksUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Task', navActiveLink: 'taches-kanban' },
})

const router = useRouter()
const { fetchKanban } = useTasks()

const loading = ref(true)
const columns = ref<Record<string, any[]>>({})
const scopeMine = ref(true)
const errorMsg = ref('')

const orderedColumns = computed(() => {
  const keys = [
    ...taskKanbanColumnOrder.filter(k => columns.value[k]?.length),
    ...Object.keys(columns.value).filter(k => !taskKanbanColumnOrder.includes(k)),
  ]

  return keys.map(status => ({
    status,
    label: taskStatusLabels[status] || status,
    color: taskStatusColor(status),
    items: columns.value[status] || [],
  }))
})

async function loadKanban() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await fetchKanban({ mine: scopeMine.value ? 1 : undefined })
    columns.value = res?.columns || {}
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger le kanban'
    columns.value = {}
  }
  finally {
    loading.value = false
  }
}

onMounted(loadKanban)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Kanban des tâches"
      subtitle="Vue par statut — les transitions restent contrôlées sur la fiche"
      icon="tabler-layout-kanban"
    >
      <template #actions>
        <VSwitch
          v-model="scopeMine"
          label="Mes tâches"
          color="primary"
          hide-details
          density="compact"
          class="me-2"
          @update:model-value="loadKanban"
        />
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadKanban"
        >
          Actualiser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMsg"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMsg }}
    </VAlert>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <div
      v-else
      class="d-flex gap-4 overflow-x-auto pb-4"
      style="min-height: 420px;"
    >
      <VCard
        v-for="col in orderedColumns"
        :key="col.status"
        class="flex-shrink-0"
        style="width: 280px;"
      >
        <VCardItem class="pb-0">
          <VCardTitle class="d-flex align-center gap-2 text-body-1">
            <VChip
              size="small"
              :color="col.color"
            >
              {{ col.label }}
            </VChip>
            <span class="text-caption text-medium-emphasis">{{ col.items.length }}</span>
          </VCardTitle>
        </VCardItem>
        <VCardText class="d-flex flex-column gap-2">
          <VSheet
            v-for="item in col.items"
            :key="item.id"
            border
            rounded
            class="pa-3 cursor-pointer"
            @click="router.push(`/taches/${item.id}`)"
          >
            <div class="text-caption text-medium-emphasis">
              {{ item.reference }}
            </div>
            <div class="font-weight-medium text-body-2 mb-1">
              {{ item.title }}
            </div>
            <div class="d-flex justify-space-between align-center">
              <VChip
                size="x-small"
                :color="taskPriorityColor(item.priority)"
                variant="tonal"
              >
                {{ item.assignee?.name || '—' }}
              </VChip>
              <span
                class="text-caption"
                :class="{ 'text-error': item.is_overdue }"
              >
                {{ formatTaskDue(item.due_at) }}
              </span>
            </div>
          </VSheet>
          <div
            v-if="!col.items.length"
            class="text-caption text-medium-emphasis text-center py-4"
          >
            Aucune tâche
          </div>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>
