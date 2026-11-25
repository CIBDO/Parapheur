<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useProductions } from '@/composables/useProductions'
import {
  productionFrequencyLabels,
  productionWeekdayLabel,
  productionWeekdayOptions,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'Production',
  },
})

const route = useRoute()
const router = useRouter()
const {
  getObligation,
  updateObligation,
  setObligationActive,
  suspendObligation,
} = useProductions()

const item = ref<any>(null)
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const editing = ref(false)
const showSuspend = ref(false)

const editForm = ref({
  label: '',
  description: '',
  deadline_time: '',
  frequency: 'business_days',
  schedule_weekday: 5,
  schedule_weekdays: [1, 2, 3, 4, 5] as number[],
  validation_required: true,
  auto_transmit: false,
  submit_to_parapheur: false,
})

const scheduleSummary = computed(() => {
  if (!item.value)
    return ''

  const frequency = item.value.frequency
  const config = item.value.schedule_config || {}

  if (frequency === 'weekdays') {
    const days = (config.weekdays || []).map((day: number) => productionWeekdayLabel(Number(day)))

    return days.length ? days.join(', ') : 'Aucun jour défini'
  }

  if (frequency === 'weekly' || frequency === 'biweekly') {
    return productionWeekdayLabel(Number(config.weekday ?? 5))
  }

  if (frequency === 'business_days')
    return 'Lundi à vendredi'

  if (frequency === 'daily')
    return 'Tous les jours'

  return ''
})

const suspendForm = ref({
  starts_on: new Date().toISOString().slice(0, 10),
  ends_on: '',
  reason: '',
})

const actorLabel = (actor: any) => {
  const labels: Record<string, string> = {
    contributor: 'Contributeur',
    validator: 'Validateur',
    recipient: 'Destinataire',
  }

  return labels[actor.role] || actor.role
}

const syncEditForm = () => {
  if (!item.value)
    return
  const config = item.value.schedule_config || {}
  editForm.value = {
    label: item.value.label || '',
    description: item.value.description || '',
    deadline_time: item.value.deadline_time || '',
    frequency: item.value.frequency || 'business_days',
    schedule_weekday: Number(config.weekday ?? 5),
    schedule_weekdays: Array.isArray(config.weekdays) && config.weekdays.length
      ? config.weekdays.map((day: number) => Number(day))
      : [1, 2, 3, 4, 5],
    validation_required: Boolean(item.value.validation_required),
    auto_transmit: Boolean(item.value.auto_transmit),
    submit_to_parapheur: Boolean(item.value.submit_to_parapheur),
  }
}

const toggleEditWeekday = (day: number) => {
  const current = editForm.value.schedule_weekdays
  if (current.includes(day))
    editForm.value.schedule_weekdays = current.filter(value => value !== day)
  else
    editForm.value.schedule_weekdays = [...current, day]
}

const buildScheduleConfig = () => {
  if (editForm.value.frequency === 'weekdays') {
    const days = editForm.value.schedule_weekdays.filter(day => day >= 0 && day <= 6)

    return { weekdays: days.length ? days : [1, 2, 3, 4, 5] }
  }

  if (editForm.value.frequency === 'weekly' || editForm.value.frequency === 'biweekly')
    return { weekday: Number(editForm.value.schedule_weekday) }

  if (item.value?.schedule_config)
    return item.value.schedule_config

  return null
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    item.value = await getObligation(route.params.id as string)
    syncEditForm()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Impossible de charger l’obligation.'
  }
  finally {
    loading.value = false
  }
}

