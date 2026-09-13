<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import {
  formatCorrespondenceNumber,
  formatCourrierDate,
  getTransmissionSlipNatureLabel,
  getTransmissionSlipStatusColor,
  getTransmissionSlipStatusLabel,
  listItems,
  transmissionSlipNatureLabels,
} from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const router = useRouter()
const slips = ref<any>(null)
const loading = ref(true)
const createOpen = ref(false)
const creating = ref(false)
const createError = ref('')
const search = ref('')
const statusFilter = ref<string | null>(null)

const form = ref({
  from_structure_id: null as number | null,
  to_structure_id: null as number | null,
  nature: 'pour_traitement',
  observations: '',
  selected_correspondences: [] as number[],
})

const structures = ref<any[]>([])
const recentCorrespondences = ref<any[]>([])

const items = computed(() => {
  let rows = listItems(slips.value)
  const q = search.value.trim().toLowerCase()
  if (q) {
    rows = rows.filter((item: any) => {
      const hay = [
        item.number,
        item.from_structure?.name,
        item.to_structure?.name,
        item.nature,
        item.status,
      ].join(' ').toLowerCase()

      return hay.includes(q)
    })
  }
  if (statusFilter.value)
    rows = rows.filter((item: any) => item.status === statusFilter.value)

  return rows
})

const stats = computed(() => {
  const rows = listItems(slips.value)
  const count = (status: string) => rows.filter((r: any) => r.status === status).length

  return [
    { title: 'Total', value: rows.length, icon: 'tabler-clipboard-list', color: 'primary' },
    { title: 'Brouillons', value: count('brouillon'), icon: 'tabler-file-pencil', color: 'secondary' },
    { title: 'Transmis', value: count('transmis'), icon: 'tabler-send', color: 'success' },
    { title: 'Reçus', value: count('recu'), icon: 'tabler-circle-check', color: 'teal' },
  ]
})

const structureItems = computed(() =>
  structures.value.map((s: any) => ({
    value: s.id,
    title: s.code ? `${s.code} — ${s.name}` : (s.name || `#${s.id}`),
  })),
)

const natureItems = computed(() =>
  Object.entries(transmissionSlipNatureLabels).map(([value, title]) => ({ value, title })),
)

const statusFilterItems = computed(() => [
  { value: null, title: 'Tous les statuts' },
  ...Object.entries({
    brouillon: 'Brouillon',
    valide: 'Validé',
    genere: 'Généré',
    imprime: 'Imprimé',
    transmis: 'Transmis',
    recu: 'Reçu',
  }).map(([value, title]) => ({ value, title })),
])

const correspondenceItems = computed(() =>
  recentCorrespondences.value.map((c: any) => ({
    title: `${formatCorrespondenceNumber(c)} — ${c.subject || 'Sans objet'}`,
    value: c.id,
    subtitle: c.structure?.name || '',
  })),
)

async function load() {
  loading.value = true
  try {
    slips.value = await $api('/mail/transmission-slips')
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  try {
    structures.value = await $api('/meta/structures')
  }
  catch {
    structures.value = []
  }
  try {
    const resp = await $api('/mail/correspondences', { query: { per_page: 50 } })
    recentCorrespondences.value = resp?.data || []
  }
  catch {
    recentCorrespondences.value = []
  }
})

function openCreate() {
  createError.value = ''
  createOpen.value = true
}

