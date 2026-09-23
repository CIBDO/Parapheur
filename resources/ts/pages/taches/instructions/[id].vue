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
const users = ref<{ id: number; name: string }[]>([])
const taskForm = ref({
  title: '',
  description: '',
  assignee_id: null as number | null,
  due_at: '',
  priority: 'normale',
})

const id = computed(() => String(route.params.id))

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
  <div>
    <ParapheurPageHeader
      :title="instruction?.title || 'Instruction'"
      :subtitle="instruction?.reference || 'Fiche instruction'"
      icon="tabler-list-check"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/taches/instructions')"
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
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="instruction">
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle>Synthèse</VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip
                  size="small"
                  color="primary"
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
                  v-if="instruction.is_overdue || (instruction.due_date && new Date(instruction.due_date) < new Date())"
                  size="small"
                  color="error"
                >
                  En retard
                </VChip>
              </div>
              <p class="text-body-1 whitespace-pre-wrap">
                {{ instruction.body || 'Pas de contenu détaillé.' }}
              </p>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle>Tâches d’exécution</VCardTitle>
            </VCardItem>
            <VDivider />
            <VList lines="two">
              <VListItem
                v-for="t in (instruction.tasks || [])"
                :key="t.id"
                :title="t.title"
                :subtitle="t.reference"
                @click="router.push(`/taches/${t.id}`)"
              >
                <template #append>
                  <div class="text-end">
                    <VChip
                      size="small"
                      :color="taskStatusColor(t.status)"
                      class="mb-1"
                    >
                      {{ taskStatusLabels[t.status] || t.status }}
                    </VChip>
                    <div class="text-caption">
                      {{ t.assignee?.name || '—' }} · {{ formatTaskDue(t.due_at) }}
                    </div>
                  </div>
                </template>
              </VListItem>
              <VListItem v-if="!(instruction.tasks || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune tâche liée
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCard>

          <VCard>
            <VCardItem>
              <VCardTitle>Historique</VCardTitle>
            </VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="u in (instruction.updates || [])"
                :key="u.id"
                :title="u.body || u.status || 'Mise à jour'"
                :subtitle="`${u.user?.name || 'Système'} · ${u.created_at ? new Date(u.created_at).toLocaleString('fr-FR') : ''}`"
              />
              <VListItem v-if="!(instruction.updates || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune mise à jour
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <VCard class="mb-4">
            <VCardItem><VCardTitle>Acteurs</VCardTitle></VCardItem>
            <VDivider />
            <VCardText>
              <div class="mb-2">
                <div class="text-caption text-medium-emphasis">
                  Émetteur
                </div>
                <div>{{ instruction.issuer?.name || '—' }}</div>
              </div>
              <div class="mb-2">
                <div class="text-caption text-medium-emphasis">
                  Destinataire
                </div>
                <div>{{ instruction.assignee?.name || '—' }}</div>
              </div>
              <div class="mb-2">
                <div class="text-caption text-medium-emphasis">
                  Structure
                </div>
                <div>{{ instruction.structure?.name || '—' }}</div>
              </div>
              <div>
                <div class="text-caption text-medium-emphasis">
                  Échéance
                </div>
                <div>{{ formatTaskDue(instruction.due_date) }}</div>
              </div>
            </VCardText>
          </VCard>

          <VCard>
            <VCardItem><VCardTitle>Statut</VCardTitle></VCardItem>
            <VDivider />
            <VCardText class="d-flex flex-column gap-2">
              <VBtn
                v-for="s in ['a_faire', 'en_cours', 'executee', 'cloturee']"
                :key="s"
                variant="tonal"
                :disabled="acting || instruction.status === s"
                @click="setStatus(s)"
              >
                {{ instructionStatusLabels[s] }}
              </VBtn>
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
          <VCardTitle>Nouvelle tâche d’exécution</VCardTitle>
        </VCardItem>
        <VCardText>
          <AppTextField
            v-model="taskForm.title"
            label="Objet"
            class="mb-3"
          />
          <AppTextarea
            v-model="taskForm.description"
            label="Description"
            class="mb-3"
          />
          <AppSelect
            v-model="taskForm.assignee_id"
            label="Responsable"
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
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
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
