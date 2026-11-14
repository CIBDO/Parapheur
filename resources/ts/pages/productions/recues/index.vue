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
const route = useRoute()
const loading = ref(false)
const acting = ref(false)
const errorMessage = ref('')
const items = ref<any[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const page = ref(1)
const selected = ref<any>(null)
const opinion = ref('favorable')
const opinionComment = ref('')
const filters = ref({
  today: true,
  unread: false,
  q: '',
})

const opinionLabels: Record<string, string> = {
  favorable: 'Favorable',
  reserve: 'Avec réserve',
  a_revoir: 'À revoir',
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await $api('/productions/recues', {
      query: {
        today: filters.value.today ? 1 : undefined,
        unread: filters.value.unread ? 1 : undefined,
        q: filters.value.q || undefined,
        page: page.value,
        per_page: 20,
      },
    })
    items.value = res.data ?? res
    meta.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total ?? items.value.length,
    }

    const txId = route.query.transmission
    if (txId) {
      await openTransmission(Number(txId))
    }
  }
  catch (error: any) {
    items.value = []
    errorMessage.value = error?.data?.message || 'Impossible de charger les reçues.'
  }
  finally {
    loading.value = false
  }
}

const openTransmission = async (id: number) => {
  acting.value = true
  errorMessage.value = ''
  try {
    selected.value = await $api(`/productions/recues/${id}`)
    opinion.value = selected.value?.my_recipient?.opinion || 'favorable'
    opinionComment.value = selected.value?.my_recipient?.opinion_comment || ''
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Impossible d’ouvrir cette réception.'
  }
  finally {
    acting.value = false
  }
}

const submitOpinion = async () => {
  if (!selected.value?.id)
    return
  acting.value = true
  errorMessage.value = ''
  try {
    await $api(`/productions/recues/${selected.value.id}/opinion`, {
      method: 'POST',
      body: {
        opinion: opinion.value,
        comment: opinionComment.value || null,
      },
    })
    await openTransmission(selected.value.id)
    await load()
  }
  catch (error: any) {
    errorMessage.value = error?.data?.message || 'Enregistrement de l’avis impossible.'
  }
  finally {
    acting.value = false
  }
}

const changePage = (value: number) => {
  page.value = value
  load()
}

watch(() => route.query.transmission, (id) => {
  if (id)
    openTransmission(Number(id))
})

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Reçues"
      subtitle="Documents transmis à votre attention — consultez et donnez un avis si besoin"
      icon="tabler-inbox"
    >
      <template #actions>
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
          prepend-icon="tabler-folder"
          @click="router.push('/productions/dossiers')"
        >
          Dossier du jour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="warning"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VRow dense>
      <VCol
        cols="12"
        lg="5"
      >
        <VCard>
          <VCardText>
            <VRow dense>
              <VCol cols="12">
                <AppTextField
                  v-model="filters.q"
                  label="Rechercher"
                  prepend-inner-icon="tabler-search"
                  clearable
                  @keyup.enter="page = 1; load()"
                />
              </VCol>
              <VCol cols="6">
                <VSwitch
                  v-model="filters.today"
                  label="Aujourd’hui"
                  color="primary"
                  hide-details
                  density="compact"
                  @update:model-value="page = 1; load()"
                />
              </VCol>
              <VCol cols="6">
                <VSwitch
                  v-model="filters.unread"
                  label="Non lues"
                  color="primary"
                  hide-details
                  density="compact"
                  @update:model-value="page = 1; load()"
                />
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
          <VList
            v-else
            lines="two"
          >
            <VListItem
              v-for="item in items"
              :key="item.id"
              :active="selected?.id === item.id"
              @click="openTransmission(item.id)"
            >
              <VListItemTitle>
                {{ item.occurrence?.obligation?.label || item.reference }}
              </VListItemTitle>
              <VListItemSubtitle>
                {{ item.occurrence?.responsible?.name || '—' }}
                · {{ formatTaskDue(item.transmitted_at) }}
              </VListItemSubtitle>
              <template #append>
                <VChip
                  v-if="item.recipients?.[0]?.opinion"
                  size="x-small"
                  color="success"
                  variant="tonal"
                >
                  Avis
                </VChip>
                <VChip
                  v-else-if="!item.recipients?.[0]?.viewed_at"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                >
                  Nouveau
                </VChip>
              </template>
            </VListItem>
            <div
              v-if="!items.length"
              class="text-center py-10 text-medium-emphasis"
            >
              Aucune réception
            </div>
          </VList>
          <VCardText
            v-if="meta.total"
            class="d-flex align-center justify-space-between"
          >
            <span class="text-caption">{{ meta.total }}</span>
            <VPagination
              v-if="meta.last_page > 1"
              :model-value="meta.current_page"
              :length="meta.last_page"
              total-visible="4"
              @update:model-value="changePage"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="7"
      >
        <VCard v-if="selected">
          <VCardItem>
            <VCardTitle>
              {{ selected.occurrence?.obligation?.label || selected.reference }}
            </VCardTitle>
            <VCardSubtitle>
              {{ selected.occurrence?.reference }} · Envoyé par {{ selected.transmitter?.name || '—' }}
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="text-body-2 mb-3">
              Structure : {{ selected.occurrence?.structure?.name || '—' }}
            </div>

            <div class="text-subtitle-2 mb-2">
              Documents
            </div>
            <VList
              v-if="selected.files?.length"
              density="compact"
              class="mb-4"
            >
              <VListItem
                v-for="file in selected.files"
                :key="file.id"
                :title="file.production_file?.document?.title || file.production_file?.document?.object || `Fichier #${file.id}`"
              />
            </VList>
            <div
              v-else
              class="text-medium-emphasis mb-4"
            >
              Aucun fichier
            </div>

            <VBtn
              variant="tonal"
              class="mb-4"
              prepend-icon="tabler-external-link"
              @click="router.push(`/productions/occurrences/${selected.occurrence?.id}`)"
            >
              Ouvrir la fiche complète
            </VBtn>

            <VDivider class="mb-4" />

            <div class="text-subtitle-2 mb-2">
              Votre avis
            </div>
            <div class="d-flex flex-column gap-3">
              <AppSelect
                v-model="opinion"
                label="Avis"
                :items="Object.entries(opinionLabels).map(([value, title]) => ({ title, value }))"
              />
              <AppTextarea
                v-model="opinionComment"
                label="Commentaire (optionnel)"
                rows="3"
              />
              <div>
                <VBtn
                  color="primary"
                  :loading="acting"
                  @click="submitOpinion"
                >
                  Enregistrer l’avis
                </VBtn>
              </div>
              <div
                v-if="selected.my_recipient?.opinion_at"
                class="text-caption text-medium-emphasis"
              >
                Dernier avis :
                {{ opinionLabels[selected.my_recipient.opinion] || selected.my_recipient.opinion }}
                le {{ new Date(selected.my_recipient.opinion_at).toLocaleString('fr-FR') }}
              </div>
            </div>
          </VCardText>
        </VCard>

        <VCard
          v-else
          class="pa-10 text-center text-medium-emphasis"
        >
          Sélectionnez une réception pour consulter et donner un avis.
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
