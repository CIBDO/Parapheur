<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const route = useRoute()
const { loading, getJson, submitTransfer, controlTransfer, acceptTransfer, rejectTransfer, takeOverTransfer } = useArchives()
const transfer = ref<any | null>(null)
const rejectReason = ref('')

async function load() {
  transfer.value = await getJson(`/archives/transfers/${route.params.id}`)
}

async function submit() {
  transfer.value = await submitTransfer(Number(route.params.id))
}

async function control() {
  transfer.value = await controlTransfer(Number(route.params.id))
}

async function accept() {
  await acceptTransfer(Number(route.params.id))
  transfer.value = await takeOverTransfer(Number(route.params.id))
}

async function reject() {
  if (!rejectReason.value.trim())
    return
  transfer.value = await rejectTransfer(Number(route.params.id), rejectReason.value.trim())
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="transfer?.reference || 'Versement'"
      :subtitle="transfer?.title || 'Détail du bordereau'"
      icon="tabler-file-description"
    />

    <VCard
      class="parapheur-section-card mb-4"
      :loading="loading"
    >
      <VCardText v-if="transfer">
        <div class="d-flex align-center gap-3 mb-4">
          <VChip
            :color="transferStatusColor(transfer.status)"
            label
          >
            {{ transferStatusLabels[transfer.status] || transfer.status }}
          </VChip>
          <span class="text-medium-emphasis">Structure {{ transfer.structure?.code }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <VBtn
            v-if="transfer.status === 'brouillon' || transfer.status === 'rejete'"
            color="primary"
            @click="submit"
          >
            Soumettre
          </VBtn>
          <VBtn
            v-if="transfer.status === 'soumis'"
            @click="control"
          >
            Mettre en contrôle
          </VBtn>
          <VBtn
            v-if="transfer.status === 'soumis' || transfer.status === 'en_controle'"
            color="success"
            @click="accept"
          >
            Accepter et conserver
          </VBtn>
        </div>
        <VTextarea
          v-if="transfer.status === 'soumis' || transfer.status === 'en_controle'"
          v-model="rejectReason"
          label="Motif de rejet"
          rows="2"
          class="mb-2"
        />
        <VBtn
          v-if="transfer.status === 'soumis' || transfer.status === 'en_controle'"
          color="error"
          variant="tonal"
          @click="reject"
        >
          Rejeter
        </VBtn>
        <div
          v-if="transfer.rejection_reason"
          class="mt-4 text-error"
        >
          Rejet : {{ transfer.rejection_reason }}
        </div>
      </VCardText>
    </VCard>

    <VCard
      v-if="transfer"
      class="parapheur-section-card"
    >
      <VCardItem>
        <VCardTitle>Pièces ({{ transfer.items?.length || 0 }})</VCardTitle>
      </VCardItem>
      <VList>
        <VListItem
          v-for="item in transfer.items || []"
          :key="item.id"
          :title="item.title || `Document #${item.document_id}`"
          :subtitle="item.checksum ? `SHA-256 ${item.checksum.slice(0, 12)}…` : 'Sans empreinte'"
        />
      </VList>
    </VCard>
  </div>
</template>
