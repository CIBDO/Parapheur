<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const route = useRoute()
const { loading, getJson, postJson, error } = useArchives()
const record = ref<any | null>(null)
const hybrid = ref({
  medium: 'electronique',
  physical_reference: '',
  box_id: null as number | null,
  location_id: null as number | null,
})

onMounted(async () => {
  record.value = await getJson(`/archives/records/${route.params.id}`)
  hybrid.value = {
    medium: record.value?.medium || 'electronique',
    physical_reference: record.value?.physical_reference || '',
    box_id: record.value?.box_id || null,
    location_id: record.value?.location_id || null,
  }
})

async function saveHybrid() {
  record.value = await postJson(`/archives/records/${route.params.id}/hybrid`, { ...hybrid.value })
}

async function restore() {
  if (!confirm('Restaurer depuis la réplique ?'))
    return
  await postJson(`/archives/records/${route.params.id}/restore`)
  record.value = await getJson(`/archives/records/${route.params.id}`)
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="record?.title || 'Archive'"
      :subtitle="record?.permanent_id || 'Fiche archivistique'"
      icon="tabler-archive"
    />
    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
    </VAlert>
    <VCard
      class="parapheur-section-card mb-4"
      :loading="loading"
    >
      <VCardText v-if="record">
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption text-medium-emphasis">
              Référence
            </div>
            <div class="mb-3">
              {{ record.reference || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Structure
            </div>
            <div class="mb-3">
              {{ record.structure?.name || record.structure?.code || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Statut
            </div>
            <div class="mb-3">
              {{ record.status }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Préservation
            </div>
            <div class="mb-3">
              {{ record.preservation_status || '—' }}
            </div>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption text-medium-emphasis">
              Fonds / Série
            </div>
            <div class="mb-3">
              {{ record.fonds?.code || '—' }} / {{ record.series?.code || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Empreinte
            </div>
            <div class="mb-3 text-break">
              {{ record.checksum || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Document source
            </div>
            <div>
              #{{ record.document_id }} · version {{ record.document_version_id || '—' }}
            </div>
          </VCol>
        </VRow>
        <div
          v-if="record.description"
          class="mt-4"
        >
          {{ record.description }}
        </div>
        <div class="mt-4">
          <VBtn
            color="warning"
            variant="tonal"
            size="small"
            @click="restore"
          >
            Restaurer depuis réplique
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VCardTitle>Support hybride</VCardTitle>
      <VCardText>
        <VSelect
          v-model="hybrid.medium"
          :items="[
            { title: 'Électronique', value: 'electronique' },
            { title: 'Physique', value: 'physique' },
            { title: 'Hybride', value: 'hybride' },
          ]"
          label="Support"
          class="mb-2"
        />
        <VTextField
          v-model="hybrid.physical_reference"
          label="Cote / référence papier"
          class="mb-2"
        />
        <VTextField
          v-model.number="hybrid.box_id"
          label="Boîte ID"
          type="number"
          class="mb-2"
        />
        <VTextField
          v-model.number="hybrid.location_id"
          label="Emplacement ID"
          type="number"
          class="mb-2"
        />
        <VBtn
          color="primary"
          @click="saveHybrid"
        >
          Enregistrer le lien hybride
        </VBtn>
      </VCardText>
    </VCard>
  </div>
</template>
