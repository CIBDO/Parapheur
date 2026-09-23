<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'DashboardDg',
  },
})

interface DocItem {
  id: number
  reference?: string
  object: string
  status: string
  priority: string
  expected_action: string
  due_date?: string
  submitted_at?: string
  structure?: { code: string; name: string }
  author?: { name: string }
  type?: { name: string }
}

interface DgStats {
  received: number
  to_process: number
  urgent: number
  overdue: number
  validated: number
  returned: number
  instructions_open: number
  instructions_late: number
  by_status: Record<string, number>
  by_structure: Record<string, number>
  meetings_today?: number
  meetings_this_week?: number
  meeting_decisions_open?: number
  meeting_decisions_late?: number
  meeting_minutes_to_validate?: number
  appointments_today?: number
  appointments_to_validate?: number
  appointments_next_at?: string | null
  audiences_today?: number
}

const vuetifyTheme = useTheme()
const userData = useCookie<any>('userData')

const stats = ref<DgStats | null>(null)
const documents = ref<DocItem[]>([])
const agendaToday = ref<any[]>([])
const loading = ref(true)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12)
    return 'Bonjour'
  if (hour < 18)
    return 'Bon après-midi'

  return 'Bonsoir'
})

const kpiCards = computed(() => [
  {
    title: 'À traiter',
    value: stats.value?.to_process ?? 0,
    icon: 'tabler-inbox',
    color: 'primary',
  },
  {
    title: 'Urgents',
    value: stats.value?.urgent ?? 0,
    icon: 'tabler-alert-triangle',
    color: 'error',
  },
  {
    title: 'En retard',
    value: stats.value?.overdue ?? 0,
    icon: 'tabler-clock-exclamation',
    color: 'warning',
  },
  {
    title: 'Validés',
    value: stats.value?.validated ?? 0,
    icon: 'tabler-circle-check',
    color: 'success',
  },
  {
    title: 'Retournés',
    value: stats.value?.returned ?? 0,
    icon: 'tabler-arrow-back-up',
    color: 'secondary',
  },
  {
    title: 'Reçus',
    value: stats.value?.received ?? 0,
    icon: 'tabler-files',
    color: 'info',
  },
])

const formatTime = (value?: string | null) => {
  if (!value)
    return '—'

  return new Date(value).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const statusLabels: Record<string, string> = {
  brouillon: 'Brouillon',
  transmis: 'Transmis',
  a_consulter: 'À consulter',
  a_viser: 'À viser',
  a_valider: 'À valider',
  a_corriger: 'À corriger',
  valide: 'Validé',
  traite: 'Traité',
  archive: 'Archivé',
  en_circuit: 'En circuit',
}

const priorityLabel = (priority: string) => {
  const map: Record<string, string> = {
    normale: 'Normale',
    importante: 'Importante',
    urgente: 'Urgente',
    tres_urgente: 'Très urgente',
  }

  return map[priority] ?? priority
}

const priorityColor = (priority: string) => {
  if (priority === 'tres_urgente' || priority === 'urgente')
    return 'error'
  if (priority === 'importante')
    return 'warning'

  return 'secondary'
}

const actionLabel = (action: string) => {
  const map: Record<string, string> = {
    consultation: 'Consultation',
    visa: 'Visa',
    validation: 'Validation',
    information: 'Information',
  }

  return map[action] ?? action
}

const structureSeries = computed(() => [{
  name: 'Documents',
  data: Object.values(stats.value?.by_structure ?? {}),
}])

const structureChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const labelColor = `rgba(${hexToRgb(currentTheme['on-surface'])}, ${variableTheme['disabled-opacity']})`
  const categories = Object.keys(stats.value?.by_structure ?? {})

  return {
    chart: {
      parentHeightOffset: 0,
      type: 'bar',
      toolbar: { show: false },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        columnWidth: '40%',
        distributed: true,
      },
    },
    colors: [
      currentTheme.primary,
      currentTheme.success,
      currentTheme.warning,
      currentTheme.info,
      currentTheme.error,
    ],
    grid: {
      strokeDashArray: 4,
      borderColor: `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`,
      xaxis: { lines: { show: false } },
      padding: { top: -10, left: -8, right: -8, bottom: -8 },
    },
    dataLabels: { enabled: false },
    legend: { show: false },
    tooltip: { enabled: true },
    xaxis: {
      categories,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: labelColor, fontSize: '12px' } },
    },
    yaxis: {
      labels: { style: { colors: labelColor, fontSize: '12px' } },
    },
  }
})

