<script setup lang="ts">
definePage({
  meta: {
    action: 'manage',
    subject: 'Instruction',
  },
});

const instructions = ref<any[]>([]);
const loading = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const res = await $api('/instructions');
    instructions.value = res.data ?? res;
  } finally {
    loading.value = false;
  }
};

const setStatus = async (id: number, status: string) => {
  await $api(`/instructions/${id}/status`, {
    method: 'PATCH',
    body: { status },
  });
  await load();
};

onMounted(load);
</script>

<template>
  <VCard>
    <VCardTitle>Suivi des instructions</VCardTitle>
    <VDataTable
      :items="instructions"
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
        {{ item.document?.reference || '—' }}
      </template>
      <template #item.actions="{ item }">
        <VBtn size="x-small" class="me-1" @click="setStatus(item.id, 'en_cours')"> En cours </VBtn>
        <VBtn size="x-small" color="success" @click="setStatus(item.id, 'executee')"> Exécutée </VBtn>
      </template>
    </VDataTable>
  </VCard>
</template>
