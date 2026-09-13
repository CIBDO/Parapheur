<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import {
  detailRouteName,
  formatCorrespondenceNumber,
  formatCourrierDateTime,
  getTransmissionSlipNatureLabel,
  getTransmissionSlipStatusColor,
  getTransmissionSlipStatusLabel,
} from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)
const slip = ref<any>(null)
const loading = ref(true)
const busy = ref(false)
const message = ref('')
const messageType = ref<'success' | 'error' | 'info'>('info')

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

const status = computed(() => String(slip.value?.status || 'brouillon'))

const workflowSteps = computed(() => {
  const order = ['brouillon', 'valide', 'genere', 'imprime', 'transmis', 'recu']
  const current = order.includes(status.value) ? status.value : 'brouillon'
  const idx = order.indexOf(current)

  return [
    { key: 'brouillon', label: 'Brouillon' },
    { key: 'valide', label: 'Validé' },
    { key: 'genere', label: 'Document' },
    { key: 'imprime', label: 'Imprimé' },
    { key: 'transmis', label: 'Transmis' },
    { key: 'recu', label: 'Reçu' },
  ].map((step, i) => ({
    ...step,
    done: i < idx || status.value === 'recu' || status.value === 'cloture',
    active: step.key === current || (current === 'genere' && step.key === 'genere'),
  }))
})

const canValidate = computed(() => ['brouillon', 'en_modification', 'a_valider'].includes(status.value))
const canGenerate = computed(() => !['annule', 'cloture'].includes(status.value))
const canPrint = computed(() => !!slip.value?.document_id || ['valide', 'genere', 'imprime', 'transmis'].includes(status.value))
const canSend = computed(() => ['valide', 'genere', 'imprime'].includes(status.value))
const canAck = computed(() => ['transmis', 'imprime', 'genere', 'valide'].includes(status.value) && status.value !== 'recu')

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

async function run(action: string, successMsg: string) {
  busy.value = true
  message.value = ''
  try {
    slip.value = await $api(`/mail/transmission-slips/${id}/${action}`, { method: 'POST', body: {} })
    messageType.value = 'success'
    message.value = successMsg
  }
  catch (e: any) {
    messageType.value = 'error'
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
    if (ackForm.value.proof_file)
      formData.append('proof', ackForm.value.proof_file)
    formData.append('observations', ackForm.value.observations)

    slip.value = await $api(`/mail/transmission-slips/${id}/acknowledge`, {
      method: 'POST',
      body: formData,
    })
    showAckDialog.value = false
    ackForm.value = { proof_file: null, observations: '' }
    messageType.value = 'success'
    message.value = 'Accusé de réception enregistré'
  }
  catch (e: any) {
    messageType.value = 'error'
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
    messageType.value = 'success'
    message.value = 'Impression enregistrée'
  }
  catch (e: any) {
    messageType.value = 'error'
    message.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    busy.value = false
  }
}

function onProofFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files?.[0])
    ackForm.value.proof_file = target.files[0]
}

function openCorrespondence(item: any) {
  const c = item.correspondence
  if (!c?.id)
    return
  router.push({ name: detailRouteName(c.direction), params: { id: c.id } })
}
</script>