const save = async () => {
  saving.value = true
  errorMessage.value = ''
  try {
    item.value = await updateObligation(route.params.id as string, {
      label: editForm.value.label,
      description: editForm.value.description || null,
      deadline_time: editForm.value.deadline_time || null,
      frequency: editForm.value.frequency,
      schedule_config: buildScheduleConfig(),
      validation_required: editForm.value.validation_required,
      auto_transmit: editForm.value.auto_transmit,
      submit_to_parapheur: editForm.value.submit_to_parapheur,
    })
    editing.value = false
    syncEditForm()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message
      || Object.values(error?.data?.errors || {})?.[0]?.[0]
      || 'Enregistrement impossible.'
  }
  finally {
    saving.value = false
  }
}

const toggleActive = async () => {
  if (!item.value)
    return
  saving.value = true
  errorMessage.value = ''
  try {
    item.value = await setObligationActive(item.value.id, !item.value.is_active)
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Changement d’état impossible.'
  }
  finally {
    saving.value = false
  }
}

const submitSuspend = async () => {
  if (!suspendForm.value.starts_on)
    return
  saving.value = true
  errorMessage.value = ''
  try {
    await suspendObligation(route.params.id as string, {
      starts_on: suspendForm.value.starts_on,
      ends_on: suspendForm.value.ends_on || null,
      reason: suspendForm.value.reason || null,
    })
    showSuspend.value = false
    await load()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message
      || Object.values(error?.data?.errors || {})?.[0]?.[0]
      || 'Suspension impossible.'
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="item?.label || 'Obligation'"
      :subtitle="item?.code || 'Fiche obligation'"
      icon="tabler-settings"
    >
      <template #actions>
        <VBtn
          v-if="item && !editing"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-edit"
          @click="editing = true"
        >
          Modifier
        </VBtn>
        <VBtn
          v-if="item"
          :color="item.is_active ? 'warning' : 'success'"
          variant="tonal"
          :prepend-icon="item.is_active ? 'tabler-player-pause' : 'tabler-player-play'"
          :loading="saving"
          @click="toggleActive"
        >
          {{ item.is_active ? 'Désactiver' : 'Activer' }}
        </VBtn>
        <VBtn
          v-if="item"
          color="error"
          variant="tonal"
          prepend-icon="tabler-calendar-off"
          @click="showSuspend = true"
        >
          Suspendre
        </VBtn>
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
          @click="router.push('/productions/obligations')"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

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

    <div
      v-if="loading && !item"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <VRow
      v-else-if="item"
      dense
    >
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="mb-4">
          <VCardText class="pa-5">
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="flat"
              >
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-repeat"
              >
                {{ productionFrequencyLabels[item.frequency] || item.frequency }}
              </VChip>
              <VChip
                v-if="scheduleSummary"
                size="small"
                variant="tonal"
                prepend-icon="tabler-calendar-week"
              >
                {{ scheduleSummary }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-clock"
              >
                {{ item.deadline_time || '—' }} · {{ item.timezone }}
              </VChip>
            </div>

            <template v-if="editing">
              <div class="d-flex flex-column gap-3">
                <AppTextField
                  v-model="editForm.label"
                  label="Libellé *"
                />
                <AppTextarea
                  v-model="editForm.description"
                  label="Description"
                  rows="3"
                />
                <AppTextField
                  v-model="editForm.deadline_time"
                  label="Heure limite"
                  placeholder="HH:MM:SS"
                />
                <AppSelect
                  v-model="editForm.frequency"
                  label="Fréquence / jours d’envoi"
                  :items="Object.entries(productionFrequencyLabels).map(([value, title]) => ({ title, value }))"
                />
                <AppSelect
                  v-if="editForm.frequency === 'weekly' || editForm.frequency === 'biweekly'"
                  v-model="editForm.schedule_weekday"
                  label="Jour de la semaine"
                  :items="[...productionWeekdayOptions]"
                />
                <div
                  v-if="editForm.frequency === 'weekdays'"
                  class="d-flex flex-column gap-2"
                >
                  <div class="text-body-2 font-weight-medium">
                    Jours d’envoi
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="day in productionWeekdayOptions"
                      :key="day.value"
                      :color="editForm.schedule_weekdays.includes(day.value) ? 'primary' : undefined"
                      :variant="editForm.schedule_weekdays.includes(day.value) ? 'flat' : 'outlined'"
                      filter
                      label
                      @click="toggleEditWeekday(day.value)"
                    >
                      {{ day.title }}
                    </VChip>
                  </div>
                </div>
                <div class="d-flex flex-wrap gap-4">
                  <VSwitch
                    v-model="editForm.validation_required"
                    label="Validation obligatoire"
                    color="primary"
                    hide-details
                  />
                  <VSwitch
                    v-model="editForm.auto_transmit"
                    label="Transmission automatique"
                    color="primary"
                    hide-details
                  />
                  <VSwitch
                    v-model="editForm.submit_to_parapheur"
                    label="Soumettre au parapheur"
                    color="primary"
                    hide-details
                  />
                </div>
                <div class="d-flex gap-2">
                  <VBtn
                    color="primary"
                    :loading="saving"
                    :disabled="!editForm.label.trim() || (editForm.frequency === 'weekdays' && !editForm.schedule_weekdays.length)"
                    @click="save"
                  >
                    Enregistrer
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    @click="editing = false; syncEditForm()"
                  >
                    Annuler
                  </VBtn>
                </div>
              </div>
            </template>
            <template v-else>
              <div class="text-body-1 text-high-emphasis mb-1">
                Description
              </div>
              <div
                class="text-body-2"
                :class="{ 'text-medium-emphasis': !item.description }"
              >
                {{ item.description || 'Aucune description renseignée.' }}
              </div>
            </template>
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
                  icon="tabler-users"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Acteurs
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VList v-if="item.actors?.length">
            <VListItem
              v-for="actor in item.actors"
              :key="actor.id"
              :title="actor.user?.name || actor.role_name || actor.structure?.name || '—'"
              :subtitle="actorLabel(actor)"
            />
          </VList>
          <div
            v-else
            class="text-center py-8 text-medium-emphasis"
          >
            Aucun acteur configuré
          </div>
        </VCard>

        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="warning"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-bell"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Rappels
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText class="text-body-2 text-medium-emphasis">
            Un seul rappel automatique : digest matinal (07:30–10:00 Bamako) si l’élément
            du jour n’est pas encore transmis. Pas de règles de relance individuelles.
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="text-h6">
              Paramètres
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VList density="compact">
            <VListItem
              title="Producteur"
              :subtitle="item.producer?.name || '—'"
            />
            <VListItem
              title="Suppléant"
              :subtitle="item.deputy?.name || '—'"
            />
            <VListItem
              title="Structure"
              :subtitle="item.structure?.name || '—'"
            />
            <VListItem
              title="Validation"
              :subtitle="item.validation_required ? `Obligatoire (${item.validation_mode})` : 'Facultative'"
            />
            <VListItem
              title="Transmission"
              :subtitle="item.auto_transmit ? 'Automatique' : 'Manuelle'"
            />
            <VListItem
              title="Parapheur"
              :subtitle="item.submit_to_parapheur ? 'Oui' : 'Non'"
            />
            <VListItem
              title="Formats"
              :subtitle="(item.allowed_formats || []).join(', ') || '—'"
            />
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="showSuspend"
      max-width="480"
    >
      <VCard title="Suspendre l’obligation">
        <VCardText class="d-flex flex-column gap-3">
          <AppTextField
            v-model="suspendForm.starts_on"
            label="Début *"
            type="date"
          />
          <AppTextField
            v-model="suspendForm.ends_on"
            label="Fin (optionnel)"
            type="date"
          />
          <AppTextarea
            v-model="suspendForm.reason"
            label="Motif"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showSuspend = false">
            Annuler
          </VBtn>
          <VBtn
            color="error"
            :loading="saving"
            :disabled="!suspendForm.starts_on"
            @click="submitSuspend"
          >
            Suspendre
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
