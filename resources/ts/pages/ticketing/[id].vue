<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/listItems'
import {
  formatTicketDateTime,
  formatTicketNumber,
  slaBadge,
  teamAgentSelectItems,
  ticketActorRoleLabel,
  ticketPriorityColor,
  ticketPriorityLabel,
  ticketStatusColor,
  ticketStatusLabel,
} from '@/utils/ticketingUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Ticketing',
    navActiveLink: 'ticketing',
  },
})

const route = useRoute()
const router = useRouter()
const {
  ticket,
  fetchTicket,
  fetchMeta,
  takeCharge,
  assign,
  resolve,
  close,
  reopen,
  wait,
  escalate,
  cancel,
  addComment,
  fetchComments,
  fetchTimeline,
  uploadAttachment,
  downloadAttachment,
  fetchWorklogs,
  addWorklog,
  submitSatisfaction,
  loading,
} = useTicketing()

const id = computed(() => Number(route.params.id))
const comments = ref<any[]>([])
const timeline = ref<any[]>([])
const worklogs = ref<any[]>([])
const teams = ref<any[]>([])
const users = ref<any[]>([])
const problems = ref<any[]>([])
const applications = ref<any[]>([])
const assets = ref<any[]>([])
const relations = ref<any[]>([])
const solutions = ref<any[]>([])
const tasks = ref<any[]>([])
const costs = ref<any[]>([])
const costsTotal = ref(0)
const actors = ref<any[]>([])
const observerIds = ref<number[]>([])
const taskForm = ref({ title: '', content: '', status: 'todo' })
const costForm = ref({ name: '', amount: 0, cost_type: '' })
const solutionRefuseReason = ref('')
const approvalRefuseReason = ref('')
const relationForm = ref({
  related_ticket_id: null as number | null,
  relation_type: 'related',
  search: '',
})
const relatedSearchResults = ref<any[]>([])
const activeTab = ref('comments')
const errorMsg = ref('')
const successMsg = ref('')
const actionLoading = ref(false)

const commentBody = ref('')
const commentInternal = ref(false)
const resolveDialog = ref(false)
const resolveSummary = ref('')
const reopenDialog = ref(false)
const reopenReason = ref('')
const waitDialog = ref(false)
const waitForm = ref({ status: 'EN_ATTENTE_DEMANDEUR', comment: '' })
const escalateDialog = ref(false)
const escalateForm = ref({ to_team_id: null as number | null, to_user_id: null as number | null, reason: '' })
const cancelDialog = ref(false)
const cancelReason = ref('')
const problemDialog = ref(false)
const selectedProblemId = ref<number | null>(null)
const kbDialog = ref(false)
const kbForm = ref({ title: '', summary: '', body: '' })
const transferDialog = ref(false)
const transferForm = ref({ support_team_id: null as number | null, assignee_id: null as number | null, comment: '' })
const mergeDialog = ref(false)
const mergeTargetId = ref<number | null>(null)
const mergeSearch = ref('')
const mergeResults = ref<any[]>([])
const linkedKnowledge = ref<any[]>([])
const assetIdsForm = ref<number[]>([])
const assignDialog = ref(false)
const assignForm = ref({ support_team_id: null as number | null, assignee_id: null as number | null, comment: '' })
const worklogForm = ref({ minutes: 15, note: '' })
const satisfactionForm = ref({ score: 4, comment: '' })
const uploadFile = ref<File | null>(null)

const teamItems = computed(() => teams.value.map(t => ({ value: t.id, title: t.name })))
const userItems = computed(() => users.value.map(u => ({ value: u.id, title: u.name })))

const assignAgentItems = computed(() => teamAgentSelectItems(teams.value, assignForm.value.support_team_id))
const transferAgentItems = computed(() => teamAgentSelectItems(teams.value, transferForm.value.support_team_id))
const escalateAgentItems = computed(() => teamAgentSelectItems(teams.value, escalateForm.value.to_team_id))

const availableActions = computed(() => ticket.value?.available_actions || [])
function canAction(action: string) {
  return availableActions.value.includes(action)
}

watch(() => assignForm.value.support_team_id, () => {
  if (assignForm.value.assignee_id && !assignAgentItems.value.some(a => a.value === Number(assignForm.value.assignee_id)))
    assignForm.value.assignee_id = null
})
watch(() => transferForm.value.support_team_id, () => {
  if (transferForm.value.assignee_id && !transferAgentItems.value.some(a => a.value === Number(transferForm.value.assignee_id)))
    transferForm.value.assignee_id = null
})
watch(() => escalateForm.value.to_team_id, () => {
  if (escalateForm.value.to_user_id && !escalateAgentItems.value.some(a => a.value === Number(escalateForm.value.to_user_id)))
    escalateForm.value.to_user_id = null
})

function openAssignDialog() {
  assignForm.value = {
    support_team_id: ticket.value?.support_team_id || null,
    assignee_id: null,
    comment: '',
  }
  assignDialog.value = true
}

function openTransferDialog() {
  transferForm.value = {
    support_team_id: ticket.value?.support_team_id || null,
    assignee_id: null,
    comment: '',
  }
  transferDialog.value = true
}

function openEscalateDialog() {
  escalateForm.value = {
    to_team_id: ticket.value?.support_team_id || null,
    to_user_id: null,
    reason: '',
  }
  escalateDialog.value = true
}

const canShowSatisfaction = computed(() =>
  ticket.value && ['RESOLU', 'CLOTURE', 'A_VALIDER'].includes(ticket.value.status) && !ticket.value.satisfaction,
)

