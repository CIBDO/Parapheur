<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useProductions } from '@/composables/useProductions'
import { formatTaskDue, productionStatusColor, productionStatusLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const router = useRouter()
const ability = useAbility()
const { loadMyDashboard, listOccurrences } = useProductions()
const loading = ref(false)
const errorMessage = ref('')
const stats = ref<Record<string, number>>({})
const recent = ref<any[]>([])

const canManageModels = computed(() =>
  ability.can('manage', 'Production') || ability.can('manage', 'all'),
)
const canPilot = computed(() =>
  ability.can('pilot', 'Production') || ability.can('manage', 'Production') || ability.can('manage', 'all'),
)

const cards = [
  {
    key: 'expected',
    title: 'À produire',
    hint: 'À déposer ou compléter',
    color: 'warning',
    icon: 'tabler-file-time',
    to: '/productions/occurrences?view=mine',
  },
  {
    key: 'overdue',
    title: 'En retard',
    hint: 'Échéance dépassée',
    color: 'error',
    icon: 'tabler-alert-triangle',
    to: '/productions/occurrences?view=overdue',
  },
  {
    key: 'to_transmit',
    title: 'Prêtes à envoyer',
    hint: 'Fichier déposé',
    color: 'info',
    icon: 'tabler-send',
    to: '/productions/occurrences?view=transmit',
  },
  {
    key: 'transmitted',
    title: 'Envoyées',
    hint: 'Sur 7 jours',
    color: 'success',
    icon: 'tabler-circle-check',
    to: '/productions/occurrences?view=mine',
  },
]

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const [dashboard, list] = await Promise.all([
      loadMyDashboard(),
      listOccurrences({ mine: 1, per_page: 8 }),
    ])
    stats.value = dashboard
    recent.value = list.data ?? list
  }
  catch (error: any) {
    stats.value = {}
    recent.value = []
    errorMessage.value = error?.data?.message || 'Impossible de charger vos productions.'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Aujourd’hui"
      subtitle="Déposez et envoyez vos situations aux destinataires"
      icon="tabler-calendar-event"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-list"
          @click="router.push('/productions/occurrences?view=mine')"
        >
          À produire
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-inbox"
          @click="router.push('/productions/recues')"
        >
          Reçues
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-folder"
          @click="router.push('/productions/dossier-du-jour')"
        >
          Dossier du jour
        </VBtn>
        <VBtn
          v-if="canManageModels"
          variant="tonal"
          prepend-icon="tabler-settings"
          @click="router.push('/productions/obligations')"
        >
          Modèles d’envoi
        </VBtn>
        <VBtn
          v-if="canPilot"
          variant="tonal"
          prepend-icon="tabler-chart-dots"
          @click="router.push('/productions/direction')"
        >
          Suivi
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

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <div
      v-if="loading && !Object.keys(stats).length"
      class="text-center py-8"
    >
      <VProgressCircular indeterminate />
    </div>
    <VRow
      v-else
      dense
      class="mb-4"
    >
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="12"
        sm="6"
        lg="4"
      >
        <VCard
          class="cursor-pointer h-100"
          @click="router.push(card.to)"
        >
          <VCardText class="d-flex align-center gap-4 pa-4">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="48"
            >
              <VIcon
                :icon="card.icon"
                size="26"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-h4 font-weight-semibold lh-1 mb-1">
                {{ stats[card.key] ?? 0 }}
              </div>
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ card.title }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ card.hint }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardItem>
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="36"
          >
            <VIcon
              icon="tabler-calendar-event"
              size="20"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6">
          Mes productions
        </VCardTitle>
        <VCardSubtitle>
          Occurrences dont vous êtes responsable
        </VCardSubtitle>
        <template #append>
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            to="/productions/occurrences"
          >
            Voir toutes
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VList
        v-if="recent.length"
        lines="two"
      >
        <VListItem
          v-for="item in recent"
          :key="item.id"
          :title="item.obligation?.label || item.reference"
          :subtitle="item.reference"
          class="cursor-pointer"
          @click="router.push(`/productions/occurrences/${item.id}`)"
        >
          <template #append>
            <div class="text-end">
              <VChip
                size="small"
                :color="productionStatusColor(item.status)"
                variant="tonal"
                class="mb-1"
              >
                {{ productionStatusLabels[item.status] || item.status }}
              </VChip>
              <div class="text-caption">
                {{ formatTaskDue(item.deadline_at) }}
              </div>
            </div>
          </template>
        </VListItem>
      </VList>
      <div
        v-else-if="!loading"
        class="text-center py-10 text-medium-emphasis"
      >
        <VIcon
          icon="tabler-file-off"
          size="40"
          class="mb-2"
        />
        <div>Aucune production en cours</div>
      </div>
      <div
        v-else
        class="text-center py-10"
      >
        <VProgressCircular
          indeterminate
          size="32"
        />
      </div>
    </VCard>
  </div>
</template>
