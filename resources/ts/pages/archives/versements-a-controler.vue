<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'

definePage({
  name: 'archives-versements-a-controler',
  meta: { action: 'validate', subject: 'Archives' },
})

const router = useRouter()
const {
  loading,
  fetchTransfers,
  controlTransfer,
  acceptTransfer,
  rejectTransfer,
  takeOverTransfer,
} = useArchives()

const items = ref<any[]>([])
const rejectDialog = ref(false)
const rejectReason = ref('')
const selectedId = ref<number | null>(null)

async function load() {
  const data = await fetchTransfers({ to_control: 1, per_page: 50 })
  items.value = data.data || []
}

async function control(id: number) {
  await controlTransfer(id)
  await load()
}

async function accept(id: number) {
  await acceptTransfer(id)
  await takeOverTransfer(id)
  await load()
}

function openReject(id: number) {
  selectedId.value = id
  rejectReason.value = ''
  rejectDialog.value = true
}

async function confirmReject() {
  if (!selectedId.value || !rejectReason.value.trim())
    return
  await rejectTransfer(selectedId.value, rejectReason.value.trim())
  rejectDialog.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Versements à contrôler"
      subtitle="File de contrôle archivistique"
      icon="tabler-file-check"
    />
    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="item in items"
          :key="item.id"
          :title="item.reference"
          :subtitle="item.title || 'Sans intitulé'"
        >
          <template #append>
            <div class="d-flex align-center gap-2">
              <VChip
                size="small"
                :color="transferStatusColor(item.status)"
                label
              >
                {{ transferStatusLabels[item.status] || item.status }}
              </VChip>
              <VBtn
                size="small"
                variant="tonal"
                @click="router.push({ name: 'archives-versements-id', params: { id: item.id } })"
              >
                Voir
              </VBtn>
              <VBtn
                v-if="item.status === 'soumis'"
                size="small"
                @click="control(item.id)"
              >
                Contrôler
              </VBtn>
              <VBtn
                v-if="item.status === 'soumis' || item.status === 'en_controle'"
                size="small"
                color="success"
                @click="accept(item.id)"
              >
                Accepter
              </VBtn>
              <VBtn
                v-if="item.status === 'soumis' || item.status === 'en_controle'"
                size="small"
                color="error"
                @click="openReject(item.id)"
              >
                Rejeter
              </VBtn>
            </div>
          </template>
        </VListItem>
        <VListItem
          v-if="!items.length"
          title="Aucun versement en attente"
        />
      </VList>
    </VCard>

    <VDialog
      v-model="rejectDialog"
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
            @click="rejectDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            @click="confirmReject"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
