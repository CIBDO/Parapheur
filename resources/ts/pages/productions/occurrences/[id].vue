<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  formatTaskDue,
  productionDelayLabels,
  productionStatusColor,
  productionStatusLabels,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const acting = ref(false)
const uploading = ref(false)
const item = ref<any>(null)
const fileInput = ref<File[] | null>(null)
const decision = ref('approved')
const motif = ref('')
const errorMessage = ref('')
const justification = ref('')

const id = computed(() => Number(route.params.id))

const depositStatuses = ['a_produire', 'en_preparation', 'deposee', 'a_completer', 'rejetee']

const canDeposit = computed(() => depositStatuses.includes(item.value?.status))
const awaitingDecision = computed(() => ['soumise_validation', 'en_validation'].includes(item.value?.status))
const readyToSend = computed(() => ['validee', 'prete_a_transmettre'].includes(item.value?.status))
const isLate = computed(() => Boolean(item.value?.delay_flags?.length))

const nextStep = computed(() => {
  if (canDeposit.value)
    return item.value?.files?.length ? 'Le fichier est prêt : soumettez-le.' : 'Déposez le fichier attendu.'
  if (awaitingDecision.value)
    return 'Une décision est attendue.'
  if (readyToSend.value)
    return 'Le livrable peut être transmis.'
  if (item.value?.status === 'transmise')
    return 'Production transmise. Une rectification reste possible.'

  return 'Aucune action à cette étape.'
})

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    item.value = await $api(`/productions/occurrences/${id.value}`)
    justification.value = item.value?.delay_justification || ''
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Impossible de charger cette production.'
  }
  finally {
    loading.value = false
  }
}

const run = async (fn: () => Promise<void>) => {
  acting.value = true
  errorMessage.value = ''
  try {
    await fn()
    await load()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message
      || Object.values(error?.data?.errors || {})?.[0]?.[0]
      || 'Action impossible.'
  }
  finally {
    acting.value = false
  }
}

const deposit = async () => {
  const file = fileInput.value?.[0]
  if (!file)
    return
  uploading.value = true
  errorMessage.value = ''
  try {
    const body = new FormData()
    body.append('file', file)
    await $api(`/productions/occurrences/${id.value}/deposit`, { method: 'POST', body })
    fileInput.value = null
    await load()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message
      || error?.data?.errors?.file?.[0]
      || 'Dépôt refusé.'
  }
  finally {
    uploading.value = false
  }
}

const submit = () => run(() => $api(`/productions/occurrences/${id.value}/submit`, { method: 'POST' }))

const decide = () => run(() => $api(`/productions/occurrences/${id.value}/decide`, {
  method: 'POST',
  body: { decision: decision.value, motif: motif.value || null },
}))

const transmit = (rectificative = false) => run(() => $api(`/productions/occurrences/${id.value}/transmit`, {
  method: 'POST',
  body: { rectificative },
}))

const createOffice = () => run(() => $api(`/productions/occurrences/${id.value}/office`, {
  method: 'POST',
  body: { format: 'docx' },
}))

const classify = (fileId: number) => run(() => $api(`/productions/occurrences/${id.value}/files/${fileId}/submit-ged`, {
  method: 'POST',
}))

const submitParapheur = () => run(() => $api(`/productions/occurrences/${id.value}/submit-parapheur`, {
  method: 'POST',
}))

