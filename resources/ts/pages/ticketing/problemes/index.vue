<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { listItems } from '@/utils/listItems'
import { formatTicketDateTime } from '@/utils/ticketingUi'
import { useTicketing } from '@/composables/useTicketing'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const router = useRouter()
const { fetchMeta } = useTicketing()

const loading = ref(true)
const saving = ref(false)
const problems = ref<any[]>([])
const teams = ref<any[]>([])
const errorMsg = ref('')
const successMsg = ref('')
const dialog = ref(false)
const form = ref({
  title: '',
  description: '',
  workaround: '',
  root_cause: '',
  support_team_id: null as number | null,
})

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await $api('/ticketing/problems', { query: { per_page: 50 } })
    problems.value = listItems(res)
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger les problèmes'
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  try {
    const meta = await fetchMeta()
    teams.value = meta?.teams || []
  }
  catch { /* ignore */ }
})

async function createProblem() {
  if (!form.value.title.trim())
    return
  saving.value = true
  errorMsg.value = ''
  try {
    const created = await $api('/ticketing/problems', {
      method: 'POST',
      body: {
        title: form.value.title,
        description: form.value.description || null,
        workaround: form.value.workaround || null,
        root_cause: form.value.root_cause || null,
        support_team_id: form.value.support_team_id,
      },
    })
    dialog.value = false
    form.value = { title: '', description: '', workaround: '', root_cause: '', support_team_id: null }
    if (created?.id) {
      await router.push({ name: 'ticketing-problemes-id', params: { id: created.id } })
      return
    }
    successMsg.value = 'Problème créé'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Création impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Problèmes"
      subtitle="Analyse de cause racine et liens avec les incidents"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Nouveau problème
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
          { title: 'N°', key: 'number' },
          { title: 'Titre', key: 'title' },
          { title: 'Statut', key: 'status' },
          { title: 'Équipe', key: 'support_team' },
          { title: 'Créé le', key: 'created_at' },
        ]"
        :items="problems"
        :loading="loading"
        class="text-no-wrap"
        @click:row="(_: any, { item }: any) => $router.push({ name: 'ticketing-problemes-id', params: { id: item.id } })"
      >
        <template #item.number="{ item }">
          <RouterLink
            :to="{ name: 'ticketing-problemes-id', params: { id: item.id } }"
            class="text-primary font-weight-medium"
            @click.stop
          >
            {{ item.number }}
          </RouterLink>
        </template>
        <template #item.support_team="{ item }">
          {{ item.support_team?.name || item.supportTeam?.name || '—' }}
        </template>
        <template #item.created_at="{ item }">
          {{ formatTicketDateTime(item.created_at) }}
        </template>
        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            Aucun problème enregistré.
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="640"
    >
      <VCard>
        <VCardTitle>Nouveau problème</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="form.title"
            label="Titre *"
            class="mb-3"
          />
          <AppTextarea
            v-model="form.description"
            label="Description"
            rows="3"
            class="mb-3"
          />
          <AppTextarea
            v-model="form.root_cause"
            label="Cause racine"
            rows="2"
            class="mb-3"
          />
          <AppTextarea
            v-model="form.workaround"
            label="Contournement"
            rows="2"
            class="mb-3"
          />
          <AppSelect
            v-model="form.support_team_id"
            :items="teams.map(t => ({ value: t.id, title: t.name }))"
            label="Équipe"
            clearable
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
            @click="createProblem"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
