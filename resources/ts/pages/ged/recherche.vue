<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useGedDocumentList } from '@/composables/useGedDocumentList'
import {
  confidentialityOptions,
  originOptions,
  statusOptions,
} from '@/utils/gedUi'

definePage({
  meta: { action: 'read', subject: 'Ged' },
})

const documentTypes = ref<Array<{ id: number; name: string }>>([])
const structures = ref<Array<{ id: number; name: string }>>([])
const categories = ref<Array<{ id: number; name: string }>>([])

const {
  loading, items, total, page, perPage, filters, headers, load, openDoc,
  labelOf, statusLabels, statusColor, confidentialityLabels, formatDateFr,
} = useGedDocumentList()

const search = () => {
  page.value = 1
  load()
}

onMounted(async () => {
  const [types, structs, cats] = await Promise.all([
    $api('/meta/document-types'),
    $api('/meta/structures'),
    $api('/meta/document-categories'),
  ])
  documentTypes.value = types
  structures.value = structs
  categories.value = cats.data || cats
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Recherche documentaire"
      subtitle="Que recherchez-vous ?"
      icon="tabler-search"
    />

    <VCard class="mb-4 parapheur-section-card">
      <VCardText>
        <AppTextField
          v-model="filters.q"
          label="Rechercher un document…"
          placeholder="Titre, objet, référence, numéro de dossier, mots-clés…"
          prepend-inner-icon="tabler-search"
          class="mb-4"
          @keyup.enter="search"
        />
        <VExpandTransition>
          <VRow>
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="filters.document_type_id"
                :items="documentTypes"
                item-title="name"
                item-value="id"
                label="Type"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="filters.category_id"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Catégorie"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="filters.structure_id"
                :items="structures"
                item-title="name"
                item-value="id"
                label="Structure"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
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
              md="3"
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
              md="3"
            >
              <AppTextField
                v-model="filters.document_date_from"
                type="date"
                label="Date début"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="filters.document_date_to"
                type="date"
                label="Date fin"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="filters.tag"
                label="Tag"
                placeholder="#Budget2027"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
              class="d-flex align-center"
            >
              <VBtn
                color="primary"
                block
                prepend-icon="tabler-search"
                @click="search"
              >
                Rechercher
              </VBtn>
            </VCol>
          </VRow>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <VCard
      v-if="items.length || loading"
      class="parapheur-section-card"
    >
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        @update:options="({ page: p, itemsPerPage }: any) => { page = p; perPage = itemsPerPage; load() }"
      >
        <template #item.object="{ item }">
          {{ item.title || item.object }}
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
            @click="openDoc(item.id)"
          >
            Ouvrir
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