const justify = () => run(async () => {
  await $api(`/productions/occurrences/${id.value}/justify-delay`, {
    method: 'POST',
    body: { justification: justification.value },
  })
})

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="item?.obligation?.label || 'Occurrence'"
      :subtitle="item?.reference || 'Fiche de production'"
      icon="tabler-file-upload"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/productions/occurrences')"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading && !item"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="item">
      <VAlert
        v-if="item.delay_flags?.length"
        type="error"
        variant="tonal"
        class="mb-4"
        density="comfortable"
        icon="tabler-alert-triangle"
      >
        {{ item.delay_flags.map((flag: string) => productionDelayLabels[flag] || flag).join(' · ') }}
        — échéance {{ formatTaskDue(item.deadline_at) }}
      </VAlert>

      <VCard
        v-if="isLate && !['cloturee', 'annulee'].includes(item.status)"
        class="mb-4"
      >
        <VCardItem>
          <VCardTitle class="text-h6">
            Justification du retard
          </VCardTitle>
          <VCardSubtitle>
            Expliquez pourquoi l’échéance n’a pas été tenue.
          </VCardSubtitle>
        </VCardItem>
        <VCardText class="d-flex flex-column gap-3">
          <AppTextarea
            v-model="justification"
            label="Justification"
            rows="3"
            counter="2000"
          />
          <div>
            <VBtn
              color="error"
              variant="tonal"
              prepend-icon="tabler-message"
              :loading="acting"
              :disabled="!justification.trim()"
              @click="justify"
            >
              Enregistrer
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <VAlert
        v-if="errorMessage"
        type="warning"
        variant="tonal"
        class="mb-4"
        closable
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <VRow dense>
        <VCol
          cols="12"
          lg="8"
        >
          <VCard class="mb-4">
            <VCardText class="pa-5">
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip
                  size="small"
                  :color="productionStatusColor(item.status)"
                  variant="flat"
                >
                  {{ productionStatusLabels[item.status] || item.status }}
                </VChip>
                <VChip
                  v-if="item.deadline_at"
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-calendar-event"
                >
                  {{ formatTaskDue(item.deadline_at) }}
                </VChip>
                <VChip
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-hash"
                >
                  {{ item.period_key }}
                </VChip>
              </div>

              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    Responsable
                  </div>
                  <div class="text-body-1">
                    {{ item.responsible?.name || '—' }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    Structure
                  </div>
                  <div class="text-body-1">
                    {{ item.structure?.name || '—' }}
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    Période couverte
                  </div>
                  <div class="text-body-1">
                    {{ formatTaskDue(item.period_start) }} — {{ formatTaskDue(item.period_end) }}
                  </div>
                </VCol>
                <VCol
                  v-if="item.task?.reference"
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    Tâche liée
                  </div>
                  <RouterLink
                    class="text-primary"
                    :to="`/taches/${item.task.id}`"
                  >
                    {{ item.task.reference }}
                  </RouterLink>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-paperclip"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Livrables
              </VCardTitle>
              <VCardSubtitle>
                {{ item.files?.length || 0 }} fichier(s)
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VList v-if="item.files?.length">
              <VListItem
                v-for="file in item.files"
                :key="file.id"
                :title="file.document?.title || file.document?.object || `Fichier #${file.id}`"
                :subtitle="`Version ${file.logical_version}${file.transmitted_at ? ' · transmis' : ''}`"
              >
                <template #prepend>
                  <VAvatar
                    color="info"
                    variant="tonal"
                    rounded
                    size="36"
                  >
                    <VIcon icon="tabler-file" />
                  </VAvatar>
                </template>
                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VBtn
                      v-if="['validee', 'prete_a_transmettre', 'transmise', 'cloturee'].includes(item.status)"
                      size="small"
                      variant="tonal"
                      @click="classify(file.id)"
                    >
                      Classer GED
                    </VBtn>
                    <VChip
                      size="small"
                      variant="tonal"
                      :color="file.transmitted_at ? 'success' : 'secondary'"
                    >
                      {{ file.status }}
                    </VChip>
                  </div>
                </template>
              </VListItem>
            </VList>
            <div
              v-else
              class="text-center py-8 text-medium-emphasis"
            >
              <VIcon
                icon="tabler-file-off"
                size="36"
                class="mb-2"
              />
              <div>Aucun fichier déposé</div>
            </div>
            <VDivider v-if="canDeposit" />
            <VCardText
              v-if="canDeposit"
              class="d-flex flex-wrap align-end gap-3"
            >
              <VFileInput
                v-model="fileInput"
                label="Ajouter un fichier"
                prepend-icon=""
                prepend-inner-icon="tabler-upload"
                density="comfortable"
                hide-details
                class="flex-grow-1"
                style="min-inline-size: 240px"
              />
              <VBtn
                color="primary"
                prepend-icon="tabler-upload"
                :loading="uploading"
                :disabled="!fileInput?.length"
                @click="deposit"
              >
                Déposer
              </VBtn>
            </VCardText>
          </VCard>

          <VCard v-if="item.transmissions?.length">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="success"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-send"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Transmissions
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="tx in item.transmissions"
                :key="tx.id"
                :title="tx.reference"
                :subtitle="`${tx.transmitter?.name || '—'} · ${tx.recipients?.length || 0} destinataire(s)`"
              >
                <template #append>
                  <VChip
                    size="small"
                    variant="tonal"
                    :color="tx.is_rectificative ? 'warning' : 'success'"
                  >
                    {{ tx.is_rectificative ? 'Rectificative' : 'Initiale' }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Prochaine étape
              </VCardTitle>
              <VCardSubtitle>
                {{ nextStep }}
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VCardText class="d-flex flex-column gap-3">
              <template v-if="canDeposit">
                <VBtn
                  variant="text"
                  prepend-icon="tabler-file-type-docx"
                  :loading="acting"
                  @click="createOffice"
                >
                  Créer un document
                </VBtn>
                <VBtn
                  v-if="item.obligation?.submit_to_parapheur"
                  variant="text"
                  prepend-icon="tabler-writing"
                  :loading="acting"
                  @click="submitParapheur"
                >
                  Envoyer au parapheur
                </VBtn>
                <VBtn
                  color="primary"
                  prepend-icon="tabler-send"
                  :loading="acting"
                  :disabled="!item.files?.length"
                  @click="submit"
                >
                  Soumettre
                </VBtn>
              </template>

              <template v-else-if="awaitingDecision && $can('validate', 'Production')">
                <AppSelect
                  v-model="decision"
                  label="Décision"
                  :items="[
                    { title: 'Approuver', value: 'approved' },
                    { title: 'Demander une correction', value: 'correction' },
                    { title: 'Rejeter', value: 'rejected' },
                  ]"
                />
                <AppTextarea
                  v-model="motif"
                  label="Motif"
                  rows="2"
                />
                <VBtn
                  color="success"
                  prepend-icon="tabler-checks"
                  :loading="acting"
                  @click="decide"
                >
                  Enregistrer la décision
                </VBtn>
              </template>

              <VBtn
                v-else-if="readyToSend && $can('transmit', 'Production')"
                color="primary"
                prepend-icon="tabler-mail-forward"
                :loading="acting"
                @click="transmit(false)"
              >
                Transmettre
              </VBtn>

              <VBtn
                v-else-if="item.status === 'transmise' && $can('transmit', 'Production')"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-refresh"
                :loading="acting"
                @click="transmit(true)"
              >
                Envoyer une rectification
              </VBtn>

              <p
                v-else
                class="text-body-2 text-medium-emphasis mb-0"
              >
                Cette étape est entre les mains d’un autre acteur.
              </p>
            </VCardText>
          </VCard>

          <VCard v-if="item.validations?.length">
            <VCardItem>
              <VCardTitle class="text-h6">
                Circuit
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VList density="compact">
              <VListItem
                v-for="step in item.validations"
                :key="step.id"
                :title="step.validator?.name || `Étape ${step.step_order}`"
                :subtitle="step.motif || step.decision"
              >
                <template #prepend>
                  <VAvatar
                    size="32"
                    :color="step.decision === 'approved' ? 'success' : step.decision === 'pending' ? 'warning' : 'error'"
                    variant="tonal"
                  >
                    {{ step.step_order }}
                  </VAvatar>
                </template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>
