<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketing } from '@/composables/useTicketing'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Ticketing',
  },
})

const router = useRouter()
const { fetchDashboardRequester, fetchDashboardAgent } = useTicketing()

const loading = ref(true)
const requesterStats = ref<Record<string, number>>({})
const agentStats = ref<Record<string, number>>({})
const hasAgentView = ref(false)

const requesterCards = computed(() => [
  { title: 'Ouverts', value: requesterStats.value.open || 0, icon: 'tabler-ticket', color: 'primary', route: 'ticketing-mes-tickets', hint: 'Mes tickets actifs' },
  { title: 'En cours', value: requesterStats.value.in_progress || 0, icon: 'tabler-loader', color: 'warning', route: 'ticketing-mes-tickets', hint: 'Traitement en cours' },
  { title: 'En attente de moi', value: requesterStats.value.waiting_on_me || 0, icon: 'tabler-user-question', color: 'info', route: 'ticketing-mes-tickets', hint: 'Réponse requise' },
  { title: 'Résolus (14 j)', value: requesterStats.value.resolved_recent || 0, icon: 'tabler-circle-check', color: 'success', route: 'ticketing-mes-tickets', hint: 'Récemment résolus' },
])

const agentCards = computed(() => [
  { title: 'Affectés', value: agentStats.value.assigned || 0, icon: 'tabler-user-check', color: 'primary', route: 'ticketing-file', hint: 'Ma file' },
  { title: 'Non pris', value: agentStats.value.not_taken || 0, icon: 'tabler-hand-click', color: 'warning', route: 'ticketing-file', hint: 'À prendre en charge' },
  { title: 'SLA alerte', value: agentStats.value.sla_warning || 0, icon: 'tabler-alert-triangle', color: 'orange', route: 'ticketing-file', hint: 'Avertissement SLA' },
  { title: 'SLA dépassé', value: agentStats.value.sla_breached || 0, icon: 'tabler-alert-octagon', color: 'error', route: 'ticketing-file', hint: 'Délai dépassé' },
])

const quickLinks = [
  { title: 'Nouveau ticket', icon: 'tabler-plus', color: 'primary', route: 'ticketing-nouveau' },
  { title: 'Mes tickets', icon: 'tabler-ticket', color: 'info', route: 'ticketing-mes-tickets' },
  { title: 'Ma file', icon: 'tabler-inbox', color: 'warning', route: 'ticketing-file' },
  { title: 'Catalogue', icon: 'tabler-category', color: 'success', route: 'ticketing-catalogue' },
]

onMounted(async () => {
  loading.value = true
  try {
    const [requester, agent] = await Promise.allSettled([
      fetchDashboardRequester(),
      fetchDashboardAgent(),
    ])
    if (requester.status === 'fulfilled')
      requesterStats.value = requester.value || {}
    if (agent.status === 'fulfilled') {
      agentStats.value = agent.value || {}
      hasAgentView.value = true
    }
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Centre de services"
      subtitle="Demandes, incidents et suivi ITSM"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'ticketing-nouveau' }"
        >
          Nouveau ticket
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else>
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          v-for="link in quickLinks"
          :key="link.route"
          cols="6"
          md="3"
        >
          <VBtn
            block
            variant="tonal"
            :color="link.color"
            :prepend-icon="link.icon"
            :to="{ name: link.route }"
          >
            {{ link.title }}
          </VBtn>
        </VCol>
      </VRow>

      <h3 class="text-h6 mb-3">
        Vue demandeur
      </h3>
      <VRow
        dense
        class="mb-6"
      >
        <VCol
          v-for="card in requesterCards"
          :key="card.title"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            class="h-100 cursor-pointer"
            @click="router.push({ name: card.route })"
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
              <div>
                <div class="text-h4 font-weight-semibold lh-1 mb-1">
                  {{ card.value }}
                </div>
                <div class="text-body-2 font-weight-medium">
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

      <template v-if="hasAgentView">
        <h3 class="text-h6 mb-3">
          Vue agent
        </h3>
        <VRow dense>
          <VCol
            v-for="card in agentCards"
            :key="card.title"
            cols="12"
            sm="6"
            md="3"
          >
            <VCard
              class="h-100 cursor-pointer"
              @click="router.push({ name: card.route })"
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
                <div>
                  <div class="text-h4 font-weight-semibold lh-1 mb-1">
                    {{ card.value }}
                  </div>
                  <div class="text-body-2 font-weight-medium">
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
      </template>
    </template>
  </div>
</template>