<template>
  <div v-if="slip && !loading">
    <ParapheurPageHeader
      :title="slip.number || `Brouillon #${slip.id}`"
      subtitle="Bordereau de transmission interne"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :to="{ name: 'courrier-bordereaux' }"
          prepend-icon="tabler-arrow-left"
        >
          Retour
        </VBtn>
        <VBtn
          v-if="canValidate"
          variant="tonal"
          color="primary"
          :loading="busy"
          prepend-icon="tabler-check"
          @click="run('validate', 'Bordereau validé — numéro BT attribué si besoin')"
        >
          Valider
        </VBtn>
        <VBtn
          v-if="canGenerate"
          variant="tonal"
          :loading="busy"
          prepend-icon="tabler-file-type-doc"
          @click="run('generate', 'Document DOCX généré')"
        >
          {{ slip.document_id ? 'Régénérer DOCX' : 'Générer DOCX' }}
        </VBtn>
        <VBtn
          v-if="canPrint"
          variant="tonal"
          :loading="busy"
          prepend-icon="tabler-printer"
          @click="showPrintDialog = true"
        >
          Imprimer
        </VBtn>
        <VBtn
          v-if="canSend"
          color="primary"
          :loading="busy"
          prepend-icon="tabler-send"
          @click="run('send', 'Bordereau transmis au destinataire')"
        >
          Transmettre
        </VBtn>
        <VBtn
          v-if="canAck"
          variant="tonal"
          color="success"
          :loading="busy"
          prepend-icon="tabler-mail-check"
          @click="showAckDialog = true"
        >
          Accusé réception
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="message"
      :type="messageType"
      class="mb-4"
      variant="tonal"
      closable
      @click:close="message = ''"
    >
      {{ message }}
    </VAlert>

    <div class="d-flex flex-wrap align-center ga-2 mb-4">
      <VChip
        :color="getTransmissionSlipStatusColor(slip.status)"
        variant="tonal"
      >
        {{ getTransmissionSlipStatusLabel(slip.status) }}
      </VChip>
      <VChip
        variant="tonal"
        color="primary"
      >
        {{ getTransmissionSlipNatureLabel(slip.nature) }}
      </VChip>
      <VChip
        v-if="slip.items?.length"
        variant="outlined"
      >
        {{ slip.items.length }} courrier(s)
      </VChip>
    </div>

    <VCard class="mb-4">
      <VCardText>
        <div class="text-caption text-medium-emphasis mb-3">
          Parcours du bordereau
        </div>
        <div class="d-flex flex-wrap align-center ga-2">
          <template
            v-for="(step, index) in workflowSteps"
            :key="step.key"
          >
            <VChip
              size="small"
              :color="step.done || step.active ? 'primary' : 'default'"
              :variant="step.active ? 'flat' : 'tonal'"
            >
              {{ index + 1 }}. {{ step.label }}
            </VChip>
            <VIcon
              v-if="index < workflowSteps.length - 1"
              icon="tabler-chevron-right"
              size="16"
              class="text-medium-emphasis"
            />
          </template>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <div class="parapheur-form-section mb-4">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-route"
              size="20"
            />
            Trajet
          </div>
          <div class="courrier-meta-grid">
            <div class="courrier-meta-grid__label">
              De
            </div>
            <div class="courrier-meta-grid__value font-weight-medium">
              {{ slip.from_structure?.name || '—' }}
            </div>
            <div class="courrier-meta-grid__label">
              Vers
            </div>
            <div class="courrier-meta-grid__value font-weight-medium">
              {{ slip.to_structure?.name || '—' }}
            </div>
            <div class="courrier-meta-grid__label">
              Nature
            </div>
            <div class="courrier-meta-grid__value">
              {{ getTransmissionSlipNatureLabel(slip.nature) }}
            </div>
            <div class="courrier-meta-grid__label">
              Observations
            </div>
            <div class="courrier-meta-grid__value">
              {{ slip.observations || '—' }}
            </div>
          </div>
        </div>

        <div class="parapheur-form-section mb-0">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-mail"
              size="20"
            />
            Courriers du bordereau
          </div>
          <VDataTable
            :headers="[
              { title: 'Référence', key: 'reference' },
              { title: 'Objet', key: 'object' },
              { title: 'Pièces', key: 'piece_count', width: '90px' },
            ]"
            :items="slip.items || []"
            density="comfortable"
          >
            <template #item.reference="{ item }">
              <button
                v-if="item.correspondence?.id"
                type="button"
                class="text-primary font-weight-medium text-decoration-underline"
                style="background: none; border: 0; cursor: pointer; padding: 0;"
                @click="openCorrespondence(item)"
              >
                {{ item.reference || formatCorrespondenceNumber(item.correspondence) }}
              </button>
              <span v-else>{{ item.reference || '—' }}</span>
            </template>
            <template #item.object="{ item }">
              {{ item.object || item.correspondence?.subject || '—' }}
            </template>
            <template #no-data>
              <div class="text-center text-medium-emphasis py-6">
                Aucun courrier sur ce bordereau
              </div>
            </template>
          </VDataTable>
        </div>
      </VCol>

      <VCol
        cols="12"
        md="5"
      >
        <div class="parapheur-form-section mb-4">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-file"
              size="20"
            />
            Document
          </div>
          <div class="courrier-meta-grid">
            <div class="courrier-meta-grid__label">
              DOCX
            </div>
            <div class="courrier-meta-grid__value">
              <RouterLink
                v-if="slip.document_id"
                :to="{ name: 'ged-id', params: { id: slip.document_id } }"
              >
                Ouvrir #{{ slip.document_id }}
              </RouterLink>
              <span v-else>Non généré</span>
            </div>
            <div class="courrier-meta-grid__label">
              Validé le
            </div>
            <div class="courrier-meta-grid__value">
              {{ formatCourrierDateTime(slip.validated_at) }}
            </div>
            <div class="courrier-meta-grid__label">
              Imprimé le
            </div>
            <div class="courrier-meta-grid__value">
              {{ formatCourrierDateTime(slip.printed_at) }}
            </div>
            <div class="courrier-meta-grid__label">
              Transmis le
            </div>
            <div class="courrier-meta-grid__value">
              {{ formatCourrierDateTime(slip.transmitted_at) }}
            </div>
            <div class="courrier-meta-grid__label">
              Reçu le
            </div>
            <div class="courrier-meta-grid__value">
              {{ formatCourrierDateTime(slip.received_at) }}
            </div>
          </div>
        </div>

        <div class="parapheur-form-section mb-0">
          <div class="parapheur-form-section__title">
            <VIcon
              icon="tabler-info-circle"
              size="20"
            />
            Actions disponibles
          </div>
          <ol class="ps-4 mb-0 text-body-2">
            <li class="mb-1">
              <strong>Valider</strong> — attribue le N° BT si besoin
            </li>
            <li class="mb-1">
              <strong>Générer DOCX</strong> — produit le document administratif
            </li>
            <li class="mb-1">
              <strong>Imprimer / Transmettre</strong> — envoie au service destinataire
            </li>
            <li>
              <strong>Accusé réception</strong> — confirme la prise en charge
            </li>
          </ol>
        </div>
      </VCol>
    </VRow>

    <VDialog
      v-model="showAckDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Accusé de réception</VCardTitle>
        <VCardText>
          <VFileInput
            label="Preuve scan (facultatif)"
            class="mb-4"
            prepend-icon="tabler-paperclip"
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
          <VBtn
            variant="text"
            @click="showAckDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="acknowledgeSlip"
          >
            Enregistrer l’AR
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showPrintDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Enregistrer l’impression</VCardTitle>
        <VCardText>
          <VTextField
            v-model="printForm.reason"
            label="Motif d’impression"
            class="mb-4"
          />
          <VCheckbox
            v-model="printForm.is_reprint"
            label="Réimpression"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showPrintDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="busy"
            @click="printSlip"
          >
            Confirmer
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
