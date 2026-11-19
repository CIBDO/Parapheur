<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'ArchivesLoans' } })

const { loading, fetchLoans, fetchBoxes, createLoan, validateLoan, checkoutLoan, returnLoan } = useArchives()
const items = ref<any[]>([])
const boxes = ref<any[]>([])
const dialog = ref(false)
const form = ref({ box_id: null as number | null, purpose: '', loan_days: 7 })

async function load() {
  const [loans, boxPage] = await Promise.all([fetchLoans({ per_page: 50 }), fetchBoxes({ per_page: 100 })])
  items.value = loans.data || []
  boxes.value = boxPage.data || []
}

async function create() {
  await createLoan({ ...form.value })
  dialog.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Prêts et communications"
      subtitle="Workflow de sortie temporaire"
      icon="tabler-hand-grab"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Demander un prêt
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card">
      <VList :loading="loading">
        <VListItem
          v-for="loan in items"
          :key="loan.id"
          :title="`${loan.reference} · ${loan.box?.code || '—'}`"
          :subtitle="`${loan.status} · ${loan.purpose || 'Sans motif'} · échéance ${loan.due_at || '—'}`"
        >
          <template #append>
            <div class="d-flex gap-1">
              <VBtn
                v-if="loan.status === 'demande'"
                size="small"
                @click="validateLoan(loan.id).then(load)"
              >
                Valider
              </VBtn>
              <VBtn
                v-if="loan.status === 'valide' || loan.status === 'prepare'"
                size="small"
                color="warning"
                @click="checkoutLoan(loan.id).then(load)"
              >
                Sortir
              </VBtn>
              <VBtn
                v-if="loan.status === 'sorti' || loan.status === 'en_retard'"
                size="small"
                color="success"
                @click="returnLoan(loan.id).then(load)"
              >
                Retour
              </VBtn>
            </div>
          </template>
        </VListItem>
        <VListItem
          v-if="!items.length"
          title="Aucun prêt"
        />
      </VList>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Demande de prêt</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VSelect
            v-model="form.box_id"
            :items="boxes.map(b => ({ title: b.code, value: b.id }))"
            label="Boîte"
          />
          <VTextField
            v-model="form.purpose"
            label="Motif"
          />
          <VTextField
            v-model.number="form.loan_days"
            label="Durée (jours)"
            type="number"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="create"
          >
            Demander
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
