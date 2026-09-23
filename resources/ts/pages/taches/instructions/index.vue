<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  formatTaskDue,
  instructionStatusLabels,
  taskPriorityColor,
  taskStatusColor,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Instruction',
  },
})

const router = useRouter()
const instructions = ref<any[]>([])
const loading = ref(false)
const filterLate = ref(false)
const showCreate = ref(false)
const users = ref<{ id: number; name: string }[]>([])
const creating = ref(false)

const form = ref({
  title: '',
  body: '',
  assignee_id: null as number | null,
  priority: 'normale',
  due_date: '',
  create_execution_task: true,
})

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/instructions', { query: { overdue: filterLate.value ? 1 : undefined } })
    instructions.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

const loadUsers = async () => {
  const res = await $api('/meta/users')
  users.value = res.data ?? res
}

const setStatus = async (id: number, status: string) => {
  await $api(`/instructions/${id}/status`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

const create = async () => {
  creating.value = true
  try {
    await $api('/instructions', {
      method: 'POST',
      body: {
        ...form.value,
        due_date: form.value.due_date || null,
      },
    })
    showCreate.value = false
    form.value = { title: '', body: '', assignee_id: null, priority: 'normale', due_date: '', create_execution_task: true }
    await load()
  }
  finally {
    creating.value = false
  }
}

const isLate = (item: any) => {
  if (!item.due_date || ['executee', 'cloturee', 'annulee'].includes(item.status))
    return false
  return new Date(item.due_date) < new Date(new Date().toDateString())
}

onMounted(async () => {
  await Promise.all([load(), loadUsers()])
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Instructions"
      subtitle="Directives hiérarchiques et tâches d’exécution associées"
      icon="tabler-list-check"
    >
      <template #actions>
        <VSwitch
          v-model="filterLate"
          label="Retards"
          color="error"
          hide-details
          inset
          @update:model-value="load"
        />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showCreate = true"
        >
          Nouvelle instruction
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Référence</th>
            <th>Objet</th>
            <th>Destinataire</th>
            <th>Priorité</th>
            <th>Statut</th>
            <th>Échéance</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in instructions"
            :key="item.id"
            style="cursor: pointer"
            @click="router.push(`/taches/instructions/${item.id}`)"
          >
            <td>{{ item.reference || `#${item.id}` }}</td>
            <td>{{ item.title }}</td>
            <td>{{ item.assignee?.name || '—' }}</td>
            <td>
              <VChip
                size="small"
                :color="taskPriorityColor(item.priority)"
              >
                {{ item.priority }}
              </VChip>
            </td>
            <td>
              <VChip
                size="small"
                :color="isLate(item) ? 'error' : taskStatusColor(item.status === 'a_faire' ? 'imputee' : item.status)"
              >
                {{ instructionStatusLabels[item.status] || item.status }}
              </VChip>
            </td>
            <td :class="{ 'text-error': isLate(item) }">
              {{ formatTaskDue(item.due_date) }}
            </td>
            <td @click.stop>
              <div class="d-flex align-center gap-1 flex-nowrap">
                <VBtn
                  v-if="item.status === 'a_faire'"
                  icon
                  size="x-small"
                  variant="tonal"
                  color="info"
                  @click="setStatus(item.id, 'en_cours')"
                >
                  <VIcon
                    icon="tabler-player-play"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Passer en cours
                  </VTooltip>
                </VBtn>
                <VBtn
                  v-if="['a_faire', 'en_cours'].includes(item.status)"
                  icon
                  size="x-small"
                  variant="tonal"
                  color="success"
                  @click="setStatus(item.id, 'executee')"
                >
                  <VIcon
                    icon="tabler-circle-check"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Marquer exécutée
                  </VTooltip>
                </VBtn>
                <VBtn
                  v-if="item.status === 'executee'"
                  icon
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  @click="setStatus(item.id, 'cloturee')"
                >
                  <VIcon
                    icon="tabler-lock"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Clôturer
                  </VTooltip>
                </VBtn>
                <VBtn
                  icon
                  size="x-small"
                  variant="tonal"
                  @click="router.push(`/taches/instructions/${item.id}`)"
                >
                  <VIcon
                    icon="tabler-eye"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Ouvrir
                  </VTooltip>
                </VBtn>
                <VBtn
                  icon
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  @click="router.push({ path: '/taches', query: { instruction_id: item.id } })"
                >
                  <VIcon
                    icon="tabler-checkbox"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Voir les tâches
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog
      v-model="showCreate"
      max-width="560"
    >
      <VCard title="Nouvelle instruction">
        <VCardText>
          <AppTextField
            v-model="form.title"
            class="mb-3"
            label="Objet *"
          />
          <AppTextarea
            v-model="form.body"
            class="mb-3"
            label="Contenu"
            rows="3"
          />
          <AppSelect
            v-model="form.assignee_id"
            class="mb-3"
            label="Destinataire *"
            :items="users"
            item-title="name"
            item-value="id"
          />
          <AppTextField
            v-model="form.due_date"
            class="mb-3"
            label="Échéance"
            type="date"
          />
          <VSwitch
            v-model="form.create_execution_task"
            label="Créer une tâche d’exécution"
            color="primary"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showCreate = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="creating"
            @click="create"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
