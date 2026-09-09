<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'DashboardDg',
  },
});

const users = ref<any[]>([]);
const delegations = ref<any[]>([]);
const form = ref({
  delegate_id: null as number | null,
  starts_on: '',
  ends_on: '',
  reason: '',
});

const load = async () => {
  const [people, list] = await Promise.all([$api('/meta/users'), $api('/delegations', { query: { mine: 1 } })]);
  users.value = people;
  delegations.value = list.data ?? list;
};

const createDelegation = async () => {
  await $api('/delegations', { method: 'POST', body: form.value });
  form.value = { delegate_id: null, starts_on: '', ends_on: '', reason: '' };
  await load();
};

onMounted(load);
</script>

<template>
  <div>
    <h4 class="text-h4 mb-4">Délégations</h4>

    <VCard class="mb-6">
      <VCardTitle>Nouvelle délégation</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <AppSelect v-model="form.delegate_id" :items="users" item-title="name" item-value="id" label="Bénéficiaire" />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField v-model="form.starts_on" type="date" label="Début" />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField v-model="form.ends_on" type="date" label="Fin" />
          </VCol>
          <VCol cols="12" md="2" class="d-flex align-end">
            <VBtn color="primary" block @click="createDelegation"> Créer </VBtn>
          </VCol>
          <VCol cols="12">
            <AppTextField v-model="form.reason" label="Motif" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable
        :items="delegations"
        :headers="[
          { title: 'De', key: 'delegator' },
          { title: 'Vers', key: 'delegate' },
          { title: 'Début', key: 'starts_on' },
          { title: 'Fin', key: 'ends_on' },
          { title: 'Active', key: 'is_active' },
        ]"
      >
        <template #item.delegator="{ item }">
          {{ item.delegator?.name }}
        </template>
        <template #item.delegate="{ item }">
          {{ item.delegate?.name }}
        </template>
        <template #item.is_active="{ item }">
          <VChip size="small" :color="item.is_active ? 'success' : 'secondary'">
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
