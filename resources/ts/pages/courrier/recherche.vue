<script setup lang="ts">
import { computed, ref } from 'vue'
import { $api } from '@/utils/api'
import { listItems } from '@/utils/listItems'
import { correspondenceDirectionLabels, correspondenceStatusColors, detailRouteName, formatCorrespondenceNumber, formatCourrierDate, statusLabel } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const loading = ref(false)
const results = ref<any>(null)
const filters = ref({
  q: '',
  direction: null as string | null,
  status: null as string | null,
  from: '',
  to: '',
})

const items = computed(() => listItems(results.value))

async function performSearch() {
  loading.value = true
  try {
    results.value = await $api('/mail/search', {
      query: {
        q: filters.value.q || undefined,
        direction: filters.value.direction || undefined,
        status: filters.value.status || undefined,
        from: filters.value.from || undefined,
        to: filters.value.to || undefined,
        per_page: 50,
      },
    })
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche avancée"
      subtitle="Courriers entrants, sortants et internes"
    />

    <VCard class="mb-4">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="filters.q"
              label="Mot-clé / n° / référence"
              @keyup.enter="performSearch"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.direction"
              clearable
              :items="Object.entries(correspondenceDirectionLabels).map(([value, title]) => ({ value, title }))"
              label="Direction"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppTextField
              v-model="filters.from"
              type="date"
              label="Du"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppTextField
              v-model="filters.to"
              type="date"
              label="Au"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-end"
          >
            <VBtn
              block
              color="primary"
              :loading="loading"
              @click="performSearch"
            >
              Rechercher
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable
        :headers="[
          { title: 'Direction', key: 'direction' },
          { title: 'N°', key: 'number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Statut', key: 'status' },
          { title: 'Date', key: 'correspondence_date' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.direction="{ item }">
          {{ correspondenceDirectionLabels[item.direction] || item.direction }}
        </template>
        <template #item.number="{ item }">
          <RouterLink :to="{ name: detailRouteName(item.direction), params: { id: item.id } }">
            {{ formatCorrespondenceNumber(item) }}
          </RouterLink>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.correspondence_date="{ item }">
          {{ formatCourrierDate(item.correspondence_date) }}
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