onMounted(async () => {
  await refresh()
  try {
    const meta = await fetchMeta()
    teams.value = meta?.teams || []
  }
  catch { /* ignore */ }
  try {
    const res = await $api('/meta/users')
    users.value = listItems(res).length ? listItems(res) : (Array.isArray(res) ? res : [])
  }
  catch {
    users.value = []
  }
  try {
    const res = await $api('/ticketing/problems', { query: { per_page: 100 } })
    problems.value = listItems(res)
  }
  catch {
    problems.value = []
  }
  try {
    const [apps, ast] = await Promise.all([
      $api('/ticketing/applications', { query: { per_page: 100 } }),
      $api('/ticketing/assets', { query: { per_page: 100 } }),
    ])
    applications.value = listItems(apps)
    assets.value = listItems(ast)
  }
  catch {
    applications.value = []
    assets.value = []
  }
})

async function refresh() {
  errorMsg.value = ''
  await fetchTicket(id.value)
  const [c, t, w, rel, sol, tsk, cst, act] = await Promise.all([
    fetchComments(id.value),
    fetchTimeline(id.value),
    fetchWorklogs(id.value),
    $api(`/ticketing/tickets/${id.value}/relations`).catch(() => ({ data: [] })),
    $api(`/ticketing/tickets/${id.value}/solutions`).catch(() => ({ data: [] })),
    $api(`/ticketing/tickets/${id.value}/tasks`).catch(() => ({ data: [] })),
    $api(`/ticketing/tickets/${id.value}/costs`).catch(() => ({ data: [], total: 0 })),
    $api(`/ticketing/tickets/${id.value}/actors`).catch(() => ({ data: [] })),
  ])
  comments.value = Array.isArray(c) ? c : (c?.data || [])
  timeline.value = t?.events || []
  worklogs.value = Array.isArray(w) ? w : (w?.data || [])
  relations.value = listItems(rel).length ? listItems(rel) : (Array.isArray(rel?.data) ? rel.data : [])
  solutions.value = Array.isArray(sol) ? sol : (sol?.data || [])
  tasks.value = Array.isArray(tsk) ? tsk : (tsk?.data || [])
  costs.value = Array.isArray(cst) ? cst : (cst?.data || [])
  costsTotal.value = Number(cst?.total || 0)
  actors.value = Array.isArray(act) ? act : (act?.data || [])
  observerIds.value = actors.value.filter((a: any) => a.role === 'observer').map((a: any) => a.user_id || a.user?.id)
  assetIdsForm.value = (ticket.value?.assets || []).map((a: any) => a.id)
  if (!assetIdsForm.value.length && ticket.value?.asset_id)
    assetIdsForm.value = [ticket.value.asset_id]
  try {
    linkedKnowledge.value = ticket.value?.knowledge_articles || []
  }
  catch {
    linkedKnowledge.value = []
  }
}

async function runAction(fn: () => Promise<any>) {
  actionLoading.value = true
  errorMsg.value = ''
  try {
    await fn()
    await refresh()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Action impossible'
  }
  finally {
    actionLoading.value = false
  }
}

async function doTakeCharge() {
  await runAction(() => takeCharge(id.value))
}

async function doResolve() {
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/solutions`, {
      method: 'POST',
      body: {
        content: resolveSummary.value,
        solution_type: 'solution',
        await_validation: true,
      },
    })
    resolveDialog.value = false
    resolveSummary.value = ''
  })
}

async function acceptSolution(solutionId: number) {
  await runAction(() => $api(`/ticketing/tickets/${id.value}/solutions/${solutionId}/accept`, { method: 'POST', body: {} }))
}

async function refuseSolution(solutionId: number) {
  if (!solutionRefuseReason.value.trim()) {
    errorMsg.value = 'Motif de refus requis.'

    return
  }
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/solutions/${solutionId}/refuse`, {
      method: 'POST',
      body: { reason: solutionRefuseReason.value },
    })
    solutionRefuseReason.value = ''
  })
}

async function acceptApproval() {
  await runAction(() => $api(`/ticketing/tickets/${id.value}/approvals/accept`, { method: 'POST', body: {} }))
}

async function refuseApproval() {
  if (!approvalRefuseReason.value.trim()) {
    errorMsg.value = 'Motif de refus requis.'

    return
  }
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/approvals/refuse`, {
      method: 'POST',
      body: { reason: approvalRefuseReason.value },
    })
    approvalRefuseReason.value = ''
  })
}

async function saveObservers() {
  await runAction(() => $api(`/ticketing/tickets/${id.value}/actors/observers`, {
    method: 'PUT',
    body: { observer_ids: observerIds.value },
  }))
}

async function addTask() {
  if (!taskForm.value.title.trim())
    return
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/tasks`, { method: 'POST', body: taskForm.value })
    taskForm.value = { title: '', content: '', status: 'todo' }
  })
}

async function updateTaskStatus(taskId: number, status: string) {
  await runAction(() => $api(`/ticketing/tickets/${id.value}/tasks/${taskId}`, {
    method: 'PUT',
    body: { status },
  }))
}

async function addCost() {
  if (!costForm.value.name.trim() || !costForm.value.amount)
    return
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/costs`, { method: 'POST', body: costForm.value })
    costForm.value = { name: '', amount: 0, cost_type: '' }
  })
}

async function doClose() {
  await runAction(() => close(id.value))
}

async function doReopen() {
  await runAction(async () => {
    await reopen(id.value, { reason: reopenReason.value })
    reopenDialog.value = false
    reopenReason.value = ''
  })
}

async function doWait() {
  await runAction(async () => {
    await wait(id.value, {
      status: waitForm.value.status,
      comment: waitForm.value.comment || undefined,
    })
    waitDialog.value = false
    waitForm.value = { status: 'EN_ATTENTE_DEMANDEUR', comment: '' }
  })
}

async function doEscalate() {
  if (escalateForm.value.to_user_id
    && !escalateAgentItems.value.some(a => a.value === Number(escalateForm.value.to_user_id))) {
    errorMsg.value = 'Choisissez un agent membre de l’équipe sélectionnée.'

    return
  }
  await runAction(async () => {
    await escalate(id.value, {
      to_team_id: escalateForm.value.to_team_id ? Number(escalateForm.value.to_team_id) : null,
      to_user_id: escalateForm.value.to_user_id ? Number(escalateForm.value.to_user_id) : null,
      reason: escalateForm.value.reason || undefined,
    })
    escalateDialog.value = false
  })
}

async function doCancel() {
  await runAction(async () => {
    await cancel(id.value, { reason: cancelReason.value })
    cancelDialog.value = false
    cancelReason.value = ''
  })
}

async function toggleMajorIncident() {
  if (!ticket.value)
    return
  const next = !ticket.value.is_major_incident
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}`, {
      method: 'PUT',
      body: { is_major_incident: next },
    })
    successMsg.value = next
      ? 'Marqué comme incident majeur'
      : 'Incident majeur retiré'
  })
}

