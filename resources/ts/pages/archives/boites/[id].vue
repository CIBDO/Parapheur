<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const route = useRoute()
const { loading, getJson, assignBox, fetchLocations } = useArchives()
const box = ref<any | null>(null)
const label = ref<any | null>(null)
const locations = ref<any[]>([])
const locationId = ref<number | null>(null)

async function load() {
  const id = route.params.id
  ;[box.value, label.value, locations.value] = await Promise.all([
    getJson(`/archives/boxes/${id}`),
    getJson(`/archives/boxes/${id}/label`),
    fetchLocations().then(r => r.data || []),
  ])
  locationId.value = box.value?.location_id || null
}

async function assign() {
  if (!locationId.value)
    return
  box.value = await assignBox(Number(route.params.id), locationId.value)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="box?.code || 'Boîte'"
      :subtitle="box?.label || 'Fiche physique'"
      icon="tabler-box"
    />

    <VRow>
      <VCol
        cols="12"
        md="7"
      >
        <VCard
          class="parapheur-section-card mb-4"
          :loading="loading"
        >
          <VCardText v-if="box">
            <div class="mb-2">
              Emplacement : <strong>{{ box.location?.full_code || 'Non affecté' }}</strong>
            </div>
            <div class="mb-2">
              Statut : {{ box.status }} · État : {{ box.physical_state }}
            </div>
            <div class="d-flex gap-2 align-center mb-4">
              <VSelect
                v-model="locationId"
                :items="locations.map(l => ({ title: l.full_code, value: l.id }))"
                label="Nouvel emplacement"
                hide-details
                style="max-width: 320px"
              />
              <VBtn
                color="primary"
                @click="assign"
              >
                Affecter
              </VBtn>
            </div>
            <div class="text-subtitle-2 mb-2">
              Contenu
            </div>
            <VList>
              <VListItem
                v-for="c in box.contents || []"
                :key="c.id"
                :title="c.title || c.folder_code || `Record #${c.record_id}`"
              />
              <VListItem
                v-if="!(box.contents || []).length"
                title="Aucun dossier"
              />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Étiquette / QR</VCardTitle>
          </VCardItem>
          <VCardText v-if="label">
            <div>{{ label.code }}</div>
            <div class="text-medium-emphasis">
              {{ label.location || 'Sans localisation' }}
            </div>
            <div class="mt-3 text-caption text-break">
              {{ label.qr_payload }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              Token opaque — scan via API avec contrôle d’habilitation
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
