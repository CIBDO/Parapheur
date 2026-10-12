<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { productionFrequencyLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'Production',
  },
})

const router = useRouter()
const loading = ref(false)
const items = ref<any[]>([])
const total = ref(0)
const search = ref('')
const showWizard = ref(false)
const step = ref(1)
const creating = ref(false)
const users = ref<{ id: number; name: string }[]>([])
const structures = ref<{ id: number; name: string; code?: string }[]>([])
const calendars = ref<{ id: number; name: string; code?: string }[]>([])

const wizardSteps = ['Général', 'Acteurs', 'Périodicité', 'Livrables', 'Rappels', 'Récapitulatif']

const frequencies = Object.entries(productionFrequencyLabels).map(([value, title]) => ({ title, value }))

const emptyForm = () => ({
  code: '',
  label: '',
  description: '',
  category: 'tresorerie',
  structure_id: null as number | null,
  producer_id: null as number | null,
  deputy_id: null as number | null,
  frequency: 'business_days',
  deadline_time: '10:00:00',
  timezone: 'Africa/Bamako',
  calendar_id: null as number | null,
  confidentiality: 'restreint',
  schedule_weekday: 5,
  schedule_day: 1,
  schedule_month: 1,
  schedule_days: '1,15',
  once_date: '',
  allowed_formats: ['pdf', 'xlsx', 'docx', 'csv'],
  min_files: 1,
  max_files: 10,
  validation_required: true,
  validation_mode: 'sequential',
  auto_transmit: false,
  submit_to_parapheur: false,
  validators: [] as { user_id: number | null; step_order: number }[],
  recipient_user_id: null as number | null,
  recipients: [] as { role_name?: string; user_id?: number | null }[],
  reminder_rules: [
    { offset_type: 'before', offset_minutes: 90, target: 'producer' },
    { offset_type: 'before', offset_minutes: 30, target: 'producer' },
    { offset_type: 'after', offset_minutes: 30, target: 'hierarchy', escalate: true },
  ],
})

const form = ref(emptyForm())

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/productions/obligations', { query: { q: search.value || undefined } })
    items.value = res.data ?? res
    total.value = res.total ?? items.value.length
  }
  finally {
    loading.value = false
  }
}

const loadMeta = async () => {
  const [u, s, c] = await Promise.all([
    $api('/meta/users'),
    $api('/meta/structures'),
    $api('/productions/calendars'),
  ])
  users.value = u.data ?? u
  structures.value = s.data ?? s
  calendars.value = c.data ?? c
}

const toggleActive = async (item: any) => {
  await $api(`/productions/obligations/${item.id}/active`, {
    method: 'POST',
    body: { is_active: !item.is_active },
  })
  await load()
}

const openWizard = () => {
  form.value = emptyForm()
  step.value = 1
  showWizard.value = true
}

const scheduleConfig = () => {
  const frequency = form.value.frequency
  if (frequency === 'weekly' || frequency === 'biweekly')
    return { weekday: Number(form.value.schedule_weekday) }
  if (frequency === 'semimonthly')
    return { days: form.value.schedule_days.split(',').map(part => Number(part.trim())).filter(Boolean) }
  if (frequency === 'monthly' || frequency === 'quarterly' || frequency === 'semiannual')
    return { day: Number(form.value.schedule_day) }
  if (frequency === 'annual')
    return { month: Number(form.value.schedule_month), day: Number(form.value.schedule_day) }
  if (frequency === 'once' && form.value.once_date)
    return { date: form.value.once_date }

  return null
}