async function linkToProblem() {
  if (!selectedProblemId.value)
    return
  await runAction(async () => {
    await $api(`/ticketing/problems/${selectedProblemId.value}/tickets`, {
      method: 'POST',
      body: { ticket_id: id.value },
    })
    problemDialog.value = false
    successMsg.value = 'Ticket lié au problème'
  })
}

async function createKbFromResolution() {
  const title = kbForm.value.title || `Solution — ${ticket.value?.title || ''}`
  await runAction(async () => {
    const article = await $api('/ticketing/knowledge', {
      method: 'POST',
      body: {
        title,
        summary: kbForm.value.summary || ticket.value?.resolution_summary || null,
        body: kbForm.value.body || ticket.value?.resolution_summary || ticket.value?.description || null,
        status: 'draft',
      },
    })
    if (article?.id) {
      await $api(`/ticketing/knowledge/${article.id}/tickets`, {
        method: 'POST',
        body: { ticket_id: id.value },
      })
    }
    kbDialog.value = false
    kbForm.value = { title: '', summary: '', body: '' }
    successMsg.value = 'Article de connaissance créé (brouillon)'
  })
}

async function saveCmdbLinks() {
  if (!ticket.value)
    return
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}`, {
      method: 'PUT',
      body: {
        application_id: ticket.value!.application_id || null,
        asset_id: ticket.value!.asset_id || null,
      },
    })
    successMsg.value = 'Application / actif mis à jour'
  })
}

async function searchRelatedTickets() {
  const q = relationForm.value.search.trim()
  if (q.length < 2) {
    relatedSearchResults.value = []
    return
  }
  try {
    const res = await $api('/ticketing/tickets', { query: { q, per_page: 10 } })
    relatedSearchResults.value = listItems(res).filter((t: any) => t.id !== id.value)
  }
  catch {
    relatedSearchResults.value = []
  }
}

async function linkRelatedTicket() {
  if (!relationForm.value.related_ticket_id)
    return
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/relations`, {
      method: 'POST',
      body: {
        related_ticket_id: relationForm.value.related_ticket_id,
        relation_type: relationForm.value.relation_type,
      },
    })
    relationForm.value = { related_ticket_id: null, relation_type: 'related', search: '' }
    relatedSearchResults.value = []
    successMsg.value = 'Ticket lié'
  })
}

async function unlinkRelation(relationId: number) {
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/relations/${relationId}`, { method: 'DELETE' })
    successMsg.value = 'Relation supprimée'
  })
}

function otherTicket(rel: any) {
  if (rel.related_ticket_id === id.value)
    return rel.ticket
  return rel.related_ticket || rel.relatedTicket
}

async function doAssign() {
  if (assignForm.value.assignee_id
    && !assignAgentItems.value.some(a => a.value === Number(assignForm.value.assignee_id))) {
    errorMsg.value = 'Choisissez un agent membre de l’équipe sélectionnée.'

    return
  }
  await runAction(async () => {
    await assign(id.value, {
      ...assignForm.value,
      support_team_id: assignForm.value.support_team_id ? Number(assignForm.value.support_team_id) : null,
      assignee_id: assignForm.value.assignee_id ? Number(assignForm.value.assignee_id) : null,
    })
    assignDialog.value = false
  })
}

async function doTransfer() {
  if (transferForm.value.assignee_id
    && !transferAgentItems.value.some(a => a.value === Number(transferForm.value.assignee_id))) {
    errorMsg.value = 'Choisissez un agent membre de l’équipe sélectionnée.'

    return
  }
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/transfer`, {
      method: 'POST',
      body: {
        ...transferForm.value,
        support_team_id: transferForm.value.support_team_id ? Number(transferForm.value.support_team_id) : null,
        assignee_id: transferForm.value.assignee_id ? Number(transferForm.value.assignee_id) : null,
      },
    })
    transferDialog.value = false
    successMsg.value = 'Ticket transféré'
  })
}

async function searchMergeTargets() {
  const q = mergeSearch.value.trim()
  if (q.length < 2) {
    mergeResults.value = []

    return
  }
  try {
    const res = await $api('/ticketing/tickets', { query: { q, per_page: 10 } })
    mergeResults.value = listItems(res).filter((t: any) => t.id !== id.value)
  }
  catch {
    mergeResults.value = []
  }
}

