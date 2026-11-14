<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatTaskDue } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const router = useRouter()
const loading = ref(false)
const errorMessage = ref('')
const pack = ref<any>(null)
const date = ref(new Date().toISOString().slice(0, 10))
const filter = ref<'all' | 'missing' | 'to_send' | 'sent' | 'viewed' | 'with_opinion'>('all')

const bucketColor = (bucket?: string) => {
  switch (bucket) {
    case 'with_opinion': return 'info'
    case 'viewed': return 'primary'
    case 'sent': return 'success'
    case 'to_send': return 'warning'
    case 'missing': return 'error'
    default: return 'secondary'
  }
}

const cards = computed(() => {
  const s = pack.value?.stats || {}

  return [
    { key: 'expected', title: 'Attendus', value: s.expected ?? 0, color: 'secondary', filter: 'all' as const },
    { key: 'missing', title: 'Manquants', value: s.missing ?? 0, color: 'error', filter: 'missing' as const },
    { key: 'to_send', title: 'À envoyer', value: s.to_send ?? 0, color: 'warning', filter: 'to_send' as const },
    { key: 'sent', title: 'Envoyés', value: s.sent ?? 0, color: 'success', filter: 'sent' as const },
    { key: 'viewed', title: 'Consultés', value: s.viewed ?? 0, color: 'primary', filter: 'viewed' as const },
    { key: 'with_opinion', title: 'Avec avis', value: s.with_opinion ?? 0, color: 'info', filter: 'with_opinion' as const },
  ]
})

const visibleItems = computed(() => {
  const items = pack.value?.items || []
  if (filter.value === 'all')
    return items
  if (filter.value === 'sent')
    return items.filter((i: any) => ['sent', 'viewed', 'with_opinion'].includes(i.bucket))
  if (filter.value === 'viewed')
    return items.filter((i: any) => ['viewed', 'with_opinion'].includes(i.bucket))

  return items.filter((i: any) => i.bucket === filter.value)
})

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    pack.value = await $api('/productions/dossier-du-jour', {
      query: { date: date.value || undefined },
    })
  }
  catch (error: any) {
    pack.value = null
    errorMessage.value = error?.data?.message || 'Impossible de charger le dossier du jour.'
  }
  finally {
    loading.value = false
  }
}

const openItem = (item: any) => {
  if (item.my_transmission_id) {
    router.push(`/productions/recues?transmission=${item.my_transmission_id}`)
    return
  }
  if (item.occurrence_id) {
    router.push(`/productions/occurrences/${item.occurrence_id}`)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="pack?.title || 'Dossier du jour'"
      subtitle="Pack trésorerie — manquants, envoyés, consultés, avis"
      icon="tabler-folder"
    >
      <template #actions>
        <AppTextField
          v-model="date"
          type="date"
          density="compact"
          hide-details
          style="max-inline-size: 180px"
          @update:model-value="load"
        />
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
          prepend-icon="tabler-inbox"
          @click="router.push('/productions/recues')"
        >
          Reçues
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
      v-if="loading && !pack"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="pack">
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          v-for="card in cards"
          :key="card.key"
          cols="6"
          md="4"
          lg="2"
        >
          <VCard
            :variant="filter === card.filter ? 'elevated' : 'tonal'"
            :color="filter === card.filter ? card.color : undefined"
            class="cursor-pointer"
            @click="filter = card.filter"
          >
            <VCardText class="text-center py-4">
              <div class="text-h5 font-weight-bold">
                {{ card.value }}
              </div>
              <div class="text-caption">
                {{ card.title }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VCard>
        <VCardItem>
          <VCardTitle class="text-h6">
            Situations du {{ formatTaskDue(pack.date) }}
          </VCardTitle>
          <VCardSubtitle>
            {{ visibleItems.length }} ligne(s)
            <span v-if="pack.folder_reference"> · {{ pack.folder_reference }}</span>
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VTable>
          <thead>
            <tr>
              <th>Situation</th>
              <th>Structure</th>
              <th>Responsable</th>
              <th>État</th>
              <th>Avis</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in visibleItems"
              :key="item.obligation_code"
              class="cursor-pointer"
              @click="openItem(item)"
            >
              <td>
                <div class="font-weight-medium">
                  {{ item.obligation_label }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.obligation_code }}
                  <span v-if="item.reference"> · {{ item.reference }}</span>
                </div>
              </td>
              <td>{{ item.structure?.name || '—' }}</td>
              <td>{{ item.producer?.name || '—' }}</td>
              <td>
                <VChip
                  size="small"
                  :color="bucketColor(item.bucket)"
                  variant="tonal"
                >
                  {{ item.bucket_label }}
                </VChip>
              </td>
              <td>
                <div
                  v-if="item.opinions?.length"
                  class="text-caption"
                >
                  <div
                    v-for="(op, idx) in item.opinions"
                    :key="idx"
                  >
                    {{ op.user?.name || '—' }} : {{ op.opinion }}
                  </div>
                </div>
                <span
                  v-else
                  class="text-medium-emphasis"
                >—</span>
              </td>
            </tr>
            <tr v-if="!visibleItems.length">
              <td
                colspan="5"
                class="text-center text-medium-emphasis py-10"
              >
                Aucune situation pour ce filtre
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </template>
  </div>
</template>