const create = async () => {
  creating.value = true
  try {
    const { schedule_weekday, schedule_day, schedule_month, schedule_days, once_date, recipient_user_id, ...payload } = form.value
    await $api('/productions/obligations', {
      method: 'POST',
      body: {
        ...payload,
        schedule_config: scheduleConfig(),
        validators: form.value.validators.filter(v => v.user_id),
        recipients: recipient_user_id
          ? [{ user_id: recipient_user_id }, { role_name: 'Directeur Général' }, { role_name: 'DGA' }]
          : [
              { role_name: 'Directeur Général' },
              { role_name: 'DGA' },
            ],
      },
    })
    showWizard.value = false
    await load()
  }
  finally {
    creating.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadMeta()])
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Obligations de production"
      subtitle="Référentiel configurable des échéances et destinataires"
      icon="tabler-settings"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openWizard"
        >
          Nouvelle obligation
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
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <AppTextField
              v-model="search"
              label="Recherche (code, libellé)"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              @click="load"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <div
        v-if="loading && !items.length"
        class="text-center py-10"
      >
        <VProgressCircular indeterminate />
      </div>
      <VTable v-else>
        <thead>
          <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th>Fréquence</th>
            <th>Heure limite</th>
            <th>Actif</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            class="cursor-pointer"
            @click="router.push(`/productions/obligations/${item.id}`)"
          >
            <td class="font-weight-medium">
              {{ item.code }}
            </td>
            <td>{{ item.label }}</td>
            <td>
              <VChip
                size="small"
                variant="tonal"
              >
                {{ productionFrequencyLabels[item.frequency] || item.frequency }}
              </VChip>
            </td>
            <td>{{ item.deadline_time || '—' }}</td>
            <td @click.stop>
              <VSwitch
                :model-value="item.is_active"
                color="success"
                hide-details
                inset
                @update:model-value="toggleActive(item)"
              />
            </td>
            <td @click.stop>
              <VBtn
                icon
                size="small"
                variant="text"
                @click="router.push(`/productions/obligations/${item.id}`)"
              >
                <VIcon icon="tabler-eye" />
              </VBtn>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td
              colspan="6"
              class="text-center text-medium-emphasis py-10"
            >
              <VIcon
                icon="tabler-settings-off"
                size="40"
                class="mb-2 d-block mx-auto"
              />
              Aucune obligation
            </td>
          </tr>
        </tbody>
      </VTable>
      <VCardText
        v-if="total"
        class="text-caption"
      >
        {{ total }} résultat(s)
      </VCardText>
    </VCard>

    <VDialog
      v-model="showWizard"
      max-width="760"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Nouvelle obligation</VCardTitle>
          <VCardSubtitle>
            Étape {{ step }} sur {{ wizardSteps.length }} — {{ wizardSteps[step - 1] }}
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <div class="d-flex flex-wrap gap-2 mb-6">
            <VChip
              v-for="(label, index) in wizardSteps"
              :key="label"
              size="small"
              :color="step === index + 1 ? 'primary' : 'default'"
              :variant="step === index + 1 ? 'flat' : 'tonal'"
            >
              {{ index + 1 }}. {{ label }}
            </VChip>
          </div>

          <div
            v-if="step === 1"
            class="d-flex flex-column gap-4"
          >
            <AppTextField
              v-model="form.code"
              label="Code unique"
            />
            <AppTextField
              v-model="form.label"
              label="Libellé"
            />
            <AppTextarea
              v-model="form.description"
              label="Description"
              rows="3"
            />
            <AppSelect
              v-model="form.structure_id"
              :items="structures"
              item-title="name"
              item-value="id"
              label="Structure responsable"
              clearable
            />
          </div>

          <div
            v-else-if="step === 2"
            class="d-flex flex-column gap-4"
          >
            <UserAutocomplete
              v-model="form.producer_id"
              :items="users"
              label="Producteur principal"
            />
            <UserAutocomplete
              v-model="form.deputy_id"
              :items="users"
              label="Suppléant"
            />
            <UserAutocomplete
              :model-value="form.validators[0]?.user_id ?? null"
              :items="users"
              label="Validateur"
              @update:model-value="(v: number | number[] | null) => { form.validators = [{ user_id: typeof v === 'number' ? v : null, step_order: 1 }] }"
            />
            <UserAutocomplete
              v-model="form.recipient_user_id"
              :items="users"
              label="Destinataire nominatif"
            />
            <div class="text-caption text-medium-emphasis">
              Le Directeur Général et le DGA restent destinataires par leur rôle. Une délégation active élargit la transmission.
            </div>
          </div>

          <div
            v-else-if="step === 3"
            class="d-flex flex-column gap-4"
          >
            <AppSelect
              v-model="form.frequency"
              :items="frequencies"
              label="Fréquence"
            />
            <AppTextField
              v-model="form.deadline_time"
              label="Heure limite"
              hint="Fuseau Africa/Bamako"
              persistent-hint
            />
            <AppSelect
              v-if="form.frequency === 'weekly' || form.frequency === 'biweekly'"
              v-model="form.schedule_weekday"
              label="Jour de la semaine"
              :items="[
                { title: 'Lundi', value: 1 },
                { title: 'Mardi', value: 2 },
                { title: 'Mercredi', value: 3 },
                { title: 'Jeudi', value: 4 },
                { title: 'Vendredi', value: 5 },
              ]"
            />
            <AppTextField
              v-if="form.frequency === 'semimonthly'"
              v-model="form.schedule_days"
              label="Jours du mois"
              hint="Deux dates, par exemple 1,15"
              persistent-hint
            />
            <AppTextField
              v-if="['monthly', 'quarterly', 'semiannual', 'annual'].includes(form.frequency)"
              v-model.number="form.schedule_day"
              type="number"
              label="Jour du mois"
            />
            <AppTextField
              v-if="form.frequency === 'annual'"
              v-model.number="form.schedule_month"
              type="number"
              label="Mois"
            />
            <AppTextField
              v-if="form.frequency === 'once'"
              v-model="form.once_date"
              type="date"
              label="Date de production"
            />
            <AppSelect
              v-model="form.calendar_id"
              :items="calendars"
              item-title="name"
              item-value="id"
              label="Calendrier des jours fériés"
              clearable
            />
            <AppSelect
              v-model="form.confidentiality"
              label="Confidentialité"
              :items="[
                { title: 'Normal', value: 'normal' },
                { title: 'Restreint', value: 'restreint' },
                { title: 'Confidentiel', value: 'confidentiel' },
              ]"
            />
          </div>

          <div
            v-else-if="step === 4"
            class="d-flex flex-column gap-4"
          >
            <AppSelect
              v-model="form.allowed_formats"
              :items="['pdf', 'xlsx', 'docx', 'csv', 'png', 'jpg']"
              label="Formats autorisés"
              multiple
              chips
            />
            <AppTextField
              v-model.number="form.min_files"
              type="number"
              label="Nombre minimal de fichiers"
            />
            <VSwitch
              v-model="form.validation_required"
              label="Validation obligatoire"
              color="primary"
              inset
              hide-details
            />
            <VSwitch
              v-model="form.auto_transmit"
              label="Transmission automatique après validation"
              color="primary"
              inset
              hide-details
            />
            <VSwitch
              v-model="form.submit_to_parapheur"
              label="Soumettre aussi les livrables au parapheur"
              color="primary"
              inset
              hide-details
            />
          </div>

          <div
            v-else-if="step === 5"
            class="text-body-1"
          >
            <div class="text-medium-emphasis mb-3">
              Règles appliquées à la création
            </div>
            <VList density="compact">
              <VListItem
                title="Rappel 90 minutes avant l’échéance"
                prepend-icon="tabler-bell"
              />
              <VListItem
                title="Rappel 30 minutes avant l’échéance"
                prepend-icon="tabler-bell"
              />
              <VListItem
                title="Escalade hiérarchique 30 minutes après"
                prepend-icon="tabler-alert-triangle"
              />
            </VList>
          </div>

          <VList
            v-else
            density="comfortable"
          >
            <VListItem
              :title="form.label || 'Sans libellé'"
              :subtitle="form.code || 'Sans code'"
            />
            <VListItem
              title="Fréquence"
              :subtitle="`${productionFrequencyLabels[form.frequency] || form.frequency} · ${form.deadline_time}`"
            />
            <VListItem
              title="Validation"
              :subtitle="form.validation_required ? 'Obligatoire' : 'Facultative'"
            />
          </VList>
        </VCardText>
        <VDivider />
        <VCardActions>
          <VBtn
            variant="text"
            @click="showWizard = false"
          >
            Annuler
          </VBtn>
          <VSpacer />
          <VBtn
            v-if="step > 1"
            variant="tonal"
            @click="step--"
          >
            Précédent
          </VBtn>
          <VBtn
            v-if="step < wizardSteps.length"
            color="primary"
            @click="step++"
          >
            Suivant
          </VBtn>
          <VBtn
            v-else
            color="primary"
            prepend-icon="tabler-check"
            :loading="creating"
            @click="create"
          >
            Activer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
