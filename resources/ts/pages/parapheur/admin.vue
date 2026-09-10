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
    title: 'Reçus',
    value: stats.value?.received ?? 0,
    icon: 'tabler-files',
    color: 'primary',
  },
  {
    title: 'En cours',
    value: stats.value?.to_process ?? 0,
    icon: 'tabler-loader',
    color: 'info',
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
    title: 'Utilisateurs',
    value: users.value.length,
    icon: 'tabler-users',
    color: 'secondary',
  },
])

const adminLinks = [
  { title: 'Structures', icon: 'tabler-building', to: { name: 'parapheur-structures' } },
  { title: 'Utilisateurs', icon: 'tabler-users', to: { name: 'parapheur-users' } },
  { title: 'Rôles', icon: 'tabler-shield', to: { name: 'parapheur-roles' } },
  { title: 'Types', icon: 'tabler-file-type', to: { name: 'parapheur-document-types' } },
  { title: 'Audit', icon: 'tabler-history', to: { name: 'parapheur-audit' } },
  { title: 'Parapheur', icon: 'tabler-briefcase', to: { name: 'parapheur' } },
]

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

const roleBreakdown = computed(() => {
  const map = new Map<string, number>()

  for (const user of users.value) {
    const role = String(user.role || user.roles?.[0] || 'Agent')
    map.set(role, (map.get(role) || 0) + 1)
  }

  return [...map.entries()]
    .map(([role, count]) => ({ role, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 6)
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
      subtitle="Pilotage global du parapheur"
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

    <VRow
      dense
      class="match-height mb-4"
    >
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Administration
            </VCardTitle>
            <VCardSubtitle>
              {{ structures.length }} structures · {{ users.length }} comptes
            </VCardSubtitle>
          </VCardItem>
          <VCardText class="pt-3">
            <div class="d-flex flex-wrap gap-2">
              <VBtn
                v-for="link in adminLinks"
                :key="link.title"
                size="small"
                variant="tonal"
                color="primary"
                :prepend-icon="link.icon"
                :to="link.to"
              >
                {{ link.title }}
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard>
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Décisions
            </VCardTitle>
          </VCardItem>
          <VCardText class="pt-3">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Validés</span>
              <span class="text-body-1 font-weight-medium text-success">{{ stats?.validated ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Retournés</span>
              <span class="text-body-1 font-weight-medium text-error">{{ stats?.returned ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Instructions ouvertes</span>
              <span class="text-body-1 font-weight-medium">{{ stats?.instructions_open ?? 0 }}</span>
            </div>
            <div class="d-flex align-center justify-space-between">
              <span class="text-body-2">Instructions en retard</span>
              <span class="text-body-1 font-weight-medium text-error">{{ stats?.instructions_late ?? 0 }}</span>
            </div>
            <VBtn
              class="mt-4"
              size="small"
              variant="tonal"
              color="primary"
              block
              :to="{ name: 'parapheur-instructions' }"
            >
              Instructions
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="4"
      >
        <VCard>
          <VCardItem class="pb-0">
            <VCardTitle class="text-subtitle-1">
              Direction
            </VCardTitle>
          </VCardItem>
          <VCardText
            v-if="direction"
            class="pt-3"
          >
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Préparés</span>
              <span class="text-body-1 font-weight-medium">{{ direction.prepared }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">En validation</span>
              <span class="text-body-1 font-weight-medium">{{ direction.in_validation }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Transmis DG</span>
              <span class="text-body-1 font-weight-medium">{{ direction.sent_dg }}</span>
            </div>
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-body-2">Retournés</span>
              <span class="text-body-1 font-weight-medium text-error">{{ direction.returned }}</span>
            </div>
            <div class="d-flex align-center justify-space-between">
              <span class="text-body-2">Validés</span>
              <span class="text-body-1 font-weight-medium text-success">{{ direction.validated }}</span>
            </div>
          </VCardText>
          <VCardText
            v-else
            class="text-medium-emphasis"
          >
            Indicateurs indisponibles
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow dense class="mb-4">
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
        <div class="d-flex flex-column gap-4">
          <VCard>
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

          <VCard>
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1">
                Rôles
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <div
                v-for="item in roleBreakdown"
                :key="item.role"
                class="d-flex align-center justify-space-between mb-2"
              >
                <span class="text-body-2 text-truncate me-2">{{ item.role }}</span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ item.count }}
                </VChip>
              </div>
            </VCardText>
          </VCard>
        </div>
      </VCol>
    </VRow>

    <VRow dense>
      <VCol cols="12">
        <VCard>
          <VCardItem>
            <VCardTitle class="text-subtitle-1">
              Dossiers récents
            </VCardTitle>
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
          <VTable
            density="compact"
            class="text-no-wrap"
          >
            <thead>
              <tr>
                <th>Document</th>
                <th>Action</th>
                <th>Priorité</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-if="!documents.length">
                <td
                  colspan="4"
                  class="text-center text-medium-emphasis py-6"
                >
                  Aucun dossier
                </td>
              </tr>
              <tr
                v-for="doc in documents.slice(0, 8)"
                :key="doc.id"
              >
                <td>
                  <div
                    class="font-weight-medium text-truncate"
                    style="max-inline-size: 28rem"
                  >
                    {{ doc.structure?.code || '—' }} — {{ doc.object }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ doc.reference || doc.type?.name || 'Sans référence' }}
                    <span v-if="doc.author"> · {{ doc.author.name }}</span>
                  </div>
                </td>
                <td>
                  <span class="text-body-2">{{ actionLabel(doc.expected_action) }}</span>
                </td>
                <td>
                  <VChip
                    size="x-small"
                    label
                    :color="priorityColor(doc.priority)"
                  >
                    {{ priorityLabel(doc.priority) }}
                  </VChip>
                </td>
                <td class="text-end">
                  <IconBtn
                    :to="{ name: 'parapheur-id', params: { id: doc.id } }"
                  >
                    <VIcon icon="tabler-eye" />
                  </IconBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart";

.dash-kpi {
  .v-card-text {
    min-block-size: 4.25rem;
  }
}
</style>
