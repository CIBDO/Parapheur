<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'all',
  },
})

interface DocItem {
  id: number
  reference?: string
  object: string
  status: string
  priority: string
  expected_action: string
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
}

const vuetifyTheme = useTheme()
const userData = useCookie<any>('userData')

const stats = ref<DgStats | null>(null)
const direction = ref<any>(null)
const documents = ref<DocItem[]>([])
const users = ref<any[]>([])
const structures = ref<any[]>([])
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
    title: 'Dossiers reçus',
    value: stats.value?.received ?? 0,
    subtitle: 'Tous circuits',
    icon: 'tabler-files',
    color: 'primary',
  },
  {
    title: 'En traitement',
    value: stats.value?.to_process ?? 0,
    subtitle: 'Circuits ouverts',
    icon: 'tabler-loader',
    color: 'info',
  },
  {
    title: 'Urgents',
    value: stats.value?.urgent ?? 0,
    subtitle: 'Priorité haute',
    icon: 'tabler-alert-triangle',
    color: 'error',
  },
  {
    title: 'Utilisateurs',
    value: users.value.length,
    subtitle: 'Comptes actifs',
    icon: 'tabler-users',
    color: 'success',
  },
])

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
  en_attente: 'En attente',
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
    avis: 'Avis',
    observations: 'Observations',
    instruction: 'Instruction',
  }

  return map[action] ?? action
}

const statusColor = (status: string) => {
  if (['valide', 'traite', 'archive'].includes(status))
    return 'success'
  if (['a_corriger', 'rejete'].includes(status))
    return 'error'
  if (['a_valider', 'a_viser', 'en_attente'].includes(status))
    return 'warning'
  if (['transmis', 'a_consulter', 'en_circuit'].includes(status))
    return 'primary'

  return 'secondary'
}

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
      labels: { style: { colors: labelColor, fontSize: '13px' } },
    },
    yaxis: {
      labels: { style: { colors: labelColor, fontSize: '13px' } },
    },
  }
})

const roleBreakdown = computed(() => {
  const map = new Map<string, number>()

  for (const user of users.value) {
    const role = String(user.role || user.roles?.[0] || 'Agent')
    map.set(role, (map.get(role) || 0) + 1)
  }

  return [...map.entries()]
    .map(([role, count]) => ({ role, count }))
    .sort((a, b) => b.count - a.count)
})

