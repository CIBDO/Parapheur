<script setup lang="ts">
definePage({
  meta: {
    action: 'manage',
    subject: 'Instruction',
  },
})

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

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-4">
      <div>
        <h4 class="text-h4 mb-1">
          Suivi des instructions
        </h4>
        <p class="text-body-1 mb-0">
          {{ lateCount }} en retard — relances automatiques quotidiennes (cron 08:00)
        </p>
      </div>
      <VSwitch
        v-model="filterLate"
        label="Retards uniquement"
        color="error"
        hide-details
      />
    </div>

    <VCard>
      <VDataTable
        :items="visible"
        :loading="loading"
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
          {{ item.assignee?.name }}
        </template>
        <template #item.document="{ item }">
          <RouterLink
            v-if="item.document_id"
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
            {{ item.due_date }} · retard
          </VChip>
          <span v-else>{{ item.due_date || '—' }}</span>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="x-small"
            class="me-1"
            @click="setStatus(item.id, 'en_cours')"
          >
            En cours
          </VBtn>
          <VBtn
            size="x-small"
            color="success"
            class="me-1"
            @click="setStatus(item.id, 'executee')"
          >
            Exécutée
          </VBtn>
          <VBtn
            size="x-small"
            variant="tonal"
            @click="setStatus(item.id, 'cloturee')"
          >
            Clôturer
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
