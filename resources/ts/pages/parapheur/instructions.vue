<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'Instruction',
  },
})

const instructionStatusLabels: Record<string, string> = {
  ouverte: 'Ouverte',
  en_cours: 'En cours',
  executee: 'Exécutée',
  cloturee: 'Clôturée',
}

const instructions = ref<any[]>([])
const loading = ref(false)
const filterLate = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/instructions')
    instructions.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

const setStatus = async (id: number, status: string) => {
  await $api(`/instructions/${id}/status`, {
    method: 'PATCH',
    body: { status },
  })
  await load()
}

const isLate = (item: any) => {
  if (!item.due_date || ['executee', 'cloturee'].includes(item.status))
    return false

  return new Date(item.due_date) < new Date(new Date().toDateString())
}

const visible = computed(() => {
  if (!filterLate.value)
    return instructions.value

  return instructions.value.filter(isLate)
})

const lateCount = computed(() => instructions.value.filter(isLate).length)

const statusColor = (status: string) => {
  if (status === 'executee' || status === 'cloturee')
    return 'success'
  if (status === 'en_cours')
    return 'info'

  return 'warning'
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Suivi des instructions"
      :subtitle="`${lateCount} en retard — relances automatiques quotidiennes`"
      icon="tabler-list-check"
    >
      <template #actions>
        <VSwitch
          v-model="filterLate"
          label="Retards uniquement"
          color="error"
          hide-details
          inset
        />
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow class="mb-6">
      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
            >
              <VIcon icon="tabler-list" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total
              </div>
              <div class="text-h5">
                {{ instructions.length }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="error"
              variant="tonal"
              rounded
            >
              <VIcon icon="tabler-alert-circle" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                En retard
              </div>
              <div class="text-h5">
                {{ lateCount }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="success"
              variant="tonal"
              rounded
            >
              <VIcon icon="tabler-circle-check" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Affichées
              </div>
              <div class="text-h5">
                {{ visible.length }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="parapheur-section-card">
      <div
        v-if="!loading && !visible.length"
        class="parapheur-empty"
      >
        <VIcon
          icon="tabler-clipboard-off"
          size="40"
          class="mb-3"
        />
        <div class="text-h6">
          Aucune instruction
        </div>
      </div>
      <VDataTable
        v-else
        :items="visible"
        :loading="loading"
        hover
        :headers="[
          { title: 'Titre', key: 'title' },
          { title: 'Responsable', key: 'assignee' },
          { title: 'Document', key: 'document' },
          { title: 'Échéance', key: 'due_date' },
          { title: 'Statut', key: 'status' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
      >
        <template #item.assignee="{ item }">
          {{ item.assignee?.name || '—' }}
        </template>
        <template #item.document="{ item }">
          <RouterLink
            v-if="item.document_id"
            class="text-primary font-weight-medium"
            :to="{ name: 'parapheur-id', params: { id: item.document_id } }"
          >
            {{ item.document?.reference || `#${item.document_id}` }}
          </RouterLink>
          <span v-else>—</span>
        </template>
        <template #item.due_date="{ item }">
          <VChip
            v-if="isLate(item)"
            size="small"
            color="error"
            label
          >
            {{ formatDateFr(item.due_date) }} · retard
          </VChip>
          <span v-else>{{ formatDateFr(item.due_date) }}</span>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            label
            variant="tonal"
            :color="statusColor(item.status)"
          >
            {{ instructionStatusLabels[item.status] || item.status }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex flex-wrap gap-1">
            <VBtn
              size="small"
              variant="tonal"
              @click="setStatus(item.id, 'en_cours')"
            >
              En cours
            </VBtn>
            <VBtn
              size="small"
              color="success"
              variant="tonal"
              @click="setStatus(item.id, 'executee')"
            >
              Exécutée
            </VBtn>
            <VBtn
              size="small"
              variant="text"
              @click="setStatus(item.id, 'cloturee')"
            >
              Clôturer
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
