<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'
import {
  formatTicketDateTime,
  formatTicketNumber,
  listItems,
  slaBadge,
  ticketPriorityColor,
  ticketPriorityLabel,
  ticketStatusColor,
  ticketStatusLabel,
} from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing' },
})

const router = useRouter()
const { tickets, fetchTickets, loading } = useTicketing()

onMounted(() => fetchTickets({ mine: 1, per_page: 50 }))

const items = computed(() => listItems(tickets.value).length ? listItems(tickets.value) : tickets.value)

const headers = [
  { title: 'N°', key: 'number', width: '140px' },
  { title: 'Titre', key: 'title' },
  { title: 'Statut', key: 'status', width: '140px' },
  { title: 'Priorité', key: 'priority', width: '120px' },
  { title: 'SLA', key: 'sla', width: '120px' },
  { title: 'Créé le', key: 'created_at', width: '150px' },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mes tickets"
      subtitle="Tickets dont je suis demandeur ou assigné"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'ticketing-nouveau' }"
        >
          Nouveau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id"
        hover
        @click:row="(_: any, { item }: any) => router.push({ name: 'ticketing-id', params: { id: item.id } })"
      >
        <template #item.number="{ item }">
          <RouterLink
            class="font-weight-medium text-primary"
            :to="{ name: 'ticketing-id', params: { id: item.id } }"
            @click.stop
          >
            {{ formatTicketNumber(item) }}
          </RouterLink>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="ticketStatusColor(item.status)"
            variant="tonal"
          >
            {{ item.status_label || ticketStatusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="ticketPriorityColor(item.priority)"
            variant="tonal"
          >
            {{ ticketPriorityLabel(item.priority) }}
          </VChip>
        </template>
        <template #item.sla="{ item }">
          <VChip
            size="small"
            :color="slaBadge(item.sla).color"
            variant="tonal"
          >
            {{ slaBadge(item.sla).label }}
          </VChip>
        </template>
        <template #item.created_at="{ item }">
          {{ formatTicketDateTime(item.created_at) }}
        </template>
        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            Aucun ticket.
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
