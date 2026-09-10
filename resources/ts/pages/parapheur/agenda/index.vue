<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { appointmentStatusColor, appointmentStatusLabel, formatAppointmentSlot } from '@/utils/appointmentsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Appointment',
  },
})

const ability = useAbility()
const stats = ref<any>(null)
const loading = ref(false)

const load = async () => {
  loading.value = true
  try {
    stats.value = await $api('/appointments/dashboard')
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const secretariatKpi = computed(() => {
  if (!stats.value)
    return []

  return [
    { title: 'RDV aujourd’hui', value: stats.value.today?.appointments, icon: 'tabler-calendar-event', color: 'primary' },
    { title: 'Audiences', value: stats.value.today?.audiences, icon: 'tabler-user-star', color: 'info' },
    { title: 'Réunions', value: stats.value.today?.meetings, icon: 'tabler-users-group', color: 'secondary' },
    { title: 'À examiner', value: stats.value.requests?.to_examine, icon: 'tabler-inbox', color: 'warning' },
    { title: 'À valider DG', value: stats.value.requests?.to_validate, icon: 'tabler-checks', color: 'error' },
    { title: 'En attente', value: stats.value.requests?.pending, icon: 'tabler-hourglass', color: 'secondary' },
  ]
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Agenda & Rendez-vous"
      subtitle="Bureau numérique du Directeur Général — audiences, créneaux et suivi"
      icon="tabler-calendar-event"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-calendar"
          :to="{ name: 'parapheur-agenda-calendrier' }"
        >
          Agenda
        </VBtn>
        <VBtn
          v-if="ability.can('create', 'Appointment') || ability.can('manage', 'Appointment')"
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'parapheur-agenda-nouveau' }"
        >
          Enregistrer / Demander
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="stats?.dg"
      type="info"
      variant="tonal"
      class="mb-6"
      title="Agenda du Directeur"
    >
      Aujourd’hui : {{ stats.dg.today }} · Prochain RDV : {{ stats.dg.next_appointment_at || '—' }} · À valider : {{ stats.dg.to_validate }}
    </VAlert>

    <VRow class="mb-6">
      <VCol
        v-for="card in secretariatKpi"
        :key="card.title"
        cols="6"
        md="2"
      >
        <VCard>
          <VCardText>
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="36"
              class="mb-2"
            >
              <VIcon
                :icon="card.icon"
                size="20"
              />
            </VAvatar>
            <div class="text-caption text-medium-emphasis">
              {{ card.title }}
            </div>
            <div class="text-h5">
              {{ card.value ?? '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardTitle>Mes rendez-vous aujourd’hui</VCardTitle>
          <VCardText>
            <VProgressLinear
              v-if="loading"
              indeterminate
              class="mb-4"
            />
            <div
              v-if="!stats?.today_list?.length"
              class="text-medium-emphasis"
            >
              Aucun rendez-vous planifié aujourd’hui.
            </div>
            <VList v-else>
              <VListItem
                v-for="item in stats.today_list"
                :key="item.id"
                :to="{ name: 'parapheur-agenda-id', params: { id: item.id } }"
              >
                <template #prepend>
                  <div class="text-subtitle-1 font-weight-bold me-4">
                    {{ item.start_at ? new Date(item.start_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—' }}
                  </div>
                </template>
                <VListItemTitle>{{ item.subject }}</VListItemTitle>
                <VListItemSubtitle>
                  {{ item.requester_name || item.requester_organization || '—' }}
                  · {{ item.duration_minutes || '—' }} min
                </VListItemSubtitle>
                <template #append>
                  <VChip
                    size="small"
                    :color="appointmentStatusColor(item.status)"
                  >
                    {{ appointmentStatusLabel(item.status) }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard class="mb-4">
          <VCardTitle>Mes demandes</VCardTitle>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>Total</span><strong>{{ stats?.my_requests?.total ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Confirmées</span><strong>{{ stats?.my_requests?.confirmed ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>En attente</span><strong>{{ stats?.my_requests?.pending ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-space-between">
              <span>Refusées</span><strong>{{ stats?.my_requests?.rejected ?? 0 }}</strong>
            </div>
            <VBtn
              class="mt-4"
              block
              variant="tonal"
              :to="{ name: 'parapheur-agenda-mes-rdv' }"
            >
              Voir mon suivi
            </VBtn>
          </VCardText>
        </VCard>
        <VCard>
          <VCardTitle>Accès rapide</VCardTitle>
          <VCardText class="d-flex flex-column gap-2">
            <VBtn
              variant="outlined"
              :to="{ name: 'parapheur-agenda-demandes' }"
            >
              File secrétariat
            </VBtn>
            <VBtn
              v-if="ability.can('validate', 'Appointment')"
              variant="outlined"
              :to="{ name: 'parapheur-agenda-avalider' }"
            >
              Validations DG
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
