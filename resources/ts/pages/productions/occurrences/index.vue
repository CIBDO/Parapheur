<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatTaskDue, productionStatusColor, productionStatusLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const errorMessage = ref('')
const items = ref<any[]>([])
const total = ref(0)
const filters = ref({
  q: '',
  status: '',
})

const view = computed(() => String(route.query.view || 'all'))

const title = computed(() => {
  switch (view.value) {
    case 'overdue': return 'Productions en retard'
    case 'validate': return 'Productions à valider'
    case 'transmit': return 'Productions à transmettre'
    case 'mine': return 'Mes productions'
    default: return 'Occurrences de production'
  }
})

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const query: Record<string, any> = {
      q: filters.value.q || undefined,
      status: filters.value.status || undefined,
    }
    if (view.value === 'overdue')
      query.overdue = 1
    if (view.value === 'validate')
      query.to_validate = 1
    if (view.value === 'transmit')
      query.status = 'prete_a_transmettre'
    if (view.value === 'mine')
      query.mine = 1

    const res = await $api('/productions/occurrences', { query })
    items.value = res.data ?? res
    total.value = res.total ?? items.value.length
  }
  catch (error: any) {
    items.value = []
    total.value = 0
    errorMessage.value = error?.data?.message || 'Impossible de charger les occurrences.'
  }
  finally {
    loading.value = false
  }
}

const isLate = (item: any) => {
  if (!item.deadline_at || ['transmise', 'cloturee', 'annulee'].includes(item.status))
    return false

  return new Date(item.deadline_at) < new Date()
}

watch(() => route.query.view, load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="title"
      subtitle="Livrables attendus par période"
      icon="tabler-calendar-event"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/productions')"
        >
          Tableau de bord
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
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche (référence, obligation)"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
            />
          </VCol>
          <VCol
            v-if="view === 'all' || view === 'mine'"
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="filters.status"
              label="Statut"
              clearable
              :items="Object.entries(productionStatusLabels).map(([value, title]) => ({ title, value }))"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              block
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
            <th>Référence</th>
            <th>Obligation</th>
            <th>Responsable</th>
            <th>Échéance</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            class="cursor-pointer"
            @click="router.push(`/productions/occurrences/${item.id}`)"
          >
            <td class="font-weight-medium">
              {{ item.reference }}
            </td>
            <td>{{ item.obligation?.label || item.obligation?.code || '—' }}</td>
            <td>{{ item.responsible?.name || '—' }}</td>
            <td :class="{ 'text-error': isLate(item) }">
              {{ formatTaskDue(item.deadline_at) }}
            </td>
            <td>
              <VChip
                size="small"
                :color="isLate(item) ? 'error' : productionStatusColor(item.status)"
                variant="tonal"
              >
                {{ productionStatusLabels[item.status] || item.status }}
              </VChip>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td
              colspan="5"
              class="text-center text-medium-emphasis py-10"
            >
              <VIcon
                icon="tabler-file-off"
                size="40"
                class="mb-2 d-block mx-auto"
              />
              Aucune occurrence
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
  </div>
</template>
