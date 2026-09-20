<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { formatTicketDateTime, formatTicketNumber, listItems } from '@/utils/ticketingUi'
import { useTicketing } from '@/composables/useTicketing'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'Ticketing', navActiveLink: 'ticketing' },
})

const route = useRoute()
const router = useRouter()
const { fetchMeta } = useTicketing()

const id = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const problem = ref<any>(null)
const teams = ref<any[]>([])
const errorMsg = ref('')
const successMsg = ref('')
const linkTicketId = ref<number | null>(null)
const ticketSearch = ref('')
const ticketResults = ref<any[]>([])
const knownErrorForm = ref({
  title: '',
  symptoms: '',
  workaround: '',
  solution: '',
  is_published: false,
})

const form = ref({
  title: '',
  description: '',
  status: 'open',
  root_cause: '',
  workaround: '',
  support_team_id: null as number | null,
})

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const data = await $api(`/ticketing/problems/${id.value}`)
    problem.value = data
    form.value = {
      title: data.title || '',
      description: data.description || '',
      status: data.status || 'open',
      root_cause: data.root_cause || '',
      workaround: data.workaround || '',
      support_team_id: data.support_team_id || data.support_team?.id || null,
    }
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Chargement impossible'
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

async function save() {
  saving.value = true
  errorMsg.value = ''
  try {
    problem.value = await $api(`/ticketing/problems/${id.value}`, {
      method: 'PUT',
      body: form.value,
    })
    successMsg.value = 'Problème mis à jour'
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Enregistrement impossible'
  }
  finally {
    saving.value = false
  }
}

async function searchTickets() {
  const q = ticketSearch.value.trim()
  if (q.length < 2) {
    ticketResults.value = []
    return
  }
  try {
    const res = await $api('/ticketing/tickets', { query: { q, per_page: 10 } })
    ticketResults.value = listItems(res)
  }
  catch {
    ticketResults.value = []
  }
}

async function linkTicket() {
  if (!linkTicketId.value)
    return
  saving.value = true
  try {
    problem.value = await $api(`/ticketing/problems/${id.value}/tickets`, {
      method: 'POST',
      body: { ticket_id: linkTicketId.value },
    })
    linkTicketId.value = null
    ticketSearch.value = ''
    ticketResults.value = []
    successMsg.value = 'Ticket lié'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Liaison impossible'
  }
  finally {
    saving.value = false
  }
}

async function unlinkTicket(ticketId: number) {
  saving.value = true
  try {
    await $api(`/ticketing/problems/${id.value}/tickets/${ticketId}`, { method: 'DELETE' })
    successMsg.value = 'Ticket délié'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Déliaison impossible'
  }
  finally {
    saving.value = false
  }
}

