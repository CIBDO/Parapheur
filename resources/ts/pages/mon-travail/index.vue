<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
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

const cards = computed(() => [
  { key: 'a_faire', title: 'À faire', color: 'primary', icon: 'tabler-checkbox' },
  { key: 'a_valider', title: 'À valider', color: 'info', icon: 'tabler-checks' },
  { key: 'en_retard', title: 'En retard', color: 'error', icon: 'tabler-alert-triangle' },
  { key: 'aujourdhui', title: 'Aujourd’hui', color: 'warning', icon: 'tabler-calendar-event' },
  { key: 'en_attente', title: 'En attente', color: 'secondary', icon: 'tabler-clock-pause' },
  { key: 'instructions', title: 'Instructions', color: 'primary', icon: 'tabler-list-check' },
])

onMounted(() => load(12))
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mon Travail"
      subtitle="Synthèse de vos actions à traiter aujourd’hui"
      icon="tabler-layout-dashboard"
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
          @click="load()"
        >
          Actualiser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow class="mb-6">
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="6"
        md="4"
        lg="2"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="card.icon" />
            </VAvatar>
            <div>
              <div class="text-h5">
                {{ data.counts[card.key] ?? 0 }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ card.title }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Mes tâches</VCardTitle>
          </VCardItem>
          <VDivider />
          <VList lines="two">
            <VListItem
              v-for="task in data.tasks"
              :key="task.id"
              :title="task.title"
              :subtitle="task.reference"
              @click="router.push(task.url)"
            >
              <template #append>
                <div class="text-end">
                  <VChip
                    size="small"
                    :color="taskStatusColor(task.status)"
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
            <VListItem v-if="!data.tasks.length && !loading">
              <VListItemTitle class="text-medium-emphasis">
                Aucune tâche ouverte
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="5"
      >
        <VCard class="mb-4">
          <VCardItem>
            <VCardTitle>Instructions</VCardTitle>
          </VCardItem>
          <VDivider />
          <VList>
            <VListItem
              v-for="ins in data.instructions"
              :key="ins.id"
              :title="ins.title"
              :subtitle="ins.reference || 'Instruction'"
              @click="router.push('/taches/instructions')"
            >
              <template #append>
                <VChip
                  size="small"
                  :color="ins.is_overdue ? 'error' : taskPriorityColor(ins.priority)"
                >
                  {{ formatTaskDue(ins.due_at) }}
                </VChip>
              </template>
            </VListItem>
            <VListItem v-if="!data.instructions.length && !loading">
              <VListItemTitle class="text-medium-emphasis">
                Aucune instruction ouverte
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCard>

        <VCard>
          <VCardItem>
            <VCardTitle>Autres actions</VCardTitle>
          </VCardItem>
          <VDivider />
          <VList>
            <VListItem
              v-for="(item, idx) in data.other"
              :key="idx"
              :title="item.label"
              :prepend-icon="item.type === 'courrier' ? 'tabler-mail' : 'tabler-ticket'"
              @click="router.push(item.url)"
            />
            <VListItem v-if="!data.other.length && !loading">
              <VListItemTitle class="text-medium-emphasis">
                Pas d’autre action signalée
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