async function doMerge() {
  if (!mergeTargetId.value)
    return
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}/merge`, {
      method: 'POST',
      body: { target_ticket_id: mergeTargetId.value },
    })
    mergeDialog.value = false
    successMsg.value = 'Ticket fusionné'
    await router.push({ name: 'ticketing-id', params: { id: mergeTargetId.value } })
  })
}

async function saveAssets() {
  await runAction(async () => {
    await $api(`/ticketing/tickets/${id.value}`, {
      method: 'PUT',
      body: { asset_ids: assetIdsForm.value },
    })
  })
}

async function postComment() {
  if (!commentBody.value.trim())
    return
  await runAction(async () => {
    await addComment(id.value, { body: commentBody.value, is_internal: commentInternal.value })
    commentBody.value = ''
    commentInternal.value = false
  })
}

async function doUpload() {
  const f = Array.isArray(uploadFile.value) ? uploadFile.value[0] : uploadFile.value
  if (!f)
    return
  await runAction(async () => {
    await uploadAttachment(id.value, f)
    uploadFile.value = null
  })
}

async function doDownloadAttachment(att: any) {
  try {
    await downloadAttachment(
      id.value,
      att.id,
      att.original_name || att.name || 'piece-jointe',
    )
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Téléchargement impossible'
  }
}

async function postWorklog() {
  await runAction(async () => {
    await addWorklog(id.value, { minutes: worklogForm.value.minutes, note: worklogForm.value.note || undefined })
    worklogForm.value = { minutes: 15, note: '' }
  })
}

async function postSatisfaction() {
  await runAction(async () => {
    await submitSatisfaction(id.value, satisfactionForm.value)
  })
}

function timelineLabel(event: any) {
  switch (event.type) {
    case 'status':
      return `Statut : ${ticketStatusLabel(event.from)} → ${ticketStatusLabel(event.to)}`
    case 'comment':
      return event.body
    case 'internal_note':
      return `[Interne] ${event.body}`
    case 'assignment':
      return `Affectation (${event.action || 'assign'}) → ${event.assignee?.name || '—'}`
    case 'priority':
      return `Priorité : ${event.from?.name || '—'} → ${event.to?.name || '—'}`
    default:
      return event.type
  }
}
</script>

<template>
  <div>
    <div
      v-if="loading && !ticket"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="ticket">
      <ParapheurPageHeader
        :title="formatTicketNumber(ticket)"
        :subtitle="ticket.title"
      >
        <template #actions>
          <VChip
            :color="ticketStatusColor(ticket.status)"
            variant="tonal"
            size="small"
            class="me-1"
          >
            {{ ticketStatusLabel(ticket.status) }}
          </VChip>
          <VChip
            v-if="ticket.priority"
            :color="ticketPriorityColor(ticket.priority)"
            variant="tonal"
            size="small"
            class="me-2"
          >
            {{ ticketPriorityLabel(ticket.priority) }}
          </VChip>

          <VBtn
            v-if="canAction('take_charge')"
            color="primary"
            @click="doTakeCharge"
          >
            Prendre en charge
          </VBtn>
          <VBtn
            v-if="canAction('resolve')"
            color="success"
            variant="tonal"
            @click="resolveDialog = true"
          >
            Résoudre
          </VBtn>
          <VBtn
            v-if="canAction('close')"
            color="secondary"
            variant="tonal"
            @click="doClose"
          >
            Clôturer
          </VBtn>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                append-icon="tabler-chevron-down"
              >
                Actions
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                prepend-icon="tabler-refresh"
                title="Actualiser"
                @click="refresh"
              />
              <VListItem
                v-if="canAction('assign')"
                prepend-icon="tabler-user-plus"
                title="Affecter"
                @click="openAssignDialog"
              />
              <VListItem
                v-if="canAction('transfer')"
                prepend-icon="tabler-arrows-exchange"
                title="Transférer"
                @click="openTransferDialog"
              />
              <VListItem
                v-if="canAction('wait')"
                prepend-icon="tabler-clock-pause"
                title="Mettre en attente"
                @click="waitDialog = true"
              />
              <VListItem
                v-if="canAction('escalate')"
                prepend-icon="tabler-arrow-up-right"
                title="Escalader"
                @click="openEscalateDialog"
              />
              <VListItem
                v-if="canAction('reopen')"
                prepend-icon="tabler-rotate-clockwise"
                title="Réouvrir"
                @click="reopenDialog = true"
              />
              <VDivider v-if="canAction('merge') || canAction('link_problem') || canAction('kb') || canAction('major_incident')" />
              <VListItem
                v-if="canAction('merge')"
                prepend-icon="tabler-git-merge"
                title="Fusionner"
                @click="mergeDialog = true"
              />
              <VListItem
                v-if="canAction('link_problem')"
                prepend-icon="tabler-bug"
                title="Lier à un problème"
                @click="problemDialog = true"
              />
              <VListItem
                v-if="canAction('kb')"
                prepend-icon="tabler-book"
                title="Capitaliser (KB)"
                @click="kbDialog = true"
              />
              <VListItem
                v-if="canAction('major_incident')"
                :prepend-icon="ticket.is_major_incident ? 'tabler-alert-triangle-off' : 'tabler-alert-triangle'"
                :title="ticket.is_major_incident ? 'Retirer incident majeur' : 'Marquer incident majeur'"
                @click="toggleMajorIncident"
              />
              <VDivider v-if="canAction('cancel')" />
              <VListItem
                v-if="canAction('cancel')"
                prepend-icon="tabler-x"
                title="Annuler le ticket"
                class="text-error"
                @click="cancelDialog = true"
              />
            </VList>
          </VMenu>
        </template>
      </ParapheurPageHeader>

      <VAlert
        v-if="ticket.status === 'EN_ATTENTE_VALIDATION'"
        type="warning"
        variant="tonal"
        class="mb-4"
      >
        <div class="d-flex flex-wrap align-center justify-space-between gap-3">
          <div>
            <div class="font-weight-medium">
              En attente d'approbation
            </div>
            <div class="text-body-2">
              Le traitement est bloqué jusqu'à validation.
            </div>
          </div>
          <div
            v-if="canAction('approve') || canAction('refuse')"
            class="d-flex flex-wrap align-center gap-2"
          >
            <AppTextField
              v-if="canAction('refuse')"
              v-model="approvalRefuseReason"
              label="Motif si refus"
              hide-details
              density="compact"
              style="min-inline-size: 180px"
            />
            <VBtn
              v-if="canAction('approve')"
              color="success"
              @click="acceptApproval"
            >
              Approuver
            </VBtn>
            <VBtn
              v-if="canAction('refuse')"
              color="error"
              variant="tonal"
              @click="refuseApproval"
            >
              Refuser
            </VBtn>
          </div>
        </div>
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

      <VRow dense>
        <VCol
          cols="12"
          md="8"
        >
          <VCard class="mb-4">
            <VCardText>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip
                  :color="ticketStatusColor(ticket.status)"
                  variant="tonal"
                >
                  {{ ticket.status_label || ticketStatusLabel(ticket.status) }}
                </VChip>
                <VChip
                  :color="ticketPriorityColor(ticket.priority)"
                  variant="tonal"
                >
                  {{ ticketPriorityLabel(ticket.priority) }}
                </VChip>
                <VChip
                  :color="slaBadge(ticket.sla).color"
                  variant="tonal"
                >
                  {{ slaBadge(ticket.sla).label }}
                </VChip>
                <VChip
                  v-if="ticket.is_major_incident"
                  color="error"
                  variant="flat"
                >
                  Incident majeur
                </VChip>
              </div>
              <div class="text-body-1 mb-2">
                {{ ticket.description || 'Aucune description.' }}
              </div>
              <div
                v-if="ticket.resolution_summary"
                class="mt-4"
              >
                <div class="text-subtitle-2">
                  Résolution
                </div>
                <div class="text-body-2">
                  {{ ticket.resolution_summary }}
                </div>
              </div>
            </VCardText>
          </VCard>

          <VCard>
            <VTabs
              v-model="activeTab"
              class="px-2"
            >
              <VTab value="comments">
                Suivis
              </VTab>
              <VTab value="tasks">
                Tâches
              </VTab>
              <VTab value="solutions">
                Solutions
              </VTab>
              <VTab value="timeline">
                Historique
              </VTab>
              <VTab value="attachments">
                Fichiers
              </VTab>
              <VTab value="actors">
                Acteurs
              </VTab>
              <VTab value="relations">
                Liens
              </VTab>
            </VTabs>
            <VDivider />
            <VWindow v-model="activeTab">
              <VWindowItem value="comments">
                <VCardText>
                  <div
                    v-for="c in comments"
                    :key="c.id"
                    class="mb-4 pa-3 rounded"
                    style="background: rgba(var(--v-theme-on-surface), 0.04)"
                  >
                    <div class="d-flex justify-space-between mb-1">
                      <span class="font-weight-medium">{{ c.user?.name || '—' }}</span>
                      <span class="text-caption">{{ formatTicketDateTime(c.created_at) }}</span>
                    </div>
                    <VChip
                      v-if="c.is_internal"
                      size="x-small"
                      color="warning"
                      class="mb-2"
                    >
                      Interne
                    </VChip>
                    <div class="text-body-2">
                      {{ c.body }}
                    </div>
                  </div>
                  <AppTextarea
                    v-model="commentBody"
                    label="Nouveau suivi"
                    rows="3"
                    class="mb-2"
                  />
                  <div class="d-flex align-center justify-space-between">
                    <VCheckbox
                      v-if="canAction('internal_note')"
                      v-model="commentInternal"
                      label="Note interne"
                      hide-details
                    />
                    <VSpacer v-else />
                    <VBtn
                      v-if="canAction('comment')"
                      color="primary"
                      :loading="actionLoading"
                      @click="postComment"
                    >
                      Publier
                    </VBtn>
                  </div>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="tasks">
                <VCardText>
                  <div
                    v-for="task in tasks"
                    :key="task.id"
                    class="d-flex align-center justify-space-between mb-3 pa-2 rounded"
                    style="background: rgba(var(--v-theme-on-surface), 0.04)"
                  >
                    <div>
                      <div class="font-weight-medium">
                        {{ task.title }}
                      </div>
                      <div class="text-caption">
                        {{ task.status }} — {{ task.assignee?.name || 'Non assigné' }}
                      </div>
                    </div>
                    <div class="d-flex gap-1">
                      <VBtn
                        size="x-small"
                        variant="tonal"
                        @click="updateTaskStatus(task.id, 'doing')"
                      >
                        En cours
                      </VBtn>
                      <VBtn
                        size="x-small"
                        color="success"
                        variant="tonal"
                        @click="updateTaskStatus(task.id, 'done')"
                      >
                        Fait
                      </VBtn>
                    </div>
                  </div>
                  <VDivider class="mb-3" />
                  <AppTextField
                    v-model="taskForm.title"
                    label="Titre de la tâche"
                    class="mb-2"
                    hide-details
                  />
                  <AppTextarea
                    v-model="taskForm.content"
                    label="Contenu"
                    rows="2"
                    class="mb-2"
                    hide-details
                  />
                  <VBtn
                    color="primary"
                    :loading="actionLoading"
                    @click="addTask"
                  >
                    Ajouter la tâche
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="solutions">
                <VCardText>
                  <div
                    v-for="sol in solutions"
                    :key="sol.id"
                    class="mb-4 pa-3 rounded"
                    style="background: rgba(var(--v-theme-on-surface), 0.04)"
                  >
                    <div class="d-flex justify-space-between mb-1">
                      <span class="font-weight-medium">{{ sol.author?.name || '—' }} — {{ sol.solution_type }}</span>
                      <VChip
                        size="small"
                        :color="sol.status === 'accepted' ? 'success' : sol.status === 'refused' ? 'error' : 'warning'"
                      >
                        {{ sol.status }}
                      </VChip>
                    </div>
                    <div class="text-body-2 mb-2">
                      {{ sol.content }}
                    </div>
                    <div
                      v-if="sol.status === 'proposed' && (canAction('accept_solution') || canAction('refuse_solution') || canAction('close'))"
                      class="d-flex flex-wrap gap-2 align-center"
                    >
                      <VBtn
                        v-if="canAction('accept_solution') || canAction('close')"
                        size="small"
                        color="success"
                        @click="acceptSolution(sol.id)"
                      >
                        Accepter
                      </VBtn>
                      <AppTextField
                        v-if="canAction('refuse_solution') || canAction('reopen')"
                        v-model="solutionRefuseReason"
                        label="Motif refus"
                        hide-details
                        density="compact"
                        style="min-inline-size: 200px"
                      />
                      <VBtn
                        v-if="canAction('refuse_solution') || canAction('reopen')"
                        size="small"
                        color="error"
                        variant="tonal"
                        @click="refuseSolution(sol.id)"
                      >
                        Refuser
                      </VBtn>
                    </div>
                  </div>
                  <div
                    v-if="!solutions.length"
                    class="text-medium-emphasis"
                  >
                    Aucune solution proposée.
                  </div>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="actors">
                <VCardText>
                  <div
                    v-for="a in actors"
                    :key="`${a.role}-${a.user_id}`"
                    class="mb-2"
                  >
                    <VChip
                      size="small"
                      class="me-2"
                    >
                      {{ ticketActorRoleLabel(a.role) }}
                    </VChip>
                    {{ a.user?.name || a.user_id }}
                  </div>
                  <VDivider class="my-4" />
                  <AppSelect
                    v-model="observerIds"
                    :items="userItems"
                    label="Observateurs"
                    multiple
                    chips
                    clearable
                    class="mb-3"
                    hide-details
                  />
                  <VBtn
                    color="primary"
                    :loading="actionLoading"
                    @click="saveObservers"
                  >
                    Enregistrer observateurs
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="elements">
                <VCardText>
                  <div class="mb-3">
                    <div class="text-caption text-medium-emphasis mb-1">
                      Application
                    </div>
                    <div>{{ ticket.application ? `${ticket.application.code} — ${ticket.application.name}` : '—' }}</div>
                  </div>
                  <AppSelect
                    v-model="assetIdsForm"
                    :items="assets.map(a => ({
                      value: a.id,
                      title: a.inventory_number ? `${a.name} (${a.inventory_number})` : a.name,
                    }))"
                    label="Actifs liés"
                    multiple
                    chips
                    clearable
                    class="mb-3"
                    hide-details
                  />
                  <div
                    v-if="ticket.location_label"
                    class="mb-3"
                  >
                    <div class="text-caption text-medium-emphasis">
                      Lieu
                    </div>
                    <div>{{ ticket.location_label }}</div>
                  </div>
                  <VBtn
                    color="primary"
                    :loading="actionLoading"
                    @click="saveAssets"
                  >
                    Enregistrer les éléments
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="knowledge">
                <VCardText>
                  <div
                    v-for="art in linkedKnowledge"
                    :key="art.id"
                    class="mb-3"
                  >
                    <RouterLink
                      :to="{ name: 'ticketing-connaissances-id', params: { id: art.id } }"
                      class="font-weight-medium text-primary"
                    >
                      {{ art.title }}
                    </RouterLink>
                    <div class="text-caption">
                      {{ art.summary || '' }}
                    </div>
                  </div>
                  <div
                    v-if="!linkedKnowledge.length"
                    class="text-medium-emphasis mb-3"
                  >
                    Aucun article lié. Utilisez « Capitaliser en KB » après résolution.
                  </div>
                  <VBtn
                    variant="tonal"
                    :to="{ name: 'ticketing-connaissances' }"
                  >
                    Parcourir la base
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="timeline">
                <VCardText>
                  <VTimeline
                    v-if="timeline.length"
                    side="end"
                    density="compact"
                    truncate-line="both"
                  >
                    <VTimelineItem
                      v-for="(event, idx) in timeline"
                      :key="idx"
                      size="x-small"
                      dot-color="primary"
                    >
                      <div class="text-caption text-medium-emphasis">
                        {{ formatTicketDateTime(event.at) }} — {{ event.user?.name || 'Système' }}
                      </div>
                      <div class="text-body-2">
                        {{ timelineLabel(event) }}
                      </div>
                    </VTimelineItem>
                  </VTimeline>
                  <div
                    v-else
                    class="text-medium-emphasis"
                  >
                    Aucun événement.
                  </div>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="attachments">
                <VCardText>
                  <VList
                    v-if="ticket.attachments?.length"
                    lines="two"
                  >
                    <VListItem
                      v-for="att in ticket.attachments"
                      :key="att.id"
                    >
                      <VListItemTitle>
                        <a
                          href="#"
                          class="text-primary text-decoration-none"
                          @click.prevent="doDownloadAttachment(att)"
                        >{{ att.original_name || att.name }}</a>
                      </VListItemTitle>
                      <VListItemSubtitle>{{ formatTicketDateTime(att.created_at) }}</VListItemSubtitle>
                      <template #append>
                        <VBtn
                          icon
                          variant="text"
                          size="small"
                          title="Télécharger"
                          @click="doDownloadAttachment(att)"
                        >
                          <VIcon icon="tabler-download" />
                        </VBtn>
                      </template>
                    </VListItem>
                  </VList>
                  <div
                    v-else
                    class="text-medium-emphasis mb-4"
                  >
                    Aucune pièce jointe.
                  </div>
                  <div class="d-flex gap-2 align-center">
                    <VFileInput
                      v-model="uploadFile"
                      label="Ajouter un fichier"
                      prepend-icon="tabler-paperclip"
                      hide-details
                      class="flex-grow-1"
                    />
                    <VBtn
                      color="primary"
                      :loading="actionLoading"
                      @click="doUpload"
                    >
                      Envoyer
                    </VBtn>
                  </div>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="worklogs">
                <VCardText>
                  <VTable
                    v-if="worklogs.length"
                    density="compact"
                    class="mb-4"
                  >
                    <thead>
                      <tr>
                        <th>Agent</th>
                        <th>Minutes</th>
                        <th>Note</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="w in worklogs"
                        :key="w.id"
                      >
                        <td>{{ w.user?.name || '—' }}</td>
                        <td>{{ w.minutes }}</td>
                        <td>{{ w.note || '—' }}</td>
                        <td>{{ formatTicketDateTime(w.worked_at || w.created_at) }}</td>
                      </tr>
                    </tbody>
                  </VTable>
                  <VRow dense>
                    <VCol
                      cols="12"
                      md="3"
                    >
                      <AppTextField
                        v-model.number="worklogForm.minutes"
                        type="number"
                        label="Minutes"
                        hide-details
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="worklogForm.note"
                        label="Note"
                        hide-details
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="3"
                    >
                      <VBtn
                        block
                        color="primary"
                        :loading="actionLoading"
                        @click="postWorklog"
                      >
                        Ajouter
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="costs">
                <VCardText>
                  <div class="text-subtitle-2 mb-3">
                    Total : {{ costsTotal }} XOF
                  </div>
                  <div
                    v-for="cost in costs"
                    :key="cost.id"
                    class="mb-2"
                  >
                    {{ cost.name }} — {{ cost.amount }} {{ cost.currency }}
                  </div>
                  <VDivider class="my-3" />
                  <AppTextField
                    v-model="costForm.name"
                    label="Libellé"
                    class="mb-2"
                    hide-details
                  />
                  <AppTextField
                    v-model.number="costForm.amount"
                    type="number"
                    label="Montant"
                    class="mb-2"
                    hide-details
                  />
                  <VBtn
                    color="primary"
                    :loading="actionLoading"
                    @click="addCost"
                  >
                    Ajouter un coût
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="relations">
                <VCardText>
                  <div
                    v-for="rel in relations"
                    :key="rel.id"
                    class="d-flex align-center justify-space-between mb-3 pa-2 rounded"
                    style="background: rgba(var(--v-theme-on-surface), 0.04)"
                  >
                    <div>
                      <RouterLink
                        v-if="otherTicket(rel)?.id"
                        :to="{ name: 'ticketing-id', params: { id: otherTicket(rel).id } }"
                        class="font-weight-medium text-primary"
                      >
                        {{ formatTicketNumber(otherTicket(rel).number) }}
                      </RouterLink>
                      <div class="text-body-2">
                        {{ otherTicket(rel)?.title || '—' }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ rel.relation_type }}
                      </div>
                    </div>
                    <VBtn
                      size="small"
                      variant="text"
                      color="error"
                      :loading="actionLoading"
                      @click="unlinkRelation(rel.id)"
                    >
                      Délier
                    </VBtn>
                  </div>
                  <div
                    v-if="!relations.length"
                    class="text-medium-emphasis mb-4"
                  >
                    Aucun ticket lié.
                  </div>
                  <VDivider class="mb-4" />
                  <AppTextField
                    v-model="relationForm.search"
                    label="Rechercher un ticket (n° ou titre)"
                    class="mb-2"
                    hide-details
                    @update:model-value="searchRelatedTickets"
                  />
                  <AppSelect
                    v-model="relationForm.related_ticket_id"
                    :items="relatedSearchResults.map((t: any) => ({
                      value: t.id,
                      title: `${formatTicketNumber(t.number)} — ${t.title}`,
                    }))"
                    label="Ticket à lier"
                    clearable
                    class="mb-2"
                  />
                  <AppSelect
                    v-model="relationForm.relation_type"
                    :items="[
                      { value: 'related', title: 'Lié' },
                      { value: 'duplicate', title: 'Doublon' },
                      { value: 'parent', title: 'Parent' },
                      { value: 'child', title: 'Enfant' },
                      { value: 'blocks', title: 'Bloque' },
                      { value: 'blocked_by', title: 'Bloqué par' },
                    ]"
                    label="Type de lien"
                    class="mb-3"
                  />
                  <VBtn
                    color="primary"
                    :loading="actionLoading"
                    :disabled="!relationForm.related_ticket_id"
                    @click="linkRelatedTicket"
                  >
                    Lier
                  </VBtn>
                </VCardText>
              </VWindowItem>
            </VWindow>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle class="text-h6">
                Informations
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Demandeur
                </div>
                <div>{{ ticket.requester?.name || '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Assigné
                </div>
                <div>{{ ticket.assignee?.name || '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Équipe
                </div>
                <div>{{ ticket.team?.name || '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Catégorie
                </div>
                <div>{{ ticket.category?.name || '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Impact / Urgence
                </div>
                <div>{{ ticket.impact?.name || '—' }} / {{ ticket.urgency?.name || '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis mb-1">
                  Application
                </div>
                <AppSelect
                  v-model="ticket.application_id"
                  :items="applications.map(a => ({ value: a.id, title: `${a.code} — ${a.name}` }))"
                  clearable
                  density="compact"
                  hide-details
                  placeholder="Aucune"
                  @update:model-value="saveCmdbLinks"
                />
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis mb-1">
                  Actif
                </div>
                <AppSelect
                  v-model="ticket.asset_id"
                  :items="assets.map(a => ({ value: a.id, title: a.inventory_number ? `${a.name} (${a.inventory_number})` : a.name }))"
                  clearable
                  density="compact"
                  hide-details
                  placeholder="Aucun"
                  @update:model-value="saveCmdbLinks"
                />
              </div>
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Créé le
                </div>
                <div>{{ formatTicketDateTime(ticket.created_at) }}</div>
              </div>
            </VCardText>
          </VCard>

          <VCard
            v-if="ticket.sla"
            class="mb-4"
          >
            <VCardItem>
              <VCardTitle class="text-h6">
                SLA
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <div class="mb-2">
                Réponse due : {{ formatTicketDateTime(ticket.sla.response_due_at) }}
              </div>
              <div class="mb-2">
                Résolution due : {{ formatTicketDateTime(ticket.sla.resolution_due_at) }}
              </div>
              <VChip
                :color="slaBadge(ticket.sla).color"
                variant="tonal"
              >
                {{ slaBadge(ticket.sla).label }}
              </VChip>
            </VCardText>
          </VCard>

          <VCard v-if="canShowSatisfaction">
            <VCardItem>
              <VCardTitle class="text-h6">
                Satisfaction
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VCardText>
              <VRating
                v-model="satisfactionForm.score"
                class="mb-3"
              />
              <AppTextarea
                v-model="satisfactionForm.comment"
                label="Commentaire"
                rows="2"
                class="mb-3"
              />
              <VBtn
                color="primary"
                block
                :loading="actionLoading"
                @click="postSatisfaction"
              >
                Envoyer
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog
      v-model="resolveDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Résoudre le ticket</VCardTitle>
        <VCardText>
          <AppTextarea
            v-model="resolveSummary"
            label="Résumé de résolution *"
            rows="4"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="resolveDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="success"
            :loading="actionLoading"
            @click="doResolve"
          >
            Résoudre
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="reopenDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Réouvrir le ticket</VCardTitle>
        <VCardText>
          <AppTextarea
            v-model="reopenReason"
            label="Motif *"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="reopenDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="warning"
            :loading="actionLoading"
            @click="doReopen"
          >
            Réouvrir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="assignDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Affecter</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="assignForm.support_team_id"
            :items="teamItems"
            label="Équipe"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="assignForm.assignee_id"
            :items="assignAgentItems"
            label="Agent"
            clearable
            :disabled="!assignForm.support_team_id"
            :hint="assignForm.support_team_id && !assignAgentItems.length ? 'Aucun membre dans cette équipe.' : undefined"
            :persistent-hint="Boolean(assignForm.support_team_id && !assignAgentItems.length)"
            class="mb-3"
          />
          <AppTextField
            v-model="assignForm.comment"
            label="Commentaire"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="assignDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="actionLoading"
            @click="doAssign"
          >
            Affecter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="transferDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Transférer</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="transferForm.support_team_id"
            :items="teamItems"
            label="Équipe cible"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="transferForm.assignee_id"
            :items="transferAgentItems"
            label="Agent cible"
            clearable
            :disabled="!transferForm.support_team_id"
            :hint="transferForm.support_team_id && !transferAgentItems.length ? 'Aucun membre dans cette équipe.' : undefined"
            :persistent-hint="Boolean(transferForm.support_team_id && !transferAgentItems.length)"
            class="mb-3"
          />
          <AppTextField
            v-model="transferForm.comment"
            label="Motif du transfert"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="transferDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="actionLoading"
            @click="doTransfer"
          >
            Transférer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="mergeDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Fusionner dans un autre ticket</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="mergeSearch"
            label="Rechercher le ticket cible"
            class="mb-3"
            hide-details
            @update:model-value="searchMergeTargets"
          />
          <AppSelect
            v-model="mergeTargetId"
            :items="mergeResults.map((t: any) => ({
              value: t.id,
              title: `${formatTicketNumber(t.number)} — ${t.title}`,
            }))"
            label="Ticket cible"
            clearable
          />
          <div class="text-caption text-medium-emphasis mt-2">
            Ce ticket sera clôturé et marqué comme doublon du ticket cible.
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="mergeDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="error"
            :loading="actionLoading"
            :disabled="!mergeTargetId"
            @click="doMerge"
          >
            Fusionner
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="waitDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Mettre en attente</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="waitForm.status"
            :items="[
              { value: 'EN_ATTENTE_DEMANDEUR', title: 'En attente demandeur' },
              { value: 'EN_ATTENTE_TIERS', title: 'En attente tiers' },
            ]"
            label="Type d'attente"
            class="mb-3"
          />
          <AppTextarea
            v-model="waitForm.comment"
            label="Commentaire"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="waitDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="info"
            :loading="actionLoading"
            @click="doWait"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="escalateDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Escalader</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="escalateForm.to_team_id"
            :items="teamItems"
            label="Équipe cible"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="escalateForm.to_user_id"
            :items="escalateAgentItems"
            label="Agent cible"
            clearable
            :disabled="!escalateForm.to_team_id"
            :hint="escalateForm.to_team_id && !escalateAgentItems.length ? 'Aucun membre dans cette équipe.' : undefined"
            :persistent-hint="Boolean(escalateForm.to_team_id && !escalateAgentItems.length)"
            class="mb-3"
          />
          <AppTextarea
            v-model="escalateForm.reason"
            label="Motif"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="escalateDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="error"
            :loading="actionLoading"
            @click="doEscalate"
          >
            Escalader
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="cancelDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Annuler le ticket</VCardTitle>
        <VCardText>
          <AppTextarea
            v-model="cancelReason"
            label="Motif *"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="cancelDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="error"
            :loading="actionLoading"
            :disabled="!cancelReason.trim()"
            @click="doCancel"
          >
            Annuler le ticket
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="problemDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Lier à un problème</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="selectedProblemId"
            :items="problems.map(p => ({ value: p.id, title: `${p.number} — ${p.title}` }))"
            label="Problème"
            clearable
          />
          <div class="text-caption text-medium-emphasis mt-2">
            Créez d’abord un problème dans le menu Problèmes si la liste est vide.
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="problemDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="primary"
            :loading="actionLoading"
            :disabled="!selectedProblemId"
            @click="linkToProblem"
          >
            Lier
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="kbDialog"
      max-width="640"
    >
      <VCard>
        <VCardTitle>Capitaliser en article KB</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="kbForm.title"
            :placeholder="`Solution — ${ticket?.title || ''}`"
            label="Titre"
            class="mb-3"
          />
          <AppTextarea
            v-model="kbForm.summary"
            :placeholder="ticket?.resolution_summary || ''"
            label="Résumé"
            rows="2"
            class="mb-3"
          />
          <AppTextarea
            v-model="kbForm.body"
            label="Contenu"
            rows="5"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="kbDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="success"
            :loading="actionLoading"
            @click="createKbFromResolution"
          >
            Créer le brouillon
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
