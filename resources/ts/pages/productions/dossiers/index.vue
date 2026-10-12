<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatTaskDue } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Production',
  },
})

const router = useRouter()
const loading = ref(false)
const items = ref<any[]>([])
const total = ref(0)
const search = ref('')

const folderStatusColor = (status?: string) => {
  if (status === 'closed' || status === 'cloture')
    return 'success'
  if (status === 'open')
    return 'primary'

  return 'secondary'
}

const folderStatusLabel = (status?: string) => {
  if (status === 'open')
    return 'Ouvert'
  if (status === 'closed' || status === 'cloture')
    return 'Clôturé'

  return status || '—'
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/productions/folders', { query: { q: search.value || undefined } })
    items.value = res.data ?? res
    total.value = res.total ?? items.value.length
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Dossiers de production"
      subtitle="Regroupements d’occurrences, sans copie des fichiers"
      icon="tabler-folder"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/productions')"
        >
          Tableau de bord
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
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <AppTextField
              v-model="search"
              label="Recherche (référence, titre)"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              @click="load"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <div
        v-if="loading && !items.length"
        class="text-center py-10"
      >
        <VProgressCircular indeterminate />
      </div>
      <VTable v-else>
        <thead>
          <tr>
            <th>Référence</th>
            <th>Titre</th>
            <th>Période</th>
            <th>Occurrences</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in items"
            :key="item.id"
            class="cursor-pointer"
            @click="router.push(`/productions/dossiers/${item.id}`)"
          >
            <td class="font-weight-medium">
              {{ item.reference }}
            </td>
            <td>{{ item.title }}</td>
            <td>{{ formatTaskDue(item.period_date) }}</td>
            <td>{{ item.items_count ?? '—' }}</td>
            <td>
              <VChip
                size="small"
                variant="tonal"
                :color="folderStatusColor(item.status)"
              >
                {{ folderStatusLabel(item.status) }}
              </VChip>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td
              colspan="5"
              class="text-center text-medium-emphasis py-10"
            >
              <VIcon
                icon="tabler-folder-off"
                size="40"
                class="mb-2 d-block mx-auto"
              />
              Aucun dossier
            </td>
          </tr>
        </tbody>
      </VTable>
      <VCardText
        v-if="total"
        class="text-caption"
      >
        {{ total }} résultat(s)
      </VCardText>
    </VCard>
  </div>
</template>
