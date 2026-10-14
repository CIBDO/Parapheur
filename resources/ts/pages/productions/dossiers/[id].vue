<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatTaskDue, productionStatusColor, productionStatusLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const route = useRoute()
const router = useRouter()
const item = ref<any>(null)
const loading = ref(false)
const attaching = ref(false)
const dialog = ref(false)
const occurrenceId = ref<number | null>(null)
const candidates = ref<{ title: string; value: number }[]>([])
const errorMessage = ref('')

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    item.value = await $api(`/productions/folders/${route.params.id}`)
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Impossible de charger ce dossier.'
  }
  finally {
    loading.value = false
  }
}

const openAttach = async () => {
  dialog.value = true
  occurrenceId.value = null
  errorMessage.value = ''
  try {
    const res = await $api('/productions/occurrences', { query: { per_page: 50 } })
    const present = new Set((item.value?.occurrences || []).map((occ: any) => occ.id))
    candidates.value = (res.data ?? res)
      .filter((occ: any) => !present.has(occ.id))
      .map((occ: any) => ({
        title: `${occ.reference} — ${occ.obligation?.label || 'Occurrence'}`,
        value: occ.id,
      }))
  }
  catch (error: any) {
    candidates.value = []
    errorMessage.value = error?.data?.message || 'Impossible de charger les occurrences.'
  }
}

const attach = async () => {
  if (!occurrenceId.value)
    return
  attaching.value = true
  errorMessage.value = ''
  try {
    await $api(`/productions/folders/${route.params.id}/attach`, {
      method: 'POST',
      body: { occurrence_id: occurrenceId.value },
    })
    dialog.value = false
    await load()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Ajout impossible.'
  }
  finally {
    attaching.value = false
  }
}

onMounted(load)

const kpis = computed(() => {
  const c = item.value?.completeness || {}

  return [
    { title: 'Complétude', value: `${c.completeness ?? 0}%`, hint: 'Occurrences transmises', color: 'primary', icon: 'tabler-chart-pie' },
    { title: 'Transmises', value: c.transmitted ?? 0, hint: `sur ${c.total ?? 0}`, color: 'success', icon: 'tabler-send' },
    { title: 'En attente', value: c.pending ?? 0, hint: 'Non transmises', color: 'warning', icon: 'tabler-clock' },
    { title: 'En retard', value: c.overdue ?? 0, hint: 'Échéance dépassée', color: 'error', icon: 'tabler-alert-triangle' },
  ]
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="item?.title || 'Dossier'"
      :subtitle="item?.reference || 'Dossier consolidé'"
      icon="tabler-folder"
    >
      <template #actions>
        <VBtn
          v-if="$can('manage', 'Production')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAttach"
        >
          Ajouter une occurrence
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/productions/dossiers')"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading && !item"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="item">
      <VAlert
        v-if="errorMessage && !dialog"
        type="error"
        variant="tonal"
        class="mb-4"
        closable
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          v-for="card in kpis"
          :key="card.title"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard class="h-100">
            <VCardText class="d-flex align-center gap-4 pa-4">
              <VAvatar
                :color="card.color"
                variant="tonal"
                rounded
                size="48"
              >
                <VIcon
                  :icon="card.icon"
                  size="26"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="text-h4 font-weight-semibold lh-1 mb-1">
                  {{ card.value }}
                </div>
                <div class="text-body-2 font-weight-medium text-truncate">
                  {{ card.title }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ card.hint }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="36"
            >
              <VIcon
                icon="tabler-files"
                size="20"
              />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6">
            Occurrences du dossier
          </VCardTitle>
          <VCardSubtitle>
            Période {{ formatTaskDue(item.period_date) }}
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VTable>
          <thead>
            <tr>
              <th>Référence</th>
              <th>Obligation</th>
              <th>Échéance</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="occ in item.occurrences || []"
              :key="occ.id"
              class="cursor-pointer"
              @click="router.push(`/productions/occurrences/${occ.id}`)"
            >
              <td class="font-weight-medium">
                {{ occ.reference }}
              </td>
              <td>{{ occ.obligation?.label || '—' }}</td>
              <td>{{ formatTaskDue(occ.deadline_at) }}</td>
              <td>
                <VChip
                  size="small"
                  variant="tonal"
                  :color="productionStatusColor(occ.status)"
                >
                  {{ productionStatusLabels[occ.status] || occ.status }}
                </VChip>
              </td>
            </tr>
            <tr v-if="!(item.occurrences || []).length">
              <td
                colspan="4"
                class="text-center text-medium-emphasis py-10"
              >
                <VIcon
                  icon="tabler-file-off"
                  size="36"
                  class="mb-2 d-block mx-auto"
                />
                Aucune occurrence dans ce dossier
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>

      <VDialog
        v-model="dialog"
        max-width="520"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>Ajouter une occurrence</VCardTitle>
            <VCardSubtitle>
              Le dossier regroupe les productions, sans copier les fichiers.
            </VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-4">
            <VAlert
              v-if="errorMessage"
              type="error"
              variant="tonal"
            >
              {{ errorMessage }}
            </VAlert>
            <AppSelect
              v-model="occurrenceId"
              label="Occurrence"
              :items="candidates"
              :disabled="!candidates.length"
              no-data-text="Aucune occurrence disponible"
            />
          </VCardText>
          <VCardActions class="justify-end">
            <VBtn
              variant="text"
              @click="dialog = false"
            >
              Annuler
            </VBtn>
            <VBtn
              color="primary"
              :loading="attaching"
              :disabled="!occurrenceId"
              @click="attach"
            >
              Ajouter
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </div>
</template>
