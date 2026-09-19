<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'
import {
  formatTicketNumber,
  ticketKanbanColumnOrder,
  ticketPriorityColor,
  ticketPriorityLabel,
  ticketStatusColor,
  ticketStatusLabel,
} from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing' },
})

const router = useRouter()
const { fetchMeta, fetchKanban, changeStatus } = useTicketing()

const loading = ref(true)
const teams = ref<any[]>([])
const teamId = ref<number | null>(null)
const columns = ref<Record<string, any[]>>({})
const statusDialog = ref(false)
const selectedTicket = ref<any>(null)
const targetStatus = ref<string | null>(null)
const statusSaving = ref(false)
const errorMsg = ref('')

const teamItems = computed(() =>
  teams.value.map(t => ({ value: t.id, title: t.name })),
)

const statusItems = computed(() =>
  ticketKanbanColumnOrder.map(code => ({
    value: code,
    title: ticketStatusLabel(code),
  })),
)

const orderedColumns = computed(() => {
  const keys = [
    ...ticketKanbanColumnOrder.filter(k => columns.value[k]?.length),
    ...Object.keys(columns.value).filter(k => !ticketKanbanColumnOrder.includes(k)),
  ]

  return keys.map(status => ({
    status,
    label: ticketStatusLabel(status),
    color: ticketStatusColor(status),
    items: columns.value[status] || [],
  }))
})

onMounted(async () => {
  try {
    const meta = await fetchMeta()
    teams.value = meta?.teams || []
    if (teams.value.length) {
      teamId.value = teams.value[0].id
      await loadKanban()
    }
  }
  finally {
    loading.value = false
  }
})

async function loadKanban() {
  if (!teamId.value)
    return
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await fetchKanban(teamId.value)
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

function openStatusChange(ticket: any) {
  selectedTicket.value = ticket
  targetStatus.value = ticket.status
  statusDialog.value = true
}

async function applyStatus() {
  if (!selectedTicket.value || !targetStatus.value)
    return
  statusSaving.value = true
  try {
    await changeStatus(selectedTicket.value.id, { status: targetStatus.value })
    statusDialog.value = false
    await loadKanban()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Changement de statut impossible'
  }
  finally {
    statusSaving.value = false
  }
}

async function onDrop(status: string, event: DragEvent) {
  event.preventDefault()
  const id = Number(event.dataTransfer?.getData('text/ticket-id'))
  if (!id || !status)
    return
  try {
    await changeStatus(id, { status })
    await loadKanban()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Déplacement impossible'
  }
}

function onDragStart(ticket: any, event: DragEvent) {
  event.dataTransfer?.setData('text/ticket-id', String(ticket.id))
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Kanban"
      subtitle="Vue par statut — glisser-déposer ou changer le statut"
    >
      <template #actions>
        <AppSelect
          v-model="teamId"
          :items="teamItems"
          label="Équipe"
          style="min-inline-size: 220px"
          hide-details
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
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>

    <div
      v-if="loading && !orderedColumns.length"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <div
      v-else-if="!teamId"
      class="text-center py-10 text-medium-emphasis"
    >
      Sélectionnez une équipe pour afficher le kanban.
    </div>

    <div
      v-else
      class="kanban-board d-flex gap-3 overflow-x-auto pb-4"
    >
      <VCard
        v-for="col in orderedColumns"
        :key="col.status"
        class="kanban-column"
        variant="outlined"
        @dragover.prevent
        @drop="onDrop(col.status, $event)"
      >
        <VCardItem class="py-2">
          <VChip
            size="small"
            :color="col.color"
            variant="tonal"
          >
            {{ col.label }}
          </VChip>
          <template #append>
            <span class="text-caption">{{ col.items.length }}</span>
          </template>
        </VCardItem>
        <VDivider />
        <VCardText class="kanban-column-body pa-2">
          <VCard
            v-for="ticket in col.items"
            :key="ticket.id"
            class="mb-2 cursor-grab"
            variant="tonal"
            draggable="true"
            @dragstart="onDragStart(ticket, $event)"
            @click="router.push({ name: 'ticketing-id', params: { id: ticket.id } })"
          >
            <VCardText class="pa-3">
              <div class="text-caption font-weight-medium text-primary mb-1">
                {{ formatTicketNumber(ticket) }}
              </div>
              <div class="text-body-2 mb-2">
                {{ ticket.title }}
              </div>
              <div class="d-flex align-center justify-space-between gap-2">
                <VChip
                  size="x-small"
                  :color="ticketPriorityColor(ticket.priority)"
                  variant="flat"
                >
                  {{ ticketPriorityLabel(ticket.priority) }}
                </VChip>
                <VBtn
                  size="x-small"
                  variant="text"
                  icon="tabler-arrows-exchange"
                  @click.stop="openStatusChange(ticket)"
                />
              </div>
              <div
                v-if="ticket.assignee"
                class="text-caption text-medium-emphasis mt-1"
              >
                {{ ticket.assignee.name }}
              </div>
            </VCardText>
          </VCard>
        </VCardText>
      </VCard>
    </div>

    <VDialog
      v-model="statusDialog"
      max-width="420"
    >
      <VCard>
        <VCardTitle>Changer le statut</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="targetStatus"
            :items="statusItems"
            label="Nouveau statut"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="statusDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="statusSaving"
            @click="applyStatus"
          >
            Appliquer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.kanban-column {
  min-inline-size: 260px;
  max-inline-size: 280px;
  flex: 0 0 auto;
}

.kanban-column-body {
  min-block-size: 320px;
  max-block-size: 70vh;
  overflow-y: auto;
}
</style>
