<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { listItems, formatCorrespondenceNumber } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const router = useRouter()
const sheets = ref<any>(null)
const loading = ref(true)
const busy = ref(false)

onMounted(async () => {
  try {
    sheets.value = await $api('/mail/circulation-sheets')
  }
  finally {
    loading.value = false
  }
})

const items = computed(() => listItems(sheets.value))

async function generateSheet(id: number) {
  busy.value = true
  try {
    await $api(`/mail/circulation-sheets/${id}/generate`, { method: 'POST', body: {} })
    // Reload
    sheets.value = await $api('/mail/circulation-sheets')
  }
  catch (e: any) {
    alert('Erreur : ' + (e?.data?.message || e.message || 'Impossible de générer'))
  }
  finally {
    busy.value = false
  }
}

function viewCorrespondence(correspondenceId: number) {
  // Determine route based on correspondence direction - for now go to entrants
  router.push({ name: 'courrier-entrants-id', params: { id: correspondenceId } })
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Fiches de circulation"
      subtitle="Fiches d'imputation générées"
    />
    <VCard>
      <VDataTable
        :headers="[
          { title: 'N°', key: 'number' },
          { title: 'Courrier', key: 'correspondence' },
          { title: 'Statut', key: 'status' },
          { title: 'Document', key: 'document_id' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.number="{ item }">
          {{ item.number || `Fiche #${item.id}` }}
        </template>
        <template #item.correspondence="{ item }">
          <VBtn
            v-if="item.correspondence_id"
            size="x-small"
            variant="text"
            @click="viewCorrespondence(item.correspondence_id)"
          >
            {{ item.correspondence ? formatCorrespondenceNumber(item.correspondence) : `#${item.correspondence_id}` }}
          </VBtn>
          <span v-else>—</span>
        </template>
        <template #item.document_id="{ item }">
          <VChip
            v-if="item.document_id"
            size="small"
            color="success"
            variant="tonal"
          >
            Doc #{{ item.document_id }}
          </VChip>
          <span v-else class="text-medium-emphasis">Non généré</span>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="!item.document_id"
            size="x-small"
            variant="tonal"
            :loading="busy"
            @click="generateSheet(item.id)"
          >
            Générer
          </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