const loadDashboard = async () => {
  loading.value = true
  try {
    const [statsRes, docsRes, usersRes, structuresRes, directionRes] = await Promise.all([
      $api('/dashboard/dg'),
      $api('/parapheur/documents'),
      $api('/meta/users'),
      $api('/meta/structures'),
      $api('/dashboard/direction').catch(() => null),
    ])

    stats.value = statsRes
    documents.value = docsRes.data ?? docsRes
    users.value = Array.isArray(usersRes) ? usersRes : (usersRes.data ?? [])
    structures.value = Array.isArray(structuresRes) ? structuresRes : (structuresRes.data ?? [])
    direction.value = directionRes
  }
  finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="`${greeting}${userData?.fullName ? `, ${userData.fullName}` : ''}`"
      subtitle="Tableau de bord administrateur — pilotage global du parapheur"
      icon="tabler-shield-cog"
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
          prepend-icon="tabler-file-plus"
          :to="{ name: 'parapheur-nouveau' }"
        >
          Nouveau document
        </VBtn>
      </template>
    </ParapheurPageHeader>


    <VRow class="match-height">
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
                color="error"
                label
                size="small"
              >
                Alerte
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

      <!-- Organisation -->
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
                  Organisation
                </VCardTitle>
                <VCardSubtitle class="pa-0">
                  Structures & comptes
                </VCardSubtitle>
              </div>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-building"
                  size="26"
                />
              </VAvatar>
            </div>

            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Structures</span>
              <span class="text-h5">{{ structures.length }}</span>
            </div>
            <VProgressLinear
              :model-value="Math.min(100, structures.length * 12)"
              color="primary"
              height="8"
              rounded
              class="mb-4"
            />

            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Utilisateurs</span>
              <span class="text-h5">{{ users.length }}</span>
            </div>
            <VProgressLinear
              :model-value="Math.min(100, users.length * 10)"
              color="success"
              height="8"
              rounded
              class="mb-4"
            />

            <VBtn
              block
              variant="tonal"
              color="primary"
              class="mb-2"
              :to="{ name: 'parapheur-structures' }"
            >
              Gérer les structures
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="mb-2"
              :to="{ name: 'parapheur-users' }"
            >
              Gérer les utilisateurs
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="mb-2"
              :to="{ name: 'parapheur-roles' }"
            >
              Rôles & permissions
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="mb-2"
              :to="{ name: 'parapheur-document-types' }"
            >
              Types de documents
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="primary"
              class="mb-2"
              :to="{ name: 'parapheur-audit' }"
            >
              Journal d’audit
            </VBtn>
            <VBtn
              block
              variant="tonal"
              color="primary"
              :to="{ name: 'parapheur' }"
            >
              Ouvrir mon parapheur
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Décisions -->
      <VCol
        cols="12"
        md="4"
        sm="6"
      >
        <VCard>
          <VCardText>
            <h5 class="text-h5 mb-1">
              Synthèse décisionnelle
            </h5>
            <p class="mb-6 text-medium-emphasis">
              Validations, retours et retards
            </p>

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
                    Dossiers clos
                  </div>
                </div>
              </div>
              <div class="text-h5 text-success">
                {{ stats?.validated ?? 0 }}
              </div>
            </div>

            <div class="d-flex align-center justify-space-between mb-4">
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

            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="warning"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon icon="tabler-clock-exclamation" />
                </VAvatar>
                <div>
                  <div class="text-body-1 font-weight-medium">
                    En retard
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Échéance dépassée
                  </div>
                </div>
              </div>
              <div class="text-h5 text-warning">
                {{ stats?.overdue ?? 0 }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Instructions -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <div>
                <VCardTitle class="pa-0 mb-1">
                  Instructions
                </VCardTitle>
                <VCardSubtitle class="pa-0">
                  Consignes transverses
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
              :model-value="Math.min(100, (stats?.instructions_open || 0) * 10)"
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
              :model-value="Math.min(100, (stats?.instructions_late || 0) * 20)"
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
              Gérer les instructions
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Chart structures -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Répartition par structure</VCardTitle>
            <VCardSubtitle>Volume global des dossiers</VCardSubtitle>
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

      <!-- Statuts + rôles -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          title="États des dossiers"
          class="mb-6"
        >
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
                <VChip
                  :color="statusColor(item.status)"
                  size="small"
                  label
                >
                  {{ item.label }}
                </VChip>
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

        <VCard title="Répartition des rôles">
          <VCardText>
            <div
              v-for="item in roleBreakdown"
              :key="item.role"
              class="d-flex align-center justify-space-between mb-3"
            >
              <span class="text-body-2">{{ item.role }}</span>
              <VChip
                size="small"
                color="primary"
                variant="tonal"
                label
              >
                {{ item.count }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Documents récents -->
      <VCol
        cols="12"
        md="7"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Dossiers en cours</VCardTitle>
            <VCardSubtitle>Vue transverse administrateur</VCardSubtitle>
            <template #append>
              <VBtn
                size="small"
                variant="text"
                color="primary"
                :to="{ name: 'parapheur' }"
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
                  Aucun dossier en cours
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

      <!-- Direction + timeline -->
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle>Indicateurs direction</VCardTitle>
            <VCardSubtitle>Structure de l’administrateur</VCardSubtitle>
          </VCardItem>
          <VCardText v-if="direction">
            <div class="d-flex flex-wrap gap-3">
              <VChip
                label
                variant="tonal"
                color="primary"
              >
                Préparés : {{ direction.prepared }}
              </VChip>
              <VChip
                label
                variant="tonal"
                color="info"
              >
                En validation : {{ direction.in_validation }}
              </VChip>
              <VChip
                label
                variant="tonal"
                color="warning"
              >
                Transmis DG : {{ direction.sent_dg }}
              </VChip>
              <VChip
                label
                variant="tonal"
                color="error"
              >
                Retournés : {{ direction.returned }}
              </VChip>
              <VChip
                label
                variant="tonal"
                color="success"
              >
                Validés : {{ direction.validated }}
              </VChip>
            </div>
          </VCardText>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            Indicateurs direction indisponibles
          </VCardText>
        </VCard>

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
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart";
</style>
