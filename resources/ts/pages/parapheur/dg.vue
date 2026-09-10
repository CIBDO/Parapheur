<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

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
}

const vuetifyTheme = useTheme()
const userData = useCookie<any>('userData')

const stats = ref<DgStats | null>(null)
const documents = ref<DocItem[]>([])
const loading = ref(true)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12)
    return 'Bonjour'
  if (hour < 18)
    return 'Bon après-midi'

  return 'Bonsoir'
})

const kpiCards = computed(() => {
  if (!stats.value)
    return []

  return [
    {
      title: 'À traiter',
      value: stats.value.to_process,
      subtitle: 'En attente d’action',
      icon: 'tabler-inbox',
      color: 'primary',
    },
    {
      title: 'Urgents',
      value: stats.value.urgent,
      subtitle: 'Priorité haute',
      icon: 'tabler-alert-triangle',
      color: 'error',
    },
    {
      title: 'En retard',
      value: stats.value.overdue,
      subtitle: 'Échéance dépassée',
      icon: 'tabler-clock-exclamation',
      color: 'warning',
    },
    {
      title: 'Validés',
      value: stats.value.validated,
      subtitle: 'Dossiers clos',
      icon: 'tabler-circle-check',
      color: 'success',
    },
  ]
})

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
        borderRadius: 6,
        columnWidth: '32%',
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
      strokeDashArray: 6,
      borderColor: `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`,
      xaxis: { lines: { show: false } },
      padding: { top: -10, left: -10, right: -10, bottom: -10 },
    },
    dataLabels: { enabled: false },
    legend: { show: false },
    tooltip: { enabled: true },
    xaxis: {
      categories,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: { colors: labelColor, fontSize: '13px' },
      },
    },
    yaxis: {
      labels: {
        style: { colors: labelColor, fontSize: '13px' },
      },
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

const sparkSeries = computed(() => [{
  name: 'Volume',
  data: Object.values(stats.value?.by_status ?? { a: 2, b: 4, c: 3, d: 6, e: 5, f: 8, g: 4 }),
}])

const sparkOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'area',
      sparkline: { enabled: true },
      toolbar: { show: false },
    },
    stroke: { curve: 'smooth', width: 2 },
    fill: {
      type: 'gradient',
      gradient: { opacityFrom: 0.45, opacityTo: 0.05 },
    },
    colors: [currentTheme.primary],
    tooltip: { enabled: false },
  }
})

const loadError = ref('')

