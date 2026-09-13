<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { $api } from '@/utils/api'
import { transmissionSlipStatusLabels } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const route = useRoute()
const id = Number(route.params.id)
const slip = ref<any>(null)
const loading = ref(true)
const busy = ref(false)
const message = ref('')

// Dialogs
const showAckDialog = ref(false)
const showPrintDialog = ref(false)
const ackForm = ref({
  proof_file: null as File | null,
  observations: '',
})
const printForm = ref({
  reason: '',
  is_reprint: false,
})

async function load() {
  loading.value = true
  try {
    slip.value = await $api(`/mail/transmission-slips/${id}`)
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function run(action: string) {
  busy.value = true
  message.value = ''
  try {
    slip.value = await $api(`/mail/transmission-slips/${id}/${action}`, { method: 'POST', body: {} })
    message.value = `Action « ${action} » effectuée`
  }
  catch (e: any) {
    message.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    busy.value = false
  }
}

async function acknowledgeSlip() {
  busy.value = true
  message.value = ''
  try {
    const formData = new FormData()
    if (ackForm.value.proof_file) {
      formData.append('proof', ackForm.value.proof_file)
    }
    formData.append('observations', ackForm.value.observations)

    slip.value = await $api(`/mail/transmission-slips/${id}/acknowledge`, {
      method: 'POST',
      body: formData,
    })
    showAckDialog.value = false
    ackForm.value = { proof_file: null, observations: '' }
    message.value = 'Accusé de réception enregistré'
  }
  catch (e: any) {
    message.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    busy.value = false
  }
}

async function printSlip() {
  busy.value = true
  message.value = ''
  try {
    slip.value = await $api(`/mail/transmission-slips/${id}/print`, {
      method: 'POST',
      body: printForm.value,
    })
    showPrintDialog.value = false
    printForm.value = { reason: '', is_reprint: false }
    message.value = 'Impression enregistrée'
  }
  catch (e: any) {
    message.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    busy.value = false
  }
}

function onProofFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    ackForm.value.proof_file = target.files[0]
  }
}
</script>

<template>
  <div v-if="slip && !loading">
    <ParapheurPageHeader
      :title="slip.number || `Bordereau #${slip.id}`"
      :subtitle="transmissionSlipStatusLabels[slip.status] || slip.status"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :loading="busy"
          @click="run('validate')"
        >
          Valider
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="busy"
          @click="run('generate')"
        >
          Générer DOCX
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="busy"
          @click="showPrintDialog = true"
        >
          Imprimer
        </VBtn>
        <VBtn
          variant="tonal"
          :loading="busy"
          @click="showAckDialog = true"
        >
          Accusé réception
        </VBtn>
        <VBtn
          color="primary"
          :loading="busy"
          @click="run('send')"
        >
          Transmettre
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="message"
      class="mb-4"
      variant="tonal"
      type="info"
    >
      {{ message }}
    </VAlert>

    <VCard class="mb-4">
      <VCardText>
        <p><strong>De :</strong> {{ slip.from_structure?.name || '—' }}</p>
        <p><strong>Vers :</strong> {{ slip.to_structure?.name || '—' }}</p>
        <p><strong>Nature :</strong> {{ slip.nature || '—' }}</p>
        <p><strong>Observations :</strong> {{ slip.observations || '—' }}</p>
        <p><strong>Document :</strong> {{ slip.document_id ? `#${slip.document_id}` : 'Non généré' }}</p>
      </VCardText>
    </VCard>

    <VCard title="Courriers du bordereau">
      <VDataTable
        :headers="[
          { title: 'Référence', key: 'reference' },
          { title: 'Objet', key: 'object' },
          { title: 'Pièces', key: 'piece_count' },
        ]"
        :items="slip.items || []"
      />
    </VCard>

    <!-- Dialog Accusé de réception -->
    <VDialog v-model="showAckDialog" max-width="600">
      <VCard>
        <VCardTitle>Accusé de réception</VCardTitle>
        <VCardText>
          <VFileInput
            label="Fichier de preuve (facultatif)"
            class="mb-4"
            @change="onProofFileChange"
          />
          <VTextarea
            v-model="ackForm.observations"
            label="Observations"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showAckDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" :loading="busy" @click="acknowledgeSlip">
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Impression -->
    <VDialog v-model="showPrintDialog" max-width="600">
      <VCard>
        <VCardTitle>Imprimer le bordereau</VCardTitle>
        <VCardText>
          <VTextField
            v-model="printForm.reason"
            label="Motif d'impression"
            class="mb-4"
          />
          <VCheckbox
            v-model="printForm.is_reprint"
            label="Réimpression"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showPrintDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" :loading="busy" @click="printSlip">
            Imprimer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
  <div
    v-else
    class="text-center py-10"
  >
    <VProgressCircular indeterminate />
  </div>
</template>
