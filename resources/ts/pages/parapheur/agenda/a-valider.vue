<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatAppointmentSlot } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'validate',
    subject: 'Appointment',
  },
})

const items = ref<any[]>([])
const loading = ref(false)
const busyId = ref<number | null>(null)
const rejectDialog = ref(false)
const rejectReason = ref('')
const current = ref<any>(null)

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/appointments', { query: { to_validate: 1 } })
    items.value = res.data ?? res
  }
  finally {
    loading.value = false
  }
}

const validate = async (item: any) => {
  busyId.value = item.id
  try {
    await $api(`/appointments/${item.id}/validate`, { method: 'POST', body: { confirm: true } })
    await load()
  }
  finally {
    busyId.value = null
  }
}

const openReject = (item: any) => {
  current.value = item
  rejectReason.value = ''
  rejectDialog.value = true
}

const reject = async () => {
  if (!current.value)
    return
  busyId.value = current.value.id
  try {
    await $api(`/appointments/${current.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectReason.value, communicable: true },
    })
    rejectDialog.value = false
    await load()
  }
  finally {
    busyId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="À valider"
      subtitle="Validation simplifiée des propositions de créneaux"
      icon="tabler-checks"
    />

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <VRow>
      <VCol
        v-for="item in items"
        :key="item.id"
        cols="12"
        md="6"
      >
        <VCard>
          <VCardText>
            <div class="text-overline mb-1">
              {{ item.reference }}
            </div>
            <div class="text-h6 mb-2">
              {{ item.subject }}
            </div>
            <p class="text-body-2 mb-2">
              <strong>Motif :</strong> {{ item.reason || '—' }}
            </p>
            <p class="text-body-2 mb-2">
              <strong>Durée :</strong> {{ item.duration_minutes || '—' }} min
            </p>
            <p class="text-body-2 mb-4">
              <strong>Proposition :</strong> {{ formatAppointmentSlot(item.start_at, item.end_at) }}
            </p>
            <div class="d-flex flex-wrap gap-2">
              <VBtn
                color="success"
                :loading="busyId === item.id"
                @click="validate(item)"
              >
                Valider
              </VBtn>
              <VBtn
                variant="tonal"
                :to="{ name: 'parapheur-agenda-id', params: { id: item.id } }"
              >
                Modifier le créneau
              </VBtn>
              <VBtn
                color="error"
                variant="tonal"
                @click="openReject(item)"
              >
                Refuser
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div
      v-if="!loading && !items.length"
      class="text-medium-emphasis"
    >
      Aucune demande en attente de validation.
    </div>

    <VDialog
      v-model="rejectDialog"
      max-width="480"
    >
      <VCard title="Refuser la demande">
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
            :disabled="rejectReason.length < 3"
            @click="reject"
          >
            Confirmer le refus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