const statusBreakdown = computed(() => {
  const entries = Object.entries(stats.value?.by_status ?? {})
  const total = entries.reduce((sum, [, count]) => sum + Number(count), 0) || 1

  return entries
    .map(([status, count]) => ({
      status,
      label: statusLabels[status] ?? status,
      count: Number(count),
      percent: Math.round((Number(count) / total) * 100),
    }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 6)
})

const statusColor = (status: string) => {
  if (['valide', 'traite', 'archive'].includes(status))
    return 'success'
  if (['a_corriger'].includes(status))
    return 'error'
  if (['a_valider', 'a_viser'].includes(status))
    return 'warning'
  if (['transmis', 'a_consulter', 'en_circuit'].includes(status))
    return 'primary'

  return 'secondary'
}

const loadError = ref('')

const loadDashboard = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [statsRes, docsRes, agendaRes] = await Promise.all([
      $api('/dashboard/dg'),
      $api('/parapheur/documents', { query: { folder: 'a_traiter' } }),
      $api('/appointments/dashboard').catch(() => null),
    ])

    stats.value = statsRes
    documents.value = docsRes.data ?? docsRes
    agendaToday.value = agendaRes?.today_list ?? []
  }
  catch (e: any) {
    loadError.value = e?.data?.message || e?.message || 'Impossible de charger le bureau DG'
    stats.value = null
    documents.value = []
    agendaToday.value = []
  }
  finally {
    loading.value = false
  }
}

const actionBusy = ref<number | null>(null)
const quickComment = ref('')
const instructDialog = ref(false)
const instructDocId = ref<number | null>(null)
const users = ref<Array<{ id: number; name: string }>>([])
const instructForm = ref({
  assignee_id: null as number | null,
  title: 'Instruction DG',
  body: '',
  due_date: '',
})

const runQuickAction = async (docId: number, path: string, body: Record<string, unknown> = {}) => {
  actionBusy.value = docId
  try {
    await $api(`/parapheur/documents/${docId}/${path}`, {
      method: 'POST',
      body: {
        comment: quickComment.value || undefined,
        ...body,
      },
    })
    quickComment.value = ''
    await loadDashboard()
  }
  finally {
    actionBusy.value = null
  }
}

const openInstruct = (docId: number) => {
  instructDocId.value = docId
  instructForm.value = {
    assignee_id: null,
    title: 'Instruction DG',
    body: quickComment.value || '',
    due_date: '',
  }
  instructDialog.value = true
}

const submitInstruct = async () => {
  if (!instructDocId.value)
    return
  actionBusy.value = instructDocId.value
  try {
    await $api(`/parapheur/documents/${instructDocId.value}/instructions`, {
      method: 'POST',
      body: instructForm.value,
    })
    instructDialog.value = false
    quickComment.value = ''
    await loadDashboard()
  }
  finally {
    actionBusy.value = null
  }
}

