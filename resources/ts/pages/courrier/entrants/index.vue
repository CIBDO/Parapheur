<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CorrespondenceCreateDialog from '@/components/courrier/CorrespondenceCreateDialog.vue'
import CorrespondenceListActions from '@/components/courrier/CorrespondenceListActions.vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { listItems } from '@/utils/listItems'
import { $api } from '@/utils/api'
import { applyMailPriorities, correspondencePriorityColors, correspondencePriorityLabels, correspondenceStatusColors, formatCorrespondenceNumber, formatCourrierDateTime, isCorrespondenceAssigned, partiesByRole, partyDisplayName, statusLabel } from '@/utils/courrierUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Courrier',
  },
})

const router = useRouter()
const route = useRoute()
const { correspondences, fetchCorrespondences, loading } = useCorrespondence()
const search = ref('')
const showCreate = ref(false)

const items = computed(() => listItems(correspondences.value))

async function load() {
  await fetchCorrespondences({
    direction: 'entrant',
    search: search.value || undefined,
  })
}

function onCreated(item: { id: number }) {
  router.push({ name: 'courrier-entrants-id', params: { id: item.id } })
}

function senderLabel(item: any) {
  const from = partiesByRole(item.parties, 'from')[0]
  if (from)
    return partyDisplayName(from) || from.organization || '—'

  return item.structure?.name || '—'
}

function rowProps({ item }: { item: any }) {
  const row = item?.raw ?? item

  return {
    class: isCorrespondenceAssigned(row) ? 'courrier-row--assigned' : 'courrier-row--unassigned',
  }
}

onMounted(() => {
  $api('/mail/meta/priorities')
    .then(items => applyMailPriorities(Array.isArray(items) ? items : []))
    .catch(() => undefined)
  load()
  if (route.query.nouveau)
    showCreate.value = true
})

const headers = [
  { title: 'N° arrivée', key: 'arrival_number' },
  { title: 'Objet', key: 'subject' },
  { title: 'Expéditeur', key: 'sender' },
  { title: 'Priorité', key: 'priority' },
  { title: 'Statut', key: 'status' },
  { title: 'Affectation', key: 'assignment', sortable: false },
  { title: 'Reçu le', key: 'received_at' },
  { title: '', key: 'actions', sortable: false, width: '110px' },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Courriers arrivés"
      subtitle="Registre d'arrivée"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showCreate = true"
        >
          Nouveau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText class="d-flex flex-wrap gap-3">
        <AppTextField
          v-model="search"
          label="Recherche"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 280px"
          @keyup.enter="load"
        />
        <VBtn
          color="primary"
          variant="tonal"
          @click="load"
        >
          Filtrer
        </VBtn>
      </VCardText>
      <VDataTable
        class="courrier-list"
        :headers="headers"
        :items="items"
        :loading="loading"
        :row-props="rowProps"
        item-value="id"
        hover
      >
        <template #item.arrival_number="{ item }">
          <RouterLink :to="{ name: 'courrier-entrants-id', params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.sender="{ item }">
          {{ senderLabel(item) }}
        </template>
        <template #item.priority="{ item }">
          <VChip
            size="small"
            :color="correspondencePriorityColors[item.priority] || 'default'"
            variant="tonal"
          >
            {{ correspondencePriorityLabels[item.priority] || item.priority || '—' }}
          </VChip>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.assignment="{ item }">
          <VChip
            size="small"
            :color="isCorrespondenceAssigned(item) ? 'success' : 'warning'"
            :prepend-icon="isCorrespondenceAssigned(item) ? 'tabler-user-check' : 'tabler-user-question'"
            variant="flat"
          >
            {{ isCorrespondenceAssigned(item) ? 'Affecté' : 'Non affecté' }}
          </VChip>
        </template>
        <template #item.received_at="{ item }">
          {{ formatCourrierDateTime(item.received_at) }}
        </template>
        <template #item.actions="{ item }">
          <CorrespondenceListActions
            :item="item"
            @imputed="load"
          />
        </template>
      </VDataTable>
    </VCard>

    <CorrespondenceCreateDialog
      v-model="showCreate"
      direction="entrant"
      @created="onCreated"
    />
  </div>
</template>

<style scoped>
.courrier-list :deep(tr.courrier-row--assigned) {
  background: rgba(var(--v-theme-success), 0.1);
  box-shadow: inset 4px 0 0 rgb(var(--v-theme-success));
}

.courrier-list :deep(tr.courrier-row--unassigned) {
  background: rgba(var(--v-theme-warning), 0.14);
  box-shadow: inset 4px 0 0 rgb(var(--v-theme-warning));
}
</style>
