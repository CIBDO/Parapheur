<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { recordStatusLabels, useArchives } from '@/composables/useArchives'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: { action: 'read', subject: 'Archives' },
})

const route = useRoute()
const router = useRouter()
const { loading, getJson, postJson, error } = useArchives()
const record = ref<any | null>(null)
const hybrid = ref({
  medium: 'electronique',
  physical_reference: '',
  box_id: null as number | null,
  location_id: null as number | null,
})
const boxes = ref<any[]>([])
const locations = ref<any[]>([])

const mediumItems = [
  { title: 'Électronique', value: 'electronique' },
  { title: 'Physique', value: 'physique' },
  { title: 'Hybride', value: 'hybride' },
]

async function load() {
  record.value = await getJson(`/archives/records/${route.params.id}`)
  hybrid.value = {
    medium: record.value?.medium || 'electronique',
    physical_reference: record.value?.physical_reference || '',
    box_id: record.value?.box_id || null,
    location_id: record.value?.location_id || null,
  }
  const [b, l] = await Promise.all([
    getJson('/archives/boxes').catch(() => ({ data: [] })),
    getJson('/archives/locations').catch(() => ({ data: [] })),
  ])
  boxes.value = b.data || b || []
  locations.value = l.data || l || []
}

async function saveHybrid() {
  record.value = await postJson(`/archives/records/${route.params.id}/hybrid`, { ...hybrid.value })
}

async function restore() {
  if (!confirm('Restaurer depuis la réplique ?'))
    return
  await postJson(`/archives/records/${route.params.id}/restore`)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="record?.title || 'Archive'"
      :subtitle="record?.permanent_id || ''"
      icon="tabler-archive"
    >
      <template #back>
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          @click="router.push({ name: 'archives-explorer' })"
        />
      </template>
      <template
        v-if="record"
        #actions
      >
        <VChip
          label
          color="secondary"
        >
          {{ recordStatusLabels[record.status] || record.status }}
        </VChip>
        <VBtn
          v-if="record.preservation_status === 'at_risk'"
          color="warning"
          variant="tonal"
          size="small"
          @click="restore"
        >
          Restaurer
        </VBtn>
      </template>
    </ParapheurPageHeader>

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
              Conservé le
            </div>
            <div class="mb-3">
              {{ formatDateFr(record.conserved_at, true) }}
            </div>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption text-medium-emphasis">
              Fonds / série
            </div>
            <div class="mb-3">
              {{ record.fonds?.name || record.fonds?.code || '—' }}
              /
              {{ record.series?.name || record.series?.code || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Document
            </div>
            <div class="mb-3">
              {{ record.document?.reference || record.document?.title || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Empreinte
            </div>
            <div class="text-break">
              {{ record.checksum || '—' }}
            </div>
          </VCol>
        </VRow>
        <div
          v-if="record.description"
          class="mt-4"
        >
          {{ record.description }}
        </div>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VCardTitle>Support</VCardTitle>
      <VCardText class="d-flex flex-column gap-3">
        <VSelect
          v-model="hybrid.medium"
          :items="mediumItems"
          label="Support"
          style="max-width: 280px"
        />
        <VTextField
          v-model="hybrid.physical_reference"
          label="Cote papier"
          style="max-width: 280px"
        />
        <VSelect
          v-model="hybrid.box_id"
          :items="boxes.map(b => ({ title: b.code + (b.label ? ` — ${b.label}` : ''), value: b.id }))"
          label="Boîte"
          clearable
          style="max-width: 360px"
        />
        <VSelect
          v-model="hybrid.location_id"
          :items="locations.map(l => ({ title: `${l.full_code} — ${l.name}`, value: l.id }))"
          label="Emplacement"
          clearable
          style="max-width: 360px"
        />
        <div>
          <VBtn
            color="primary"
            @click="saveHybrid"
          >
            Enregistrer
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