async function createKnownError() {
  if (!knownErrorForm.value.title.trim())
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/ticketing/problems/${id.value}/known-errors`, {
      method: 'POST',
      body: {
        title: knownErrorForm.value.title,
        symptoms: knownErrorForm.value.symptoms || null,
        workaround: knownErrorForm.value.workaround || form.value.workaround || null,
        solution: knownErrorForm.value.solution || null,
        is_published: knownErrorForm.value.is_published,
      },
    })
    knownErrorForm.value = { title: '', symptoms: '', workaround: '', solution: '', is_published: false }
    successMsg.value = 'Erreur connue créée'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Création impossible'
  }
  finally {
    saving.value = false
  }
}

async function deleteKnownError(errorId: number) {
  saving.value = true
  try {
    await $api(`/ticketing/known-errors/${errorId}`, { method: 'DELETE' })
    successMsg.value = 'Erreur connue supprimée'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Suppression impossible'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="problem?.number || 'Problème'"
      :subtitle="problem?.title || 'Détail'"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          @click="router.push({ name: 'ticketing-problemes' })"
        >
          Retour
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
        <VCard class="mb-4">
          <VCardText>
            <AppTextField
              v-model="form.title"
              label="Titre *"
              class="mb-3"
            />
            <AppSelect
              v-model="form.status"
              :items="[
                { value: 'open', title: 'Ouvert' },
                { value: 'investigating', title: 'En analyse' },
                { value: 'known_error', title: 'Erreur connue' },
                { value: 'resolved', title: 'Résolu' },
                { value: 'closed', title: 'Clôturé' },
              ]"
              label="Statut"
              class="mb-3"
            />
            <AppSelect
              v-model="form.support_team_id"
              :items="teams.map(t => ({ value: t.id, title: t.name }))"
              label="Équipe"
              clearable
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
              rows="3"
              class="mb-3"
            />
            <AppTextarea
              v-model="form.workaround"
              label="Contournement"
              rows="3"
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
              v-for="t in (problem?.tickets || [])"
              :key="t.id"
              class="d-flex justify-space-between align-center mb-2"
            >
              <RouterLink
                :to="{ name: 'ticketing-id', params: { id: t.id } }"
                class="text-primary"
              >
                {{ formatTicketNumber(t.number) }}
              </RouterLink>
              <VBtn
                size="x-small"
                variant="text"
                color="error"
                @click="unlinkTicket(t.id)"
              >
                Délier
              </VBtn>
            </div>
            <div
              v-if="!(problem?.tickets || []).length"
              class="text-medium-emphasis mb-3"
            >
              Aucun ticket.
            </div>
            <AppTextField
              v-model="ticketSearch"
              label="Rechercher un ticket"
              class="mb-2"
              hide-details
              @update:model-value="searchTickets"
            />
            <AppSelect
              v-model="linkTicketId"
              :items="ticketResults.map(t => ({
                value: t.id,
                title: `${formatTicketNumber(t.number)} — ${t.title}`,
              }))"
              label="Ticket"
              clearable
              class="mb-2"
            />
            <VBtn
              block
              color="primary"
              variant="tonal"
              :disabled="!linkTicketId"
              :loading="saving"
              @click="linkTicket"
            >
              Lier
            </VBtn>
            <div class="text-caption text-medium-emphasis mt-4">
              Créé le {{ formatTicketDateTime(problem?.created_at) }}
            </div>
          </VCardText>
        </VCard>

        <VCard class="mt-4">
          <VCardItem>
            <VCardTitle class="text-h6">
              Erreurs connues
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div
              v-for="ke in (problem?.known_errors || problem?.knownErrors || [])"
              :key="ke.id"
              class="mb-3 pa-2 rounded"
              style="background: rgba(var(--v-theme-on-surface), 0.04)"
            >
              <div class="d-flex justify-space-between">
                <div class="font-weight-medium">
                  {{ ke.title }}
                </div>
                <VBtn
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="deleteKnownError(ke.id)"
                >
                  Suppr.
                </VBtn>
              </div>
              <div
                v-if="ke.workaround"
                class="text-caption mt-1"
              >
                Contournement : {{ ke.workaround }}
              </div>
              <VChip
                size="x-small"
                class="mt-1"
                :color="ke.is_published ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ ke.is_published ? 'Publiée' : 'Brouillon' }}
              </VChip>
            </div>
            <VDivider class="my-3" />
            <AppTextField
              v-model="knownErrorForm.title"
              label="Titre *"
              class="mb-2"
              hide-details
            />
            <AppTextarea
              v-model="knownErrorForm.symptoms"
              label="Symptômes"
              rows="2"
              class="mb-2"
              hide-details
            />
            <AppTextarea
              v-model="knownErrorForm.workaround"
              label="Contournement"
              rows="2"
              class="mb-2"
              hide-details
            />
            <VCheckbox
              v-model="knownErrorForm.is_published"
              label="Publier"
              hide-details
              class="mb-2"
            />
            <VBtn
              block
              color="primary"
              variant="tonal"
              :disabled="!knownErrorForm.title.trim()"
              :loading="saving"
              @click="createKnownError"
            >
              Ajouter erreur connue
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
