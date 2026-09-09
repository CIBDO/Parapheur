<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'DashboardDg',
  },
})

const users = ref<any[]>([])
const documentTypes = ref<any[]>([])
const delegations = ref<any[]>([])
const busy = ref(false)

const form = ref({
  delegate_id: null as number | null,
  starts_on: '',
  ends_on: '',
  reason: '',
  document_type_ids: [] as number[],
  allowed_actions: ['vise', 'validate', 'act'] as string[],
})

const actionItems = [
  { title: 'Agir (commenter, transmettre…)', value: 'act' },
  { title: 'Viser', value: 'vise' },
  { title: 'Valider', value: 'validate' },
]

const load = async () => {
  const [people, types, list] = await Promise.all([
    $api('/meta/users'),
    $api('/meta/document-types'),
    $api('/delegations', { query: { mine: 1 } }),
  ])
  users.value = people
  documentTypes.value = types
  delegations.value = list.data ?? list
}

const createDelegation = async () => {
  busy.value = true
  try {
    await $api('/delegations', {
      method: 'POST',
      body: {
        ...form.value,
        document_type_ids: form.value.document_type_ids.length ? form.value.document_type_ids : null,
        allowed_actions: form.value.allowed_actions.length ? form.value.allowed_actions : null,
      },
    })
    form.value = {
      delegate_id: null,
      starts_on: '',
      ends_on: '',
      reason: '',
      document_type_ids: [],
      allowed_actions: ['vise', 'validate', 'act'],
    }
    await load()
  }
  finally {
    busy.value = false
  }
}

const toggleActive = async (item: any) => {
  await $api(`/delegations/${item.id}`, {
    method: 'PATCH',
    body: { is_active: !item.is_active },
  })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <h4 class="text-h4 mb-4">
      Délégations
    </h4>

    <VCard class="mb-6">
      <VCardTitle>Nouvelle délégation</VCardTitle>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.delegate_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Bénéficiaire"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="form.starts_on"
              type="date"
              label="Début"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppTextField
              v-model="form.ends_on"
              type="date"
              label="Fin"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-end"
          >
            <VBtn
              color="primary"
              block
              :loading="busy"
              @click="createDelegation"
            >
              Créer
            </VBtn>
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.reason"
              label="Motif"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.document_type_ids"
              :items="documentTypes"
              item-title="name"
              item-value="id"
              label="Types de documents (vide = tous)"
              multiple
              chips
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.allowed_actions"
              :items="actionItems"
              label="Actions autorisées"
              multiple
              chips
            />
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
          { title: 'Actions', key: 'allowed_actions' },
          { title: 'Active', key: 'is_active' },
          { title: '', key: 'ops', sortable: false },
        ]"
      >
        <template #item.delegator="{ item }">
          {{ item.delegator?.name }}
        </template>
        <template #item.delegate="{ item }">
          {{ item.delegate?.name }}
        </template>
        <template #item.allowed_actions="{ item }">
          {{ (item.allowed_actions || ['toutes']).join(', ') }}
        </template>
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
        <template #item.ops="{ item }">
          <VBtn
            size="small"
            variant="text"
            @click="toggleActive(item)"
          >
            {{ item.is_active ? 'Désactiver' : 'Réactiver' }}
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
