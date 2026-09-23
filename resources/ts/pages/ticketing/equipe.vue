<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/listItems'
import { formatTicketDateTime, formatTicketNumber, slaBadge, ticketPriorityColor, ticketPriorityLabel, ticketStatusColor, ticketStatusLabel } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const router = useRouter()
const { tickets, fetchTickets, loading } = useTicketing()

onMounted(() => fetchTickets({ scope: 'team', per_page: 50 }))

const items = computed(() => listItems(tickets.value).length ? listItems(tickets.value) : tickets.value)

const headers = [
  { title: 'N°', key: 'number', width: '140px' },
  { title: 'Titre', key: 'title' },
  { title: 'Assigné', key: 'assignee', width: '160px' },
  { title: 'Équipe', key: 'team', width: '140px' },
  { title: 'Statut', key: 'status', width: '140px' },
  { title: 'Priorité', key: 'priority', width: '120px' },
  { title: 'SLA', key: 'sla', width: '120px' },
  { title: 'Créé le', key: 'created_at', width: '150px' },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Tickets équipe"
      subtitle="Tickets des équipes de support dont je suis membre"
    />

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
        <template #item.assignee="{ item }">
          {{ item.assignee?.name || '—' }}
        </template>
        <template #item.team="{ item }">
          {{ item.team?.name || '—' }}
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
            Aucun ticket d'équipe.
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
