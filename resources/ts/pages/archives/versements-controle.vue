<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { transferStatusColor, transferStatusLabels, useArchives } from '@/composables/useArchives'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: { layout: 'default', action: 'validate', subject: 'Archives' },
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

const headers = [
  { title: 'Référence', key: 'reference' },
  { title: 'Intitulé', key: 'title' },
  { title: 'Statut', key: 'status' },
  { title: 'Soumis le', key: 'submitted_at' },
  { title: '', key: 'actions', sortable: false },
]

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

function primaryAction(item: any) {
  if (item.status === 'soumis')
    return { label: 'Contrôler', run: () => control(item.id) }
  if (item.status === 'en_controle' || item.status === 'accepte')
    return { label: 'Accepter', run: () => accept(item.id) }
  return null
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
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        density="comfortable"
      >
        <template #item.title="{ item }">
          {{ item.title || '—' }}
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="transferStatusColor(item.status)"
            label
          >
            {{ transferStatusLabels[item.status] || item.status }}
          </VChip>
        </template>
        <template #item.submitted_at="{ item }">
          {{ formatDateFr(item.submitted_at, true) }}
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex gap-2 justify-end">
            <VBtn
              v-if="primaryAction(item)"
              size="small"
              color="primary"
              @click="primaryAction(item)!.run()"
            >
              {{ primaryAction(item)!.label }}
            </VBtn>
            <VBtn
              v-if="item.status === 'soumis' || item.status === 'en_controle'"
              size="small"
              color="error"
              variant="tonal"
              @click="openReject(item.id)"
            >
              Rejeter
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              @click="router.push({ name: 'archives-versements-id', params: { id: item.id } })"
            >
              Ouvrir
            </VBtn>
          </div>
        </template>
        <template #no-data>
          <div class="text-medium-emphasis pa-4">
            Aucun versement en attente.
          </div>
        </template>
      </VDataTable>
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
