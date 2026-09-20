<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { formatTicketDateTime, listItems } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const articles = ref<any[]>([])
const errorMsg = ref('')
const successMsg = ref('')
const dialog = ref(false)
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
    const res = await $api('/ticketing/knowledge', { query: { per_page: 50 } })
    articles.value = listItems(res)
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger la base de connaissances'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function createArticle() {
  if (!form.value.title.trim())
    return
  saving.value = true
  errorMsg.value = ''
  try {
    const created = await $api('/ticketing/knowledge', {
      method: 'POST',
      body: {
        title: form.value.title,
        summary: form.value.summary || null,
        body: form.value.body || null,
        status: form.value.status,
      },
    })
    dialog.value = false
    form.value = { title: '', summary: '', body: '', status: 'draft' }
    if (created?.id) {
      await router.push({ name: 'ticketing-connaissances-id', params: { id: created.id } })
      return
    }
    successMsg.value = 'Article créé'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Création impossible'
  }
  finally {
    saving.value = false
  }
}

async function publishArticle(item: any) {
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/ticketing/knowledge/${item.id}/publish`, { method: 'POST', body: {} })
    successMsg.value = 'Article publié'
    await load()
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
      title="Base de connaissances"
      subtitle="Articles, contournements et solutions capitalisées"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Nouvel article
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMsg"
      type="warning"
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

    <VCard>
      <VDataTable
        :headers="[
          { title: 'Titre', key: 'title' },
          { title: 'Statut', key: 'status' },
          { title: 'Catégorie', key: 'category' },
          { title: 'Mis à jour', key: 'updated_at' },
          { title: 'Actions', key: 'actions', sortable: false },
        ]"
        :items="articles"
        :loading="loading"
        @click:row="(_: any, { item }: any) => router.push({ name: 'ticketing-connaissances-id', params: { id: item.id } })"
      >
        <template #item.title="{ item }">
          <RouterLink
            :to="{ name: 'ticketing-connaissances-id', params: { id: item.id } }"
            class="text-primary font-weight-medium"
            @click.stop
          >
            {{ item.title }}
          </RouterLink>
        </template>
        <template #item.category="{ item }">
          {{ item.category?.name || item.knowledge_category?.name || '—' }}
        </template>
        <template #item.updated_at="{ item }">
          {{ formatTicketDateTime(item.updated_at) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="String(item.status || '').toLowerCase() !== 'published'"
            size="small"
            variant="tonal"
            color="success"
            :loading="saving"
            @click.stop="publishArticle(item)"
          >
            Publier
          </VBtn>
        </template>
        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            Aucun article.
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="720"
    >
      <VCard>
        <VCardTitle>Nouvel article</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="form.title"
            label="Titre *"
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
            rows="6"
            class="mb-3"
          />
          <AppSelect
            v-model="form.status"
            :items="[
              { value: 'draft', title: 'Brouillon' },
              { value: 'published', title: 'Publié' },
            ]"
            label="Statut"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
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
            @click="createArticle"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
