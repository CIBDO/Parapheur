<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr, transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const route = useRoute()
const router = useRouter()
const { loading, getJson, submitTransfer, controlTransfer, acceptTransfer, rejectTransfer, takeOverTransfer } = useArchives()
const transfer = ref<any | null>(null)
const rejectReason = ref('')
const showReject = ref(false)

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
  showReject.value = false
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="transfer?.reference || 'Versement'"
      :subtitle="transfer?.title || 'Bordereau de versement'"
      icon="tabler-file-description"
    >
      <template #back>
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          @click="router.push({ name: 'archives-mes-versements' })"
        />
      </template>
      <template
        v-if="transfer"
        #actions
      >
        <VChip
          :color="transferStatusColor(transfer.status)"
          label
        >
          {{ transferStatusLabels[transfer.status] || transfer.status }}
        </VChip>
        <VBtn
          v-if="transfer.status === 'brouillon' || transfer.status === 'rejete'"
          color="primary"
          :loading="loading"
          @click="submit"
        >
          Soumettre
        </VBtn>
        <VBtn
          v-if="transfer.status === 'soumis'"
          color="primary"
          :loading="loading"
          @click="control"
        >
          Mettre en contrôle
        </VBtn>
        <VBtn
          v-if="transfer.status === 'soumis' || transfer.status === 'en_controle'"
          color="primary"
          :loading="loading"
          @click="accept"
        >
          Accepter et conserver
        </VBtn>
        <VBtn
          v-if="transfer.status === 'soumis' || transfer.status === 'en_controle'"
          color="error"
          variant="tonal"
          @click="showReject = true"
        >
          Rejeter
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard
      class="parapheur-section-card mb-4"
      :loading="loading"
    >
      <VCardText v-if="transfer">
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <div class="text-caption text-medium-emphasis">
              Structure
            </div>
            <div>{{ transfer.structure?.name || transfer.structure?.code || '—' }}</div>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <div class="text-caption text-medium-emphasis">
              Soumis le
            </div>
            <div>{{ formatDateFr(transfer.submitted_at, true) }}</div>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <div class="text-caption text-medium-emphasis">
              Conservé le
            </div>
            <div>{{ formatDateFr(transfer.conserved_at, true) }}</div>
          </VCol>
        </VRow>
        <div
          v-if="transfer.rejection_reason"
          class="mt-4 text-error"
        >
          Rejet : {{ transfer.rejection_reason }}
        </div>
        <div
          v-if="transfer.notes"
          class="mt-4 text-medium-emphasis"
        >
          {{ transfer.notes }}
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
      <VList v-if="transfer.items?.length">
        <VListItem
          v-for="item in transfer.items"
          :key="item.id"
          :title="item.title || item.document?.title || item.document?.object || 'Document'"
          :subtitle="item.document?.reference || (item.checksum ? `Empreinte ${item.checksum.slice(0, 12)}…` : undefined)"
        />
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        Aucune pièce.
      </VCardText>
    </VCard>

    <VDialog
      v-model="showReject"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Motif de rejet</VCardTitle>
        <VCardText>
          <VTextarea
            v-model="rejectReason"
            label="Motif"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showReject = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            @click="reject"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
