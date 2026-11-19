<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useApi } from '@/composables/useApi'
import { useArchives } from '@/composables/useArchives'

definePage({
  meta: { action: 'manage', subject: 'Archives' },
})

const { getJson } = useArchives()
const structureId = ref<number | null>(null)
const structures = ref<any[]>([])
const importType = ref<'records' | 'physical'>('records')
const file = ref<File | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const current = ref<any | null>(null)

const structureItems = computed(() =>
  structures.value.map(s => ({ title: s.name || s.code, value: s.id })),
)

async function loadStructures() {
  const res = await getJson('/meta/structures').catch(() => ({ data: [] }))
  structures.value = res.data || res || []
  if (!structureId.value && structures.value[0])
    structureId.value = structures.value[0].id
}

async function preview() {
  if (!structureId.value || !file.value)
    return
  loading.value = true
  error.value = null
  try {
    const form = new FormData()
    form.append('structure_id', String(structureId.value))
    form.append('import_type', importType.value)
    form.append('file', file.value)
    const { data, error: err, response } = await useApi('/archives/imports/preview').post(form).json()
    if (err.value || (response.value && response.value.status >= 400))
      throw new Error(data.value?.message || 'Prévisualisation impossible')
    current.value = data.value
  }
  catch (e: any) {
    error.value = e?.message || 'Erreur'
  }
  finally {
    loading.value = false
  }
}

async function commit() {
  if (!current.value?.id)
    return
  if (!confirm('Confirmer l’import des lignes valides ?'))
    return
  loading.value = true
  error.value = null
  try {
    const { data, error: err, response } = await useApi(`/archives/imports/${current.value.id}/commit`).post({}).json()
    if (err.value || (response.value && response.value.status >= 400))
      throw new Error(data.value?.message || 'Commit impossible')
    current.value = data.value
  }
  catch (e: any) {
    error.value = e?.message || 'Erreur'
  }
  finally {
    loading.value = false
  }
}

function onFile(files: File[] | File | null) {
  const f = Array.isArray(files) ? files[0] : files
  file.value = f || null
}

onMounted(loadStructures)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Import / reprise"
      subtitle="CSV inventaires ou dossiers numériques"
      icon="tabler-file-import"
    />

    <VAlert
      v-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
    </VAlert>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="structureId"
              :items="structureItems"
              label="Structure"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="importType"
              :items="[
                { title: 'Dossiers numériques', value: 'records' },
                { title: 'Inventaire physique', value: 'physical' },
              ]"
              label="Type"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VFileInput
              label="Fichier CSV"
              accept=".csv,text/csv"
              @update:model-value="onFile"
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              :loading="loading"
              :disabled="!structureId || !file"
              @click="preview"
            >
              Prévisualiser
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      v-if="current"
      class="parapheur-section-card"
      :loading="loading"
    >
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ current.reference }}</span>
        <VBtn
          v-if="current.status === 'preview'"
          color="primary"
          @click="commit"
        >
          Confirmer
        </VBtn>
      </VCardTitle>
      <VCardText>
        <div class="mb-3 text-medium-emphasis">
          {{ current.row_count }} lignes · {{ current.valid_count }} valides · {{ current.duplicate_count }} doublons · {{ current.error_count }} erreurs
          <template v-if="current.committed_count">
            · {{ current.committed_count }} importées
          </template>
        </div>
        <VTable density="compact">
          <thead>
            <tr>
              <th>Ligne</th>
              <th>Statut</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in (current.preview_rows || [])"
              :key="row.line"
            >
              <td>{{ row.line }}</td>
              <td>{{ row.status }}</td>
              <td>{{ row.message }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </div>
</template>
