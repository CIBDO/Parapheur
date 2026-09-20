<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { formatTicketDateTime, formatTicketNumber } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const route = useRoute()
const router = useRouter()

const id = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const article = ref<any>(null)
const errorMsg = ref('')
const successMsg = ref('')

const form = ref({
  title: '',
  summary: '',
  body: '',
  status: 'draft',
})

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const data = await $api(`/ticketing/knowledge/${id.value}`)
    article.value = data
    form.value = {
      title: data.title || '',
      summary: data.summary || '',
      body: data.body || '',
      status: String(data.status || 'draft').toLowerCase(),
    }
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Chargement impossible'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function save() {
  saving.value = true
  errorMsg.value = ''
  try {
    article.value = await $api(`/ticketing/knowledge/${id.value}`, {
      method: 'PUT',
      body: form.value,
    })
    successMsg.value = 'Article mis à jour'
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Enregistrement impossible'
  }
  finally {
    saving.value = false
  }
}

async function publish() {
  saving.value = true
  errorMsg.value = ''
  try {
    article.value = await $api(`/ticketing/knowledge/${id.value}/publish`, {
      method: 'POST',
      body: {},
    })
    form.value.status = 'published'
    successMsg.value = 'Article publié'
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Publication impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="article?.title || 'Article'"
      :subtitle="article?.slug || 'Base de connaissances'"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          @click="router.push({ name: 'ticketing-connaissances' })"
        >
          Retour
        </VBtn>
        <VBtn
          v-if="form.status !== 'published'"
          color="success"
          variant="tonal"
          class="me-2"
          :loading="saving"
          @click="publish"
        >
          Publier
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="save"
        >
          Enregistrer
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>
    <VAlert
      v-if="successMsg"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMsg = ''"
    >
      {{ successMsg }}
    </VAlert>

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <VRow
      v-else
      dense
    >
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardText>
            <AppTextField
              v-model="form.title"
              label="Titre *"
              class="mb-3"
            />
            <AppSelect
              v-model="form.status"
              :items="[
                { value: 'draft', title: 'Brouillon' },
                { value: 'published', title: 'Publié' },
              ]"
              label="Statut"
              class="mb-3"
            />
            <AppTextarea
              v-model="form.summary"
              label="Résumé"
              rows="2"
              class="mb-3"
            />
            <AppTextarea
              v-model="form.body"
              label="Contenu"
              rows="12"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="text-h6">
              Tickets liés
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div
              v-for="t in (article?.tickets || [])"
              :key="t.id"
              class="mb-2"
            >
              <RouterLink
                :to="{ name: 'ticketing-id', params: { id: t.id } }"
                class="text-primary"
              >
                {{ formatTicketNumber(t.number) }}
              </RouterLink>
              <div class="text-caption">
                {{ t.title }}
              </div>
            </div>
            <div
              v-if="!(article?.tickets || []).length"
              class="text-medium-emphasis"
            >
              Aucun ticket lié.
            </div>
            <div class="text-caption text-medium-emphasis mt-4">
              Mis à jour {{ formatTicketDateTime(article?.updated_at) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
