<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { formatDateFr } from '@/utils/parapheurUi'

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
const errorMessage = ref('')

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

const actionLabel = (value: string) =>
  actionItems.find(i => i.value === value)?.title || value

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
  errorMessage.value = ''
  if (!form.value.delegate_id || !form.value.starts_on || !form.value.ends_on) {
    errorMessage.value = 'Bénéficiaire et période sont obligatoires.'

    return
  }

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
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Impossible de créer la délégation'
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
    <ParapheurPageHeader
      title="Délégations"
      subtitle="Transférez temporairement vos pouvoirs de visa et de validation"
      icon="tabler-user-share"
    />

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard class="mb-6 parapheur-section-card">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-plus"
            size="22"
          />
          Nouvelle délégation
        </VCardTitle>
        <VCardSubtitle>
          Le délégataire agit en votre nom pendant la période définie
        </VCardSubtitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <UserAutocomplete
              v-model="form.delegate_id"
              :items="users"
              label="Bénéficiaire *"
              placeholder="Rechercher un agent…"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="form.starts_on"
              type="date"
              label="Début *"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="form.ends_on"
              type="date"
              label="Fin *"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.reason"
              label="Motif"
              placeholder="Congé, mission, absence…"
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
              closable-chips
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
              closable-chips
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn
          color="primary"
          prepend-icon="tabler-check"
          :loading="busy"
          @click="createDelegation"
        >
          Créer la délégation
        </VBtn>
      </VCardActions>
    </VCard>

    <VCard class="parapheur-section-card">
      <VCardItem>
        <VCardTitle>Mes délégations</VCardTitle>
        <template #append>
          <VChip
            size="small"
            label
            color="primary"
            variant="tonal"
          >
            {{ delegations.length }}
          </VChip>
        </template>
      </VCardItem>
      <VDivider />
      <VDataTable
        :items="delegations"
        hover
        :headers="[
          { title: 'De', key: 'delegator' },
          { title: 'Vers', key: 'delegate' },
          { title: 'Début', key: 'starts_on' },
          { title: 'Fin', key: 'ends_on' },
          { title: 'Pouvoirs', key: 'allowed_actions' },
          { title: 'Statut', key: 'is_active' },
          { title: '', key: 'ops', sortable: false },
        ]"
      >
        <template #item.delegator="{ item }">
          {{ item.delegator?.name }}
        </template>
        <template #item.delegate="{ item }">
          {{ item.delegate?.name }}
        </template>
        <template #item.starts_on="{ item }">
          {{ formatDateFr(item.starts_on) }}
        </template>
        <template #item.ends_on="{ item }">
          {{ formatDateFr(item.ends_on) }}
        </template>
        <template #item.allowed_actions="{ item }">
          <div class="d-flex flex-wrap gap-1 py-1">
            <VChip
              v-for="a in (item.allowed_actions || ['act', 'vise', 'validate'])"
              :key="a"
              size="x-small"
              label
              variant="tonal"
            >
              {{ actionLabel(a) }}
            </VChip>
          </div>
        </template>
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            label
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </VChip>
        </template>
        <template #item.ops="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            :color="item.is_active ? 'warning' : 'success'"
            @click="toggleActive(item)"
          >
            {{ item.is_active ? 'Désactiver' : 'Réactiver' }}
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