async function createSlip() {
  createError.value = ''
  if (!form.value.from_structure_id || !form.value.to_structure_id) {
    createError.value = 'Sélectionnez les structures expéditrice et destinataire.'

    return
  }
  if (form.value.from_structure_id === form.value.to_structure_id) {
    createError.value = 'Les structures expéditrice et destinataire doivent être différentes.'

    return
  }
  if (!form.value.selected_correspondences.length) {
    createError.value = 'Ajoutez au moins un courrier au bordereau.'

    return
  }

  creating.value = true
  try {
    const created = await $api('/mail/transmission-slips', {
      method: 'POST',
      body: {
        from_structure_id: form.value.from_structure_id,
        to_structure_id: form.value.to_structure_id,
        nature: form.value.nature,
        observations: form.value.observations,
        items: form.value.selected_correspondences.map(id => ({ correspondence_id: id })),
      },
    })
    createOpen.value = false
    form.value = {
      from_structure_id: null,
      to_structure_id: null,
      nature: 'pour_traitement',
      observations: '',
      selected_correspondences: [],
    }
    await load()
    if (created?.id)
      router.push({ name: 'courrier-bordereaux-id', params: { id: created.id } })
  }
  catch (e: any) {
    createError.value = e?.data?.message || e.message || 'Impossible de créer le bordereau'
  }
  finally {
    creating.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Bordereaux de transmission"
      subtitle="Transfert interne de courriers entre structures"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouveau bordereau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      type="info"
      variant="tonal"
      class="mb-4"
    >
      Un bordereau <strong>BT</strong> regroupe un ou plusieurs courriers pour les transmettre
      d’une structure à une autre (validation, génération DOCX, impression, accusé de réception).
    </VAlert>

    <VRow class="mb-4">
      <VCol
        v-for="stat in stats"
        :key="stat.title"
        cols="6"
        md="3"
      >
        <VCard class="h-100">
          <VCardText class="d-flex align-center ga-3">
            <VAvatar
              :color="stat.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="stat.icon" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ stat.title }}
              </div>
              <div class="text-h5 font-weight-medium">
                {{ stat.value }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText class="d-flex flex-wrap ga-3">
        <AppTextField
          v-model="search"
          label="Recherche"
          prepend-inner-icon="tabler-search"
          style="max-inline-size: 280px"
          clearable
        />
        <AppSelect
          v-model="statusFilter"
          :items="statusFilterItems"
          label="Statut"
          style="max-inline-size: 220px"
          clearable
        />
        <VBtn
          variant="tonal"
          color="primary"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
      </VCardText>

      <VDataTable
        :headers="[
          { title: 'N° BT', key: 'number' },
          { title: 'Trajet', key: 'trajet' },
          { title: 'Nature', key: 'nature' },
          { title: 'Courriers', key: 'items_count' },
          { title: 'Statut', key: 'status' },
          { title: 'Créé le', key: 'created_at' },
        ]"
        :items="items"
        :loading="loading"
        item-value="id"
      >
        <template #item.number="{ item }">
          <RouterLink
            class="font-weight-medium"
            :to="{ name: 'courrier-bordereaux-id', params: { id: item.id } }"
          >
            {{ item.number || `Brouillon #${item.id}` }}
          </RouterLink>
        </template>

        <template #item.trajet="{ item }">
          <div class="d-flex flex-column ga-1 py-1">
            <div class="d-flex align-center ga-2 text-body-2">
              <VIcon
                icon="tabler-building"
                size="16"
                class="text-medium-emphasis"
              />
              <span>{{ item.from_structure?.name || '—' }}</span>
            </div>
            <div class="d-flex align-center ga-2 text-body-2">
              <VIcon
                icon="tabler-arrow-down-right"
                size="16"
                color="primary"
              />
              <span class="font-weight-medium">{{ item.to_structure?.name || '—' }}</span>
            </div>
          </div>
        </template>

        <template #item.nature="{ item }">
          {{ getTransmissionSlipNatureLabel(item.nature) }}
        </template>

        <template #item.items_count="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ item.items?.length ?? item.items_count ?? 0 }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="getTransmissionSlipStatusColor(item.status)"
            variant="tonal"
          >
            {{ getTransmissionSlipStatusLabel(item.status) }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          {{ formatCourrierDate(item.created_at) }}
        </template>

        <template #no-data>
          <div class="parapheur-empty py-10">
            <VIcon
              icon="tabler-clipboard-off"
              size="40"
              class="mb-2"
            />
            <div class="font-weight-medium mb-1">
              Aucun bordereau
            </div>
            <div class="text-caption mb-4">
              Créez un bordereau pour transmettre des courriers entre services.
            </div>
            <VBtn
              color="primary"
              variant="tonal"
              prepend-icon="tabler-plus"
              @click="openCreate"
            >
              Nouveau bordereau
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="createOpen"
      max-width="720"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center ga-2">
          <VIcon icon="tabler-file-invoice" />
          Nouveau bordereau de transmission
        </VCardTitle>
        <VCardText>
          <VAlert
            v-if="createError"
            type="error"
            variant="tonal"
            class="mb-4"
            density="compact"
          >
            {{ createError }}
          </VAlert>

          <div class="parapheur-form-section mb-4">
            <div class="parapheur-form-section__title">
              Trajet
            </div>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.from_structure_id"
                  :items="structureItems"
                  label="Structure expéditrice *"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.to_structure_id"
                  :items="structureItems"
                  label="Structure destinataire *"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.nature"
                  :items="natureItems"
                  label="Nature de la transmission"
                />
              </VCol>
            </VRow>
          </div>

          <div class="parapheur-form-section mb-4">
            <div class="parapheur-form-section__title">
              Courriers à transmettre
            </div>
            <VSelect
              v-model="form.selected_correspondences"
              :items="correspondenceItems"
              multiple
              chips
              closable-chips
              label="Sélectionner les courriers *"
              hint="Choisissez un ou plusieurs courriers récents"
              persistent-hint
            />
          </div>

          <AppTextarea
            v-model="form.observations"
            label="Observations"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="createOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="creating"
            @click="createSlip"
          >
            Créer le bordereau
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
