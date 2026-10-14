<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CorrespondenceCreateDialog from '@/components/courrier/CorrespondenceCreateDialog.vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { listItems } from '@/utils/listItems'
import { correspondenceStatusColors, formatCorrespondenceNumber, formatCourrierDate, statusLabel } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const router = useRouter()
const route = useRoute()
const { correspondences, fetchCorrespondences, loading } = useCorrespondence()
const search = ref('')
const showCreate = ref(false)

async function load() {
  await fetchCorrespondences({
    direction: 'sortant',
    search: search.value || undefined,
    per_page: 30,
  })
}

function onCreated(item: { id: number }) {
  router.push({ name: 'courrier-sortants-id', params: { id: item.id } })
}

onMounted(() => {
  load()
  if (route.query.nouveau)
    showCreate.value = true
})

const items = computed(() => listItems(correspondences.value))
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Courriers de départs"
      subtitle="Projets et registre de départ (DEP)"
    >
      <template #back>
        <CourrierBackButton to="courrier" />
      </template>
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="showCreate = true"
        >
          Nouveau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText class="d-flex gap-3">
        <AppTextField
          v-model="search"
          label="Recherche"
          style="max-inline-size: 280px"
          @keyup.enter="load"
        />
        <VBtn
          variant="tonal"
          color="primary"
          @click="load"
        >
          Filtrer
        </VBtn>
      </VCardText>
      <VDataTable
        :headers="[
          { title: 'N° / projet', key: 'departure_number' },
          { title: 'Objet', key: 'subject' },
          { title: 'Statut', key: 'status' },
          { title: 'Date', key: 'correspondence_date' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.departure_number="{ item }">
          <RouterLink :to="{ name: 'courrier-sortants-id', params: { id: item.id } }">
            {{ formatCorrespondenceNumber({ ...item, direction: 'sortant' }) }}
          </RouterLink>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="correspondenceStatusColors[item.status] || 'default'"
            variant="tonal"
          >
            {{ statusLabel(item.status, 'sortant') }}
          </VChip>
        </template>
        <template #item.correspondence_date="{ item }">
          {{ formatCourrierDate(item.correspondence_date) }}
        </template>
      </VDataTable>
    </VCard>

    <CorrespondenceCreateDialog
      v-model="showCreate"
      direction="sortant"
      @created="onCreated"
    />
  </div>
</template>
