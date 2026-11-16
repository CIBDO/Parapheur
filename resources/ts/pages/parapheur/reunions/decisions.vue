<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'
import { decisionStatusLabels, meetingStatusColor } from '@/utils/meetingsUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Meeting',
  },
})

const decisions = ref<any[]>([])
const stats = ref<any>({})
const status = ref<string | null>(null)
const lateOnly = ref(false)
const loading = ref(false)
const busyId = ref<number | null>(null)
const errorMessage = ref('')

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await $api('/meetings/decisions', {
      query: {
        status: status.value || undefined,
        late: lateOnly.value ? 1 : undefined,
      },
    })
    decisions.value = res.data ?? []
    stats.value = res.stats ?? {}
  }
  finally {
    loading.value = false
  }
}

const runDecision = async (decision: any, fn: () => Promise<unknown>) => {
  busyId.value = decision.id
  errorMessage.value = ''
  try {
    await fn()
    await load()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || e?.message || 'Action impossible'
  }
  finally {
    busyId.value = null
  }
}

const markExecuted = (decision: any) => runDecision(decision, () =>
  $api(`/meetings/${decision.meeting_id || decision.meeting?.id}/decisions/${decision.id}`, {
    method: 'PUT',
    body: { status: 'executee' },
  }),
)

const validateExecution = (decision: any) => runDecision(decision, () =>
  $api(`/meetings/${decision.meeting_id || decision.meeting?.id}/decisions/${decision.id}/validate-execution`, {
    method: 'POST',
  }),
)

const exportCsv = async () => {
  const res = await fetch('/api/meetings/decisions/export', {
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'decisions_reunions.csv'
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(load)
watch([status, lateOnly], load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Suivi des décisions"
      subtitle="Pilotage transversal des décisions de réunion"
      icon="tabler-gavel"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-download"
          @click="exportCsv"
        >
          Export CSV
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VRow class="mb-6">
      <VCol
        v-for="item in [
          { title: 'Décisions', value: stats.total },
          { title: 'Exécutées', value: stats.executee },
          { title: 'En cours', value: stats.en_cours },
          { title: 'Partielles', value: stats.partiellement_executee },
          { title: 'En retard', value: stats.en_retard },
        ]"
        :key="item.title"
        cols="6"
        md="2"
      >
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis">
              {{ item.title }}
            </div>
            <div class="text-h5">
              {{ item.value ?? 0 }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap gap-3">
        <AppSelect
          v-model="status"
          :items="Object.entries(decisionStatusLabels).map(([value, title]) => ({ value, title }))"
          label="Statut"
          clearable
          style="min-inline-size: 220px"
        />
        <VSwitch
          v-model="lateOnly"
          label="Retards uniquement"
          color="error"
        />
      </VCardText>
    </VCard>

    <VCard :loading="loading">
      <VTable>
        <thead>
          <tr>
            <th>Réf.</th>
            <th>Décision</th>
            <th>Réunion</th>
            <th>Responsable</th>
            <th>Échéance</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="d in decisions"
            :key="d.id"
          >
            <td>{{ d.reference }}</td>
            <td>{{ d.title }}</td>
            <td>
              <RouterLink :to="{ name: 'parapheur-reunions-id', params: { id: d.meeting?.id } }">
                {{ d.meeting?.reference }}
              </RouterLink>
            </td>
            <td>{{ d.assignee?.name || '—' }}</td>
            <td>{{ formatDateFr(d.due_date) }}</td>
            <td>
              <VChip
                size="small"
                :color="d.is_overdue ? 'error' : meetingStatusColor(d.status)"
                variant="tonal"
              >
                {{ d.status_label || decisionStatusLabels[d.status] }}
              </VChip>
            </td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <VBtn
                  v-if="!['executee', 'cloturee', 'annulee'].includes(d.status)"
                  size="x-small"
                  variant="tonal"
                  :loading="busyId === d.id"
                  @click="markExecuted(d)"
                >
                  Exécutée
                </VBtn>
                <VBtn
                  v-if="['executee', 'partiellement_executee'].includes(d.status) && !d.execution_validated_at"
                  size="x-small"
                  color="primary"
                  :loading="busyId === d.id"
                  @click="validateExecution(d)"
                >
                  Valider
                </VBtn>
                <VBtn
                  v-if="d.instruction?.id"
                  size="x-small"
                  variant="text"
                  :to="`/taches/instructions/${d.instruction.id}`"
                >
                  Instruction
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
