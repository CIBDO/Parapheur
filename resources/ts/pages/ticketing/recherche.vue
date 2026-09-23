<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/listItems'
import { formatTicketDateTime, formatTicketNumber, slaBadge, ticketPriorityColor, ticketPriorityLabel, ticketStatusColor, ticketStatusLabel, ticketKanbanColumnOrder } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const router = useRouter()
const { searchTickets, fetchMeta, tickets, loading } = useTicketing()

const teams = ref<any[]>([])
const priorities = ref<any[]>([])
const channels = ref<any[]>([])
const types = ref<any[]>([])
const categories = ref<any[]>([])
const tags = ref<any[]>([])
const catalogItems = ref<any[]>([])

const filters = ref({
  q: '',
  status: null as string | null,
  priority_id: null as number | null,
  team_id: null as number | null,
  channel_id: null as number | null,
  ticket_type_id: null as number | null,
  ticket_category_id: null as number | null,
  service_item_id: null as number | null,
  tag_id: null as number | null,
  confidentiality: null as string | null,
  sla: null as string | null,
  created_from: '',
  created_to: '',
  unassigned: false,
  is_major_incident: false,
})

const items = computed(() => listItems(tickets.value).length ? listItems(tickets.value) : tickets.value)

const statusItems = computed(() => [
  { value: null, title: 'Tous les statuts' },
  ...ticketKanbanColumnOrder.concat(['CLOTURE', 'ANNULE']).map(s => ({
    value: s,
    title: ticketStatusLabel(s),
  })),
])

const priorityItems = computed(() => [
  { value: null, title: 'Toutes priorités' },
  ...priorities.value.map(p => ({ value: p.id, title: p.name })),
])

const teamItems = computed(() => [
  { value: null, title: 'Toutes équipes' },
  ...teams.value.map(t => ({ value: t.id, title: t.name })),
])

const channelItems = computed(() => [
  { value: null, title: 'Tous canaux' },
  ...channels.value.map(c => ({ value: c.id, title: c.name })),
])

const typeItems = computed(() => [
  { value: null, title: 'Tous types' },
  ...types.value.map(t => ({ value: t.id, title: t.name })),
])

const categoryItems = computed(() => {
  const flat: any[] = [{ value: null, title: 'Toutes catégories' }]
  for (const parent of categories.value) {
    flat.push({ value: parent.id, title: parent.name })
    for (const child of parent.children || [])
      flat.push({ value: child.id, title: `${parent.name} / ${child.name}` })
  }
  return flat
})

const serviceItems = computed(() => [
  { value: null, title: 'Tous services' },
  ...catalogItems.value.map(i => ({ value: i.id, title: i.name })),
])

const tagItems = computed(() => [
  { value: null, title: 'Tous tags' },
  ...tags.value.map(t => ({ value: t.id, title: t.name })),
])

const slaItems = [
  { value: null, title: 'Tous SLA' },
  { value: 'ok', title: 'OK' },
  { value: 'warning', title: 'Alerte' },
  { value: 'breach', title: 'Dépassé' },
]

const confidentialityItems = [
  { value: null, title: 'Toute confidentialité' },
  { value: 'NORMAL', title: 'Normal' },
  { value: 'RESTREINT', title: 'Restreint' },
  { value: 'CONFIDENTIEL', title: 'Confidentiel' },
]

onMounted(async () => {
  try {
    const meta = await fetchMeta()
    teams.value = meta?.teams || []
    priorities.value = meta?.priorities || []
    channels.value = meta?.channels || []
    types.value = meta?.types || []
    categories.value = meta?.categories || []
    tags.value = meta?.tags || []
    catalogItems.value = (meta?.catalog || []).flatMap((c: any) => c.items || [])
  }
  catch { /* ignore */ }
})

async function search() {
  await searchTickets({
    q: filters.value.q || undefined,
    status: filters.value.status || undefined,
    priority_id: filters.value.priority_id || undefined,
    team_id: filters.value.team_id || undefined,
    channel_id: filters.value.channel_id || undefined,
    ticket_type_id: filters.value.ticket_type_id || undefined,
    ticket_category_id: filters.value.ticket_category_id || undefined,
    service_item_id: filters.value.service_item_id || undefined,
    tag_id: filters.value.tag_id || undefined,
    confidentiality: filters.value.confidentiality || undefined,
    sla: filters.value.sla || undefined,
    created_from: filters.value.created_from || undefined,
    created_to: filters.value.created_to || undefined,
    unassigned: filters.value.unassigned ? 1 : undefined,
    is_major_incident: filters.value.is_major_incident ? 1 : undefined,
    per_page: 50,
  })
}

function reset() {
  filters.value = {
    q: '',
    status: null,
    priority_id: null,
    team_id: null,
    channel_id: null,
    ticket_type_id: null,
    ticket_category_id: null,
    service_item_id: null,
    tag_id: null,
    confidentiality: null,
    sla: null,
    created_from: '',
    created_to: '',
    unassigned: false,
    is_major_incident: false,
  }
  tickets.value = []
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche avancée"
      subtitle="Filtrer les tickets selon plusieurs critères"
    />

    <VCard class="mb-4">
      <VCardText>
        <VRow dense>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche"
              placeholder="N°, titre, description…"
              prepend-inner-icon="tabler-search"
              hide-details
              @keyup.enter="search"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.status"
              :items="statusItems"
              label="Statut"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.priority_id"
              :items="priorityItems"
              label="Priorité"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.team_id"
              :items="teamItems"
              label="Équipe"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.sla"
              :items="slaItems"
              label="SLA"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.ticket_type_id"
              :items="typeItems"
              label="Type"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.ticket_category_id"
              :items="categoryItems"
              label="Catégorie"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.service_item_id"
              :items="serviceItems"
              label="Service"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.channel_id"
              :items="channelItems"
              label="Canal"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.tag_id"
              :items="tagItems"
              label="Tag"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.confidentiality"
              :items="confidentialityItems"
              label="Confidentialité"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="filters.created_from"
              type="date"
              label="Créé du"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="filters.created_to"
              type="date"
              label="Créé au"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
            class="d-flex align-center"
          >
            <VCheckbox
              v-model="filters.unassigned"
              label="Non affectés"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
            class="d-flex align-center"
          >
            <VCheckbox
              v-model="filters.is_major_incident"
              label="Incidents majeurs"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
            class="d-flex justify-end gap-2"
          >
            <VBtn
              variant="text"
              @click="reset"
            >
              Effacer
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-search"
              :loading="loading"
              @click="search"
            >
              Rechercher
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable
        :headers="[
          { title: 'N°', key: 'number' },
          { title: 'Titre', key: 'title' },
          { title: 'Statut', key: 'status' },
          { title: 'Priorité', key: 'priority' },
          { title: 'SLA', key: 'sla' },
          { title: 'Créé le', key: 'created_at' },
        ]"
        :items="items"
        :loading="loading"
        item-value="id"
        hover
        @click:row="(_: any, { item }: any) => router.push({ name: 'ticketing-id', params: { id: item.id } })"
      >
        <template #item.number="{ item }">
          <RouterLink
            class="text-primary font-weight-medium"
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
            Lancez une recherche pour afficher des résultats.
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
