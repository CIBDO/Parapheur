<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useGedDocumentList } from '@/composables/useGedDocumentList'
import { originOptions } from '@/utils/gedUi'
import { confidentialityOptions, statusOptions } from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Ged',
  },
})

const {
  loading,
  items,
  total,
  page,
  perPage,
  filters,
  headers,
  load,
  openDoc,
  labelOf,
  statusLabels,
  statusColor,
  confidentialityLabels,
  formatDateFr,
} = useGedDocumentList()

watch([page, perPage], () => load())
onMounted(load)

const applyFilters = () => {
  page.value = 1
  load()
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Tous les documents"
      subtitle="Catalogue documentaire GED"
      icon="tabler-files"
    >
      <VBtn
        color="primary"
        prepend-icon="tabler-file-plus"
        :to="{ name: 'ged-nouveau' }"
      >
        Nouveau
      </VBtn>
    </ParapheurPageHeader>

    <VCard class="mb-4 parapheur-section-card">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="filters.q"
              label="Rechercher…"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="applyFilters"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.status"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              label="Statut"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.confidentiality"
              :items="confidentialityOptions"
              item-title="title"
              item-value="value"
              label="Confidentialité"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <AppSelect
              v-model="filters.origin"
              :items="originOptions"
              item-title="title"
              item-value="value"
              label="Origine"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="1"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              block
              @click="applyFilters"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        item-value="id"
        @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage }"
      >
        <template #item.object="{ item }">
          <div class="font-weight-medium">
            {{ item.title || item.object }}
          </div>
          <div
            v-if="item.tags?.length"
            class="d-flex flex-wrap gap-1 mt-1"
          >
            <VChip
              v-for="tag in item.tags.slice(0, 3)"
              :key="tag.id"
              size="x-small"
              label
            >
              #{{ tag.name }}
            </VChip>
          </div>
        </template>
        <template #item.type="{ item }">
          {{ item.type?.name || '—' }}
        </template>
        <template #item.structure="{ item }">
          {{ item.structure?.code || '—' }}
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            label
          >
            {{ labelOf(statusLabels, item.status) }}
          </VChip>
        </template>
        <template #item.confidentiality="{ item }">
          {{ labelOf(confidentialityLabels, item.confidentiality) }}
        </template>
        <template #item.document_date="{ item }">
          {{ formatDateFr(item.document_date) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            color="primary"
            @click="openDoc(item.id)"
          >
            Ouvrir
          </VBtn>
        </template>
        <template #no-data>
          <div class="parapheur-empty text-center py-8 text-medium-emphasis">
            Aucun document trouvé.
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
