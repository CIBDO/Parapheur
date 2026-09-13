<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { $api } from '@/utils/api'
import { listItems, transmissionSlipStatusLabels, formatCorrespondenceNumber } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Courrier' },
})

const slips = ref<any>(null)
const loading = ref(true)
const createOpen = ref(false)
const creating = ref(false)
const form = ref({
  from_structure_id: null as number | null,
  to_structure_id: null as number | null,
  nature: 'pour_traitement',
  observations: '',
  correspondence_ids: '' as string,
  selected_correspondences: [] as number[],
})
const structures = ref<any[]>([])
const recentCorrespondences = ref<any[]>([])

const items = computed(() => listItems(slips.value))

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
    const resp = await $api('/mail/correspondences', { query: { per_page: 50, sort: '-id' } })
    recentCorrespondences.value = resp?.data || []
  }
  catch {
    recentCorrespondences.value = []
  }
})

async function createSlip() {
  creating.value = true
  try {
    // Support both manual IDs and multi-select
    let ids: number[] = []
    
    if (form.value.selected_correspondences.length > 0) {
      ids = form.value.selected_correspondences
    } else if (form.value.correspondence_ids) {
      ids = form.value.correspondence_ids
        .split(/[,\s]+/)
        .map(v => Number(v.trim()))
        .filter(Boolean)
    }

    const created = await $api('/mail/transmission-slips', {
      method: 'POST',
      body: {
        from_structure_id: form.value.from_structure_id,
        to_structure_id: form.value.to_structure_id,
        nature: form.value.nature,
        observations: form.value.observations,
        items: ids.map(id => ({ correspondence_id: id })),
      },
    })
    createOpen.value = false
    form.value.selected_correspondences = []
    form.value.correspondence_ids = ''
    await load()
    if (created?.id)
      window.location.href = `/courrier/bordereaux/${created.id}`
  }
  finally {
    creating.value = false
  }
}

const correspondenceItems = computed(() => {
  return recentCorrespondences.value.map(c => ({
    title: `${formatCorrespondenceNumber(c)} - ${c.subject}`,
    value: c.id,
  }))
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Bordereaux de transmission"
      subtitle="Regroupement multi-courriers"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createOpen = true"
        >
          Nouveau bordereau
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VDataTable
        :headers="[
          { title: 'Numéro', key: 'number' },
          { title: 'De', key: 'from' },
          { title: 'Vers', key: 'to' },
          { title: 'Nature', key: 'nature' },
          { title: 'Statut', key: 'status' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.number="{ item }">
          <RouterLink :to="{ name: 'courrier-bordereaux-id', params: { id: item.id } }">
            {{ item.number || `Brouillon #${item.id}` }}
          </RouterLink>
        </template>
        <template #item.from="{ item }">
          {{ item.from_structure?.name || '—' }}
        </template>
        <template #item.to="{ item }">
          {{ item.to_structure?.name || '—' }}
        </template>
        <template #item.status="{ item }">
          {{ transmissionSlipStatusLabels[item.status] || item.status }}
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="createOpen"
      max-width="800"
    >
      <VCard title="Créer un bordereau">
        <VCardText>
          <AppSelect
            v-model="form.from_structure_id"
            class="mb-3"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure expéditrice"
          />
          <AppSelect
            v-model="form.to_structure_id"
            class="mb-3"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure destinataire"
          />
          <AppTextField
            v-model="form.nature"
            class="mb-3"
            label="Nature"
          />
          
          <div class="mb-4">
            <div class="text-caption mb-2">
              Option 1 : Sélection multiple
            </div>
            <VSelect
              v-model="form.selected_correspondences"
              :items="correspondenceItems"
              multiple
              chips
              closable-chips
              label="Courriers récents"
              hint="Sélectionnez un ou plusieurs courriers"
              persistent-hint
            />
          </div>

          <div class="mb-4">
            <div class="text-caption mb-2">
              Option 2 : Saisie manuelle (si sélection vide)
            </div>
            <AppTextField
              v-model="form.correspondence_ids"
              label="IDs courriers (séparés par virgule)"
              hint="Ex: 12, 15, 18"
              persistent-hint
            />
          </div>

          <AppTextarea
            v-model="form.observations"
            label="Observations"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="createOpen = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="creating"
            @click="createSlip"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
