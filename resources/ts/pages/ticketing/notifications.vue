<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { $api } from '@/utils/api'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing' },
})

const loading = ref(true)
const saving = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const form = ref({
  database_enabled: true,
  mail_enabled: true,
  muted_events: [] as string[],
})

const eventOptions = [
  { value: 'created', title: 'Nouveau ticket' },
  { value: 'assigned', title: 'Affectation' },
  { value: 'taken_charge', title: 'Prise en charge' },
  { value: 'comment_agent', title: 'Commentaire agent' },
  { value: 'comment_requester', title: 'Commentaire demandeur' },
  { value: 'waiting_requester', title: 'En attente demandeur' },
  { value: 'escalated', title: 'Escalade' },
  { value: 'sla_warning', title: 'Alerte SLA' },
  { value: 'sla_breach', title: 'Dépassement SLA' },
  { value: 'resolved', title: 'Résolution' },
  { value: 'reopened', title: 'Réouverture' },
  { value: 'closed', title: 'Clôture' },
  { value: 'priority_changed', title: 'Changement de priorité' },
]

onMounted(async () => {
  loading.value = true
  try {
    const pref = await $api('/ticketing/notification-preferences')
    form.value = {
      database_enabled: pref.database_enabled !== false,
      mail_enabled: pref.mail_enabled !== false,
      muted_events: Array.isArray(pref.muted_events) ? pref.muted_events : [],
    }
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Chargement impossible'
  }
  finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  errorMsg.value = ''
  try {
    await $api('/ticketing/notification-preferences', {
      method: 'PUT',
      body: form.value,
    })
    successMsg.value = 'Préférences enregistrées'
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Enregistrement impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Notifications ticketing"
      subtitle="Canaux et événements à recevoir"
    />

    <VAlert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>
    <VAlert
      v-if="successMsg"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMsg = ''"
    >
      {{ successMsg }}
    </VAlert>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <VCard
      v-else
      max-width="720"
    >
      <VCardText>
        <div class="text-subtitle-2 mb-2">
          Canaux
        </div>
        <VCheckbox
          v-model="form.database_enabled"
          label="Notifications dans l’application"
          hide-details
          class="mb-1"
        />
        <VCheckbox
          v-model="form.mail_enabled"
          label="Notifications par e-mail"
          hide-details
          class="mb-4"
        />

        <div class="text-subtitle-2 mb-2">
          Événements à masquer
        </div>
        <p class="text-caption text-medium-emphasis mb-3">
          Les événements cochés ne génèrent ni notification in-app ni e-mail.
        </p>
        <VRow dense>
          <VCol
            v-for="opt in eventOptions"
            :key="opt.value"
            cols="12"
            sm="6"
          >
            <VCheckbox
              v-model="form.muted_events"
              :label="opt.title"
              :value="opt.value"
              hide-details
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          :loading="saving"
          @click="save"
        >
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
