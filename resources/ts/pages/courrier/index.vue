<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCorrespondence } from '@/composables/useCorrespondence'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Courrier',
  },
})

const router = useRouter()
const { fetchDashboard, fetchDashboardDg, fetchDashboardDirection } = useCorrespondence()
const stats = ref<Record<string, number>>({})
const loading = ref(true)
const dashboardType = ref<'order-office' | 'dg' | 'direction'>('order-office')

onMounted(async () => {
  await loadDashboard()
})

async function loadDashboard() {
  loading.value = true
  try {
    if (dashboardType.value === 'dg') {
      stats.value = await fetchDashboardDg()
    } else if (dashboardType.value === 'direction') {
      stats.value = await fetchDashboardDirection()
    } else {
      stats.value = await fetchDashboard()
    }
  }
  catch (error) {
    console.error(error)
  }
  finally {
    loading.value = false
  }
}

async function switchDashboard(type: 'order-office' | 'dg' | 'direction') {
  dashboardType.value = type
  await loadDashboard()
}

const cards = computed(() => {
  const base = [
    { title: 'Reçus aujourd\'hui', value: stats.value.received_today || 0, icon: 'tabler-inbox', color: 'info', route: 'courrier-entrants' },
    { title: 'À qualifier', value: stats.value.to_qualify || 0, icon: 'tabler-tags', color: 'secondary', route: 'courrier-entrants' },
    { title: 'À affecter', value: stats.value.to_assign || 0, icon: 'tabler-user-plus', color: 'warning', route: 'courrier-a-affecter' },
    { title: 'Sans affectation', value: stats.value.unassigned || 0, icon: 'tabler-user-off', color: 'error', route: 'courrier-a-affecter' },
    { title: 'En traitement', value: stats.value.in_processing || 0, icon: 'tabler-clock', color: 'primary', route: 'courrier-a-traiter' },
    { title: 'À expédier', value: stats.value.to_dispatch || 0, icon: 'tabler-send', color: 'success', route: 'courrier-sortants' },
    { title: 'En retard', value: stats.value.overdue || 0, icon: 'tabler-alert-triangle', color: 'error', route: 'courrier-en-retard' },
    { title: 'Mes en attente', value: stats.value.my_pending || 0, icon: 'tabler-mail-opened', color: 'info', route: 'courrier-a-traiter' },
  ]

  // Add extra cards if available
  if (stats.value.slips_in_progress !== undefined) {
    base.push({ title: 'Bordereaux en cours', value: stats.value.slips_in_progress, icon: 'tabler-clipboard-list', color: 'purple', route: 'courrier-bordereaux' })
  }
  if (stats.value.circulation_sheets_open !== undefined) {
    base.push({ title: 'Fiches circulation ouvertes', value: stats.value.circulation_sheets_open, icon: 'tabler-file-invoice', color: 'teal', route: 'courrier-fiches' })
  }
  if (stats.value.reminders_due_today !== undefined) {
    base.push({ title: 'Relances aujourd\'hui', value: stats.value.reminders_due_today, icon: 'tabler-bell-ringing', color: 'orange', route: 'courrier-en-retard' })
  }

  return base
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Bureau d'ordre — Courrier"
      subtitle="Tableau de bord opérationnel"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'courrier-entrants-nouveau' }"
        >
          Enregistrer un entrant
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="mb-4">
      <VCardText>
        <div class="d-flex gap-2">
          <VBtn
            :variant="dashboardType === 'order-office' ? 'tonal' : 'text'"
            :color="dashboardType === 'order-office' ? 'primary' : 'default'"
            @click="switchDashboard('order-office')"
          >
            Bureau d'ordre
          </VBtn>
          <VBtn
            :variant="dashboardType === 'dg' ? 'tonal' : 'text'"
            :color="dashboardType === 'dg' ? 'primary' : 'default'"
            @click="switchDashboard('dg')"
          >
            Vue DG
          </VBtn>
          <VBtn
            :variant="dashboardType === 'direction' ? 'tonal' : 'text'"
            :color="dashboardType === 'direction' ? 'primary' : 'default'"
            @click="switchDashboard('direction')"
          >
            Vue Direction
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VRow v-if="!loading">
      <VCol
        v-for="card in cards"
        :key="card.title"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="cursor-pointer"
          @click="router.push({ name: card.route })"
        >
          <VCardText class="d-flex align-center">
            <VAvatar
              :color="card.color"
              variant="tonal"
              size="44"
              class="me-4"
            >
              <VIcon
                :icon="card.icon"
                size="26"
              />
            </VAvatar>
            <div>
              <p class="text-caption mb-0">
                {{ card.title }}
              </p>
              <h3 class="text-h4">
                {{ card.value }}
              </h3>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <div
      v-else
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>
  </div>
</template>
