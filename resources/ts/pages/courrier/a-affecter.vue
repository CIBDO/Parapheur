<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { $api } from '@/utils/api'
import {
  correspondenceStatusColors,
  formatCorrespondenceNumber,
  listItems,
  statusLabel,
} from '@/utils/courrierUi'

definePage({
  name: 'courrier-a-affecter',
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const { correspondences, fetchCorrespondences, loading, assign } = useCorrespondence()
const dialog = ref(false)
const selected = ref<any>(null)
const users = ref<any[]>([])
const assigning = ref(false)
const form = ref({
  to_user_id: null as number | null,
  instruction_text: '',
  due_date: '',
})

const items = computed(() => listItems(correspondences.value))

async function load() {
  await fetchCorrespondences({ unassigned: 1, per_page: 50 })
}

onMounted(async () => {
  await load()
  try {
    users.value = await $api('/meta/users')
  }
  catch {
    users.value = []
  }
})

function openAssign(item: any) {
  selected.value = item
  form.value = { to_user_id: null, instruction_text: '', due_date: '' }
  dialog.value = true
}

async function confirmAssign() {
  if (!selected.value || !form.value.to_user_id)
    return
  assigning.value = true
  try {
    await assign(selected.value.id, [{
      to_user_id: form.value.to_user_id,
      instruction_text: form.value.instruction_text || undefined,
      due_date: form.value.due_date || undefined,
    }])
    dialog.value = false
    await load()
  }
  finally {
    assigning.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="À affecter"
      subtitle="Courriers sans imputation"
    />

    <VCard>
      <VDataTable
        :headers="[
          { title: 'N°', key: 'arrival_number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Statut', key: 'status' },
          { title: 'Reçu le', key: 'received_at' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.arrival_number="{ item }">
          <RouterLink :to="{ name: 'courrier-entrants-id', params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
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
        <template #item.received_at="{ item }">
          {{ item.received_at ? new Date(item.received_at).toLocaleString('fr-FR') : '—' }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-plus"
            @click="openAssign(item)"
          >
            Affecter
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard :title="`Affecter — ${selected ? formatCorrespondenceNumber(selected) : ''}`">
        <VCardText>
          <AppSelect
            v-model="form.to_user_id"
            class="mb-3"
            :items="users"
            item-title="name"
            item-value="id"
            label="Agent destinataire *"
          />
          <AppTextField
            v-model="form.due_date"
            class="mb-3"
            type="date"
            label="Échéance"
          />
          <AppTextarea
            v-model="form.instruction_text"
            label="Instruction"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="assigning"
            :disabled="!form.to_user_id"
            @click="confirmAssign"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