onMounted(async () => {
  users.value = await $api('/meta/users').catch(() => [])
  await loadDashboard()
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="`${greeting}${userData?.fullName ? `, ${userData.fullName}` : ''}`"
      subtitle="Bureau du Directeur Général"
      icon="tabler-layout-dashboard"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadDashboard"
        >
          Actualiser
        </VBtn>
        <VBtn
          color="primary"
          :to="{ name: 'parapheur' }"
          prepend-icon="tabler-briefcase"
        >
          Mon parapheur
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="loadError"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="loadError = ''"
    >
      {{ loadError }}
    </VAlert>

    <VRow dense class="mb-4">
      <VCol
        v-for="card in kpiCards"
        :key="card.title"
        cols="6"
        sm="4"
        md="2"
      >
        <VCard class="dash-kpi">
          <VCardText class="d-flex align-center gap-3 pa-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="36"
            >
              <VIcon
                :icon="card.icon"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-h5 font-weight-semibold lh-1 mb-1">
                {{ card.value }}
              </div>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ card.title }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow dense class="match-height">
      <VCol
        cols="12"
        sm="4"
      >
        <VCard class="h-100">
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Instructions
            </VCardTitle>
          </VCardItem>
          <VCardText class="pt-3">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Ouvertes</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.instructions_open ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2">En retard</span>
              <span class="text-body-1 font-weight-medium text-error">{{ stats?.instructions_late ?? 0 }}</span>
            </div>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              block
              :to="{ name: 'parapheur-instructions' }"
            >
              Voir
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard class="h-100">
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Réunions
            </VCardTitle>
          </VCardItem>
          <VCardText class="pt-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Aujourd’hui</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.meetings_today ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Cette semaine</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.meetings_this_week ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Décisions ouvertes</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.meeting_decisions_open ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2">Décisions en retard</span>
              <span class="text-body-1 font-weight-medium text-error">{{ stats?.meeting_decisions_late ?? 0 }}</span>
            </div>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              block
              :to="{ name: 'parapheur-reunions' }"
            >
              Voir
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard class="h-100">
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Agenda
            </VCardTitle>
          </VCardItem>
          <VCardText class="pt-3">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">RDV aujourd’hui</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.appointments_today ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Audiences</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.audiences_today ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Prochain</span>
              <span class="text-body-1 font-weight-medium">{{ formatTime(stats?.appointments_next_at) }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2">À valider</span>
              <span class="text-body-1 font-weight-medium text-warning">{{ stats?.appointments_to_validate ?? 0 }}</span>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                block
                :to="{ name: 'parapheur-agenda' }"
              >
                Agenda
              </VBtn>
              <VBtn
                v-if="(stats?.appointments_to_validate || 0) > 0"
                size="small"
                color="warning"
                block
                :to="{ name: 'parapheur-agenda-avalider' }"
              >
                Valider
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Répartition par structure
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div
              v-if="!Object.keys(stats?.by_structure ?? {}).length"
              class="text-medium-emphasis py-6 text-center"
            >
              Aucune donnée de structure
            </div>
            <VueApexCharts
              v-else
              type="bar"
              height="220"
              :options="structureChartOptions"
              :series="structureSeries"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard class="h-100">
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              États des dossiers
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div
              v-if="!statusBreakdown.length"
              class="text-medium-emphasis text-center py-4"
            >
              Aucun statut
            </div>
            <div
              v-for="item in statusBreakdown"
              :key="item.status"
              class="mb-3"
            >
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-body-2">{{ item.label }}</span>
                <span class="text-caption font-weight-medium">{{ item.count }} · {{ item.percent }}%</span>
              </div>
              <VProgressLinear
                :model-value="item.percent"
                :color="statusColor(item.status)"
                height="4"
                rounded
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="text-subtitle-1">
              À traiter
            </VCardTitle>
            <VCardSubtitle>Actions rapides</VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <AppTextarea
              v-model="quickComment"
              label="Commentaire / motif"
              rows="2"
              class="mb-3"
            />

            <div
              v-if="!documents.length"
              class="text-center text-medium-emphasis py-6"
            >
              Aucun document à traiter
            </div>

            <div
              v-for="doc in documents.slice(0, 10)"
              :key="doc.id"
              class="dash-doc-row d-flex flex-wrap align-center justify-space-between gap-2 py-3"
            >
              <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-center gap-2 mb-1">
                  <span class="font-weight-medium text-truncate">{{ doc.object }}</span>
                  <VChip
                    size="x-small"
                    label
                    :color="priorityColor(doc.priority)"
                  >
                    {{ priorityLabel(doc.priority) }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ doc.reference || 'Sans référence' }}
                  · {{ doc.structure?.code || '—' }}
                  · {{ actionLabel(doc.expected_action) }}
                </div>
              </div>

              <div class="d-flex align-center gap-1 flex-shrink-0">
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      :loading="actionBusy === doc.id"
                      @click="runQuickAction(doc.id, 'comments', { body: quickComment || 'Prise de connaissance DG', kind: 'observation' })"
                    >
                      <VIcon icon="tabler-message" />
                    </IconBtn>
                  </template>
                  <span>Commenter</span>
                </VTooltip>
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      color="warning"
                      :loading="actionBusy === doc.id"
                      @click="runQuickAction(doc.id, 'return')"
                    >
                      <VIcon icon="tabler-arrow-back-up" />
                    </IconBtn>
                  </template>
                  <span>Retourner</span>
                </VTooltip>
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      color="success"
                      :loading="actionBusy === doc.id"
                      @click="runQuickAction(doc.id, 'validate')"
                    >
                      <VIcon icon="tabler-circle-check" />
                    </IconBtn>
                  </template>
                  <span>Valider</span>
                </VTooltip>
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      color="info"
                      :loading="actionBusy === doc.id"
                      @click="runQuickAction(doc.id, 'vise')"
                    >
                      <VIcon icon="tabler-stamp" />
                    </IconBtn>
                  </template>
                  <span>Viser</span>
                </VTooltip>
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      color="primary"
                      @click="openInstruct(doc.id)"
                    >
                      <VIcon icon="tabler-list-check" />
                    </IconBtn>
                  </template>
                  <span>Instruire</span>
                </VTooltip>
                <VTooltip location="top">
                  <template #activator="{ props: tip }">
                    <IconBtn
                      v-bind="tip"
                      :to="{ name: 'parapheur-id', params: { id: doc.id } }"
                    >
                      <VIcon icon="tabler-eye" />
                    </IconBtn>
                  </template>
                  <span>Ouvrir</span>
                </VTooltip>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle class="text-subtitle-1">
              Agenda du jour
            </VCardTitle>
            <template #append>
              <IconBtn :to="{ name: 'parapheur-agenda-calendrier' }">
                <VIcon icon="tabler-calendar" />
              </IconBtn>
            </template>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div
              v-if="!agendaToday.length"
              class="text-medium-emphasis py-4"
            >
              Aucun rendez-vous aujourd’hui
            </div>
            <div
              v-for="item in agendaToday"
              :key="item.id"
              class="dash-doc-row py-3"
            >
              <div class="d-flex align-start gap-3">
                <div class="text-body-2 font-weight-bold text-primary"
                     style="min-inline-size: 3rem"
                >
                  {{ formatTime(item.start_at) }}
                </div>
                <div class="min-w-0 flex-grow-1">
                  <RouterLink
                    class="text-body-2 font-weight-medium text-high-emphasis text-decoration-none"
                    :to="{ name: 'parapheur-agenda-id', params: { id: item.id } }"
                  >
                    {{ item.subject }}
                  </RouterLink>
                  <div class="text-caption text-medium-emphasis">
                    {{ item.requester_name || item.requester_organization || '—' }}
                    <span v-if="item.duration_minutes"> · {{ item.duration_minutes }} min</span>
                  </div>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="instructDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Instruction DG</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="instructForm.title"
            label="Titre"
            class="mb-3"
          />
          <AppTextarea
            v-model="instructForm.body"
            label="Instruction"
            class="mb-3"
          />
          <UserAutocomplete
            v-model="instructForm.assignee_id"
            :items="users"
            label="Responsable"
            class="mb-3"
          />
          <AppTextField
            v-model="instructForm.due_date"
            type="date"
            label="Échéance"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="instructDialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="actionBusy !== null"
            @click="submitInstruct"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart";

.dash-kpi {
  .v-card-text {
    min-block-size: 4.25rem;
  }
}

.dash-doc-row + .dash-doc-row {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