const loadDashboard = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [statsRes, docsRes] = await Promise.all([
      $api('/dashboard/dg'),
      $api('/parapheur/documents', { query: { folder: 'a_traiter' } }),
    ])

    stats.value = statsRes
    documents.value = docsRes.data ?? docsRes
  }
  catch (e: any) {
    loadError.value = e?.data?.message || e?.message || 'Impossible de charger le bureau DG'
    stats.value = null
    documents.value = []
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
      subtitle="Bureau du Directeur Général — vue orientée action"
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
      class="mb-6"
      closable
      @click:close="loadError = ''"
    >
      {{ loadError }}
    </VAlert>

    <VRow class="match-height">
      <!-- KPI cards style CRM -->
      <VCol
        v-for="card in kpiCards"
        :key="card.title"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <VAvatar
                :color="card.color"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  :icon="card.icon"
                  size="26"
                />
              </VAvatar>
              <VChip
                v-if="card.color === 'error' && card.value > 0"
                :color="card.color"
                label
                size="small"
              >
                Action
              </VChip>
            </div>

            <h5 class="text-h5">
              {{ card.title }}
            </h5>
            <p class="mb-1 text-medium-emphasis">
              {{ card.subtitle }}
            </p>
            <p class="text-h4 mb-0 text-high-emphasis">
              {{ card.value }}
            </p>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Mini volume -->
      <VCol
        cols="12"
        md="4"
        sm="6"
      >
        <VCard>
          <VCardItem class="pb-2">
            <VCardTitle>Volume reçu</VCardTitle>
            <VCardSubtitle>Dossiers soumis</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              v-if="stats"
              :options="sparkOptions"
              :series="sparkSeries"
              :height="72"
            />
            <div class="d-flex align-center justify-space-between mt-3">
              <h4 class="text-h4 mb-0">
                {{ stats?.received ?? '—' }}
              </h4>
              <span class="text-sm text-success">Circuit actif</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Instructions -->
      <VCol
        cols="12"
        md="4"
        sm="6"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <div>
                <VCardTitle class="pa-0 mb-1">
                  Instructions
                </VCardTitle>
                <VCardSubtitle class="pa-0">
                  Suivi des consignes
                </VCardSubtitle>
              </div>
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-list-check"
                  size="26"
                />
              </VAvatar>
            </div>

            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Ouvertes</span>
              <span class="text-h5">{{ stats?.instructions_open ?? 0 }}</span>
            </div>
            <VProgressLinear
              :model-value="stats ? Math.min(100, (stats.instructions_open || 0) * 10) : 0"
              color="info"
              height="8"
              rounded
              class="mb-4"
            />

            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">En retard</span>
              <span class="text-h5 text-error">{{ stats?.instructions_late ?? 0 }}</span>
            </div>
            <VProgressLinear
              :model-value="stats ? Math.min(100, (stats.instructions_late || 0) * 20) : 0"
              color="error"
              height="8"
              rounded
              class="mb-4"
            />

            <VBtn
              block
              variant="tonal"
              color="primary"
              :to="{ name: 'parapheur-instructions' }"
            >
              Voir les instructions
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
        sm="6"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <div>
                <VCardTitle class="pa-0 mb-1">
                  Réunions
                </VCardTitle>
                <VCardSubtitle class="pa-0">
                  Séances et décisions
                </VCardSubtitle>
              </div>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-users-group"
                  size="26"
                />
              </VAvatar>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Aujourd’hui</span>
              <span class="text-h5">{{ stats?.meetings_today ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Cette semaine</span>
              <span class="text-h5">{{ stats?.meetings_this_week ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2">Décisions en cours</span>
              <span class="text-h5">{{ stats?.meeting_decisions_open ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-body-2">Décisions en retard</span>
              <span class="text-h5 text-error">{{ stats?.meeting_decisions_late ?? 0 }}</span>
            </div>
            <VBtn
              block
              variant="tonal"
              color="primary"
              :to="{ name: 'parapheur-reunions' }"
            >
              Ouvrir les réunions
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Retours / validés -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-6">
              <div>
                <h5 class="text-h5 mb-1">
                  Synthèse décisionnelle
                </h5>
                <p class="mb-0 text-medium-emphasis">
                  Validations et retours
                </p>
              </div>
            </div>

            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="success"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon icon="tabler-checks" />
                </VAvatar>
                <div>
                  <div class="text-body-1 font-weight-medium">
                    Validés
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Décisions favorables
                  </div>
                </div>
              </div>
              <div class="text-h5 text-success">
                {{ stats?.validated ?? 0 }}
              </div>
            </div>

            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="error"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon icon="tabler-arrow-back-up" />
                </VAvatar>
                <div>
                  <div class="text-body-1 font-weight-medium">
                    Retournés
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    À corriger
                  </div>
                </div>
              </div>
              <div class="text-h5 text-error">
                {{ stats?.returned ?? 0 }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Répartition structures -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Répartition par structure</VCardTitle>
            <VCardSubtitle>Volume de dossiers par direction</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div
              v-if="!Object.keys(stats?.by_structure ?? {}).length"
              class="text-medium-emphasis py-8 text-center"
            >
              Aucune donnée de structure pour le moment
            </div>
            <VueApexCharts
              v-else
              type="bar"
              height="280"
              :options="structureChartOptions"
              :series="structureSeries"
            />
          </VCardText>
        </VCard>
      </VCol>

      <!-- Statuts -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard title="États des dossiers">
          <VCardText>
            <div
              v-if="!statusBreakdown.length"
              class="text-medium-emphasis text-center py-6"
            >
              Aucun statut disponible
            </div>

            <div
              v-for="item in statusBreakdown"
              :key="item.status"
              class="mb-4"
            >
              <div class="d-flex align-center justify-space-between mb-1">
                <div class="d-flex align-center gap-2">
                  <VChip
                    :color="statusColor(item.status)"
                    size="small"
                    label
                  >
                    {{ item.label }}
                  </VChip>
                </div>
                <span class="text-body-2 font-weight-medium">
                  {{ item.count }} · {{ item.percent }}%
                </span>
              </div>
              <VProgressLinear
                :model-value="item.percent"
                :color="statusColor(item.status)"
                height="6"
                rounded
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Documents à traiter — UI tablette -->
      <VCol cols="12">
        <VCard>
          <VCardItem>
            <VCardTitle>Documents à traiter</VCardTitle>
            <VCardSubtitle>Actions rapides DG — Commenter · Retourner · Valider · Instruire · Viser</VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <AppTextarea
              v-model="quickComment"
              label="Commentaire / motif (utilisé par les actions)"
              rows="2"
              class="mb-4"
            />

            <div
              v-if="!documents.length"
              class="text-center text-medium-emphasis py-8"
            >
              Aucun document à traiter
            </div>

            <div
              v-for="doc in documents.slice(0, 12)"
              :key="doc.id"
              class="pa-4 mb-3 rounded border"
            >
              <div class="d-flex flex-wrap justify-space-between gap-3 mb-3">
                <div class="min-w-0">
                  <div class="text-h6 text-truncate">
                    {{ doc.object }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ doc.reference || 'Sans référence' }}
                    · {{ doc.structure?.code || '—' }}
                    · {{ actionLabel(doc.expected_action) }}
                  </div>
                </div>
                <VChip
                  size="small"
                  label
                  :color="priorityColor(doc.priority)"
                >
                  {{ priorityLabel(doc.priority) }}
                </VChip>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <VBtn
                  size="small"
                  :loading="actionBusy === doc.id"
                  @click="runQuickAction(doc.id, 'comments', { body: quickComment || 'Prise de connaissance DG', kind: 'observation' })"
                >
                  Commenter
                </VBtn>
                <VBtn
                  size="small"
                  color="warning"
                  :loading="actionBusy === doc.id"
                  @click="runQuickAction(doc.id, 'return')"
                >
                  Retourner
                </VBtn>
                <VBtn
                  size="small"
                  color="success"
                  :loading="actionBusy === doc.id"
                  @click="runQuickAction(doc.id, 'validate')"
                >
                  Valider
                </VBtn>
                <VBtn
                  size="small"
                  color="info"
                  :loading="actionBusy === doc.id"
                  @click="runQuickAction(doc.id, 'vise')"
                >
                  Viser
                </VBtn>
                <VBtn
                  size="small"
                  color="primary"
                  variant="tonal"
                  @click="openInstruct(doc.id)"
                >
                  Instruire
                </VBtn>
                <VBtn
                  size="small"
                  variant="text"
                  :to="{ name: 'parapheur-id', params: { id: doc.id } }"
                >
                  Ouvrir
                </VBtn>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Ancienne liste courte conservée en synthèse -->
      <VCol
        cols="12"
        md="7"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>File de priorité</VCardTitle>
            <VCardSubtitle>Synthèse des dossiers en attente</VCardSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="text"
                color="primary"
                :to="{ name: 'parapheur', query: { folder: 'a_traiter' } }"
              >
                Tout voir
              </VBtn>
            </template>
          </VCardItem>

          <VDivider />

          <VTable class="text-no-wrap">
            <thead>
              <tr>
                <th>DOCUMENT</th>
                <th>ACTION</th>
                <th>PRIORITÉ</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-if="!documents.length">
                <td
                  colspan="4"
                  class="text-center text-medium-emphasis py-8"
                >
                  Aucun document à traiter
                </td>
              </tr>
              <tr
                v-for="doc in documents.slice(0, 8)"
                :key="doc.id"
              >
                <td style="padding-block: 1rem;">
                  <div class="d-flex align-center gap-3">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="38"
                    >
                      <VIcon icon="tabler-file-text" />
                    </VAvatar>
                    <div class="min-w-0">
                      <div class="font-weight-medium text-truncate">
                        {{ doc.structure?.code || '—' }} — {{ doc.object }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ doc.reference || doc.type?.name || 'Sans référence' }}
                        <span v-if="doc.author"> · {{ doc.author.name }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="text-body-2">{{ actionLabel(doc.expected_action) }}</span>
                </td>
                <td>
                  <VChip
                    size="small"
                    label
                    :color="priorityColor(doc.priority)"
                  >
                    {{ priorityLabel(doc.priority) }}
                  </VChip>
                </td>
                <td class="text-end">
                  <VBtn
                    size="small"
                    color="primary"
                    variant="tonal"
                    :to="{ name: 'parapheur-id', params: { id: doc.id } }"
                  >
                    Ouvrir
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>

      <!-- Timeline activité -->
      <VCol
        cols="12"
        md="5"
      >
        <VCard>
          <VCardItem>
            <template #prepend>
              <VIcon
                icon="tabler-list-details"
                size="20"
                class="me-1"
              />
            </template>
            <VCardTitle>Activité récente</VCardTitle>
          </VCardItem>

          <VCardText>
            <VTimeline
              v-if="documents.length"
              side="end"
              align="start"
              line-inset="8"
              truncate-line="start"
              density="compact"
            >
              <VTimelineItem
                v-for="(doc, index) in documents.slice(0, 5)"
                :key="doc.id"
                size="x-small"
                :dot-color="priorityColor(doc.priority) === 'secondary' ? 'primary' : priorityColor(doc.priority)"
              >
                <div class="d-flex justify-space-between align-center gap-2 flex-wrap mb-1">
                  <span class="app-timeline-title">
                    {{ doc.structure?.code || 'Dossier' }} — {{ actionLabel(doc.expected_action) }}
                  </span>
                  <span class="app-timeline-meta">#{{ index + 1 }}</span>
                </div>
                <div class="app-timeline-text">
                  {{ doc.object }}
                </div>
                <div class="mt-2">
                  <VChip
                    size="x-small"
                    label
                    :color="priorityColor(doc.priority)"
                  >
                    {{ priorityLabel(doc.priority) }}
                  </VChip>
                </div>
              </VTimelineItem>
            </VTimeline>

            <div
              v-else
              class="text-medium-emphasis text-center py-8"
            >
              Pas d’activité récente à afficher
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
          <AppSelect
            v-model="instructForm.assignee_id"
            :items="users"
            item-title="name"
            item-value="id"
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
</style>
