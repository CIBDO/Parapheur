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
const saving = ref(false)
const items = ref<any[]>([])
const total = ref(0)
const search = ref('')
const errorMessage = ref('')
const dialog = ref(false)
const form = ref({ title: '', period_date: '' })

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
  errorMessage.value = ''
  try {
    const res = await $api('/productions/folders', { query: { q: search.value || undefined } })
    items.value = res.data ?? res
    total.value = res.total ?? items.value.length
  }
  catch (error: any) {
    items.value = []
    total.value = 0
    errorMessage.value = error?.data?.message || 'Impossible de charger les dossiers.'
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = { title: '', period_date: '' }
  errorMessage.value = ''
  dialog.value = true
}

const createFolder = async () => {
  saving.value = true
  errorMessage.value = ''
  try {
    const created = await $api('/productions/folders', {
      method: 'POST',
      body: {
        title: form.value.title,
        period_date: form.value.period_date || null,
      },
    })
    dialog.value = false
    await router.push(`/productions/dossiers/${created.id}`)
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message
      || error?.data?.errors?.title?.[0]
      || 'Création impossible.'
  }
  finally {
    saving.value = false
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
          v-if="$can('manage', 'Production')"
          color="primary"
          prepend-icon="tabler-folder-plus"
          @click="openCreate"
        >
          Nouveau dossier
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
              <div
                v-if="$can('manage', 'Production')"
                class="mt-3"
              >
                <VBtn
                  color="primary"
                  prepend-icon="tabler-folder-plus"
                  @click="openCreate"
                >
                  Créer le premier dossier
                </VBtn>
              </div>
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

    <VDialog
      v-model="dialog"
      max-width="480"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Nouveau dossier</VCardTitle>
          <VCardSubtitle>
            Un titre suffit. La date précise la période couverte.
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
          <AppTextField
            v-model="form.title"
            label="Titre"
            autofocus
          />
          <AppTextField
            v-model="form.period_date"
            label="Période"
            type="date"
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
            :loading="saving"
            :disabled="!form.title.trim()"
            @click="createFolder"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
