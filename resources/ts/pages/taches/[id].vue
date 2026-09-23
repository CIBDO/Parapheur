<script setup lang="ts">
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import {
  formatTaskDue,
  taskDisplayProgress,
  taskPriorityColor,
  taskPriorityLabels,
  taskProgressColor,
  taskProgressEditable,
  taskSourceLabels,
  taskStatusColor,
  taskStatusLabels,
} from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Task',
  },
})

const route = useRoute()
const router = useRouter()
const { get, action } = useTasks()

const task = ref<any>(null)
const loading = ref(true)
const acting = ref(false)
const comment = ref('')
const completeSummary = ref('')
const returnMotif = ref('')
const showComplete = ref(false)
const showReturn = ref(false)
const showCancel = ref(false)
const cancelReason = ref('')
const subtaskTitle = ref('')

const depRelation = ref('depends_on')
const depRelatedId = ref<number | null>(null)
const relatedOptions = ref<{ id: number; title: string; reference: string }[]>([])
const linkDocumentId = ref<number | null>(null)
const linkRole = ref('ged_link')
const showLinkGed = ref(false)
const showOfficeCreate = ref(false)
const officeTitle = ref('')
const officeFormat = ref('docx')
const editorDocId = ref<number | null>(null)
const showEditor = ref(false)
const classificationNodes = ref<{ id: number; name: string; code: string }[]>([])
const availableDocs = ref<{ id: number; reference?: string; object?: string; title?: string }[]>([])
const submitGedNodeId = ref<number | null>(null)
const submitGedLinkId = ref<number | null>(null)
const showSubmitGed = ref(false)

const dependencyLabels: Record<string, string> = {
  blocks: 'Bloque',
  blocked_by: 'Bloquée par',
  depends_on: 'Dépend de',
  related_to: 'Liée à',
}

const documentRoleLabels: Record<string, string> = {
  working: 'Pièce de travail',
  proof: 'Preuve',
  final: 'Document final',
  ged_link: 'Lien GED',
}

const documentRoleColors: Record<string, string> = {
  working: 'primary',
  proof: 'warning',
  final: 'success',
  ged_link: 'info',
}

const officeFormatIcon = (format?: string) => {
  const f = String(format || '').toLowerCase()
  if (f.includes('xls') || f.includes('sheet'))
    return 'tabler-file-spreadsheet'
  if (f.includes('ppt') || f.includes('presentation'))
    return 'tabler-presentation'
  return 'tabler-file-text'
}

const taskId = computed(() => String(route.params.id))

const displayProgress = computed(() =>
  taskDisplayProgress(task.value?.status, task.value?.progress),
)

const canEditProgress = computed(() => taskProgressEditable(task.value?.status))

const progressDraft = ref(0)
const savingProgress = ref(false)
const activeTab = ref('comments')

const isOverdue = computed(() => {
  if (!task.value?.due_at || ['validee', 'annulee', 'terminee'].includes(task.value.status))
    return false

  return task.value.is_overdue || new Date(task.value.due_at) < new Date()
})

const commentsCount = computed(() => (task.value?.comments || []).length)
const subtasksCount = computed(() => (task.value?.subtasks || []).length)
const depsCount = computed(() => (task.value?.dependencies || []).length)
const docsCount = computed(() => (task.value?.document_links || []).length)

watch(
  () => [task.value?.status, task.value?.progress],
  () => {
    progressDraft.value = displayProgress.value
  },
)

const saveProgress = async () => {
  if (!task.value || !canEditProgress.value || savingProgress.value)
    return
  const next = Math.min(100, Math.max(0, Math.round(Number(progressDraft.value))))
  if (next === Number(task.value.progress || 0))
    return
  savingProgress.value = true
  try {
    await $api(`/tasks/${taskId.value}`, {
      method: 'PUT',
      body: { progress: next },
    })
    await load()
  }
  finally {
    savingProgress.value = false
  }
}

const load = async () => {
  loading.value = true
  try {
    task.value = await get(taskId.value)
  }
  finally {
    loading.value = false
  }
}

const loadRelatedOptions = async () => {
  const res = await $api('/tasks', { query: { include_subtasks: 1, per_page: 50 } })
  relatedOptions.value = (res.data ?? res).filter((t: any) => String(t.id) !== taskId.value)
}

const loadClassification = async () => {
  try {
    const res = await $api('/ged/classification-nodes')
    classificationNodes.value = res.data ?? res ?? []
  }
  catch {
    classificationNodes.value = []
  }
}

const loadAvailableDocs = async () => {
  try {
    const res = await $api('/ged/documents', { query: { per_page: 50 } })
    availableDocs.value = res.data ?? res ?? []
  }
  catch {
    availableDocs.value = []
  }
}

const docLabel = (doc: { reference?: string; object?: string; title?: string; id?: number }) =>
  `${doc.reference || `#${doc.id}`} — ${doc.object || doc.title || 'Document'}`

const run = async (path: string, body?: Record<string, any>) => {
  acting.value = true
  try {
    task.value = await action(taskId.value, path, body)
    await load()
  }
  finally {
    acting.value = false
    showComplete.value = false
    showReturn.value = false
    showCancel.value = false
  }
}

const addComment = async () => {
  if (!comment.value.trim())
    return
  await run('comments', { body: comment.value })
  comment.value = ''
}

const addSubtask = async () => {
  if (!subtaskTitle.value.trim())
    return
  await run('subtasks', { title: subtaskTitle.value })
  subtaskTitle.value = ''
}

const addDependency = async () => {
  if (!depRelatedId.value)
    return
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/dependencies`, {
      method: 'POST',
      body: { related_task_id: depRelatedId.value, relation: depRelation.value },
    })
    depRelatedId.value = null
    await load()
  }
  finally {
    acting.value = false
  }
}

const removeDependency = async (depId: number) => {
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/dependencies/${depId}`, { method: 'DELETE' })
    await load()
  }
  finally {
    acting.value = false
  }
}

const createOfficeDoc = async () => {
  acting.value = true
  try {
    const link = await $api(`/tasks/${taskId.value}/documents/office`, {
      method: 'POST',
      body: {
        title: officeTitle.value || undefined,
        format: officeFormat.value,
      },
    })
    showOfficeCreate.value = false
    await load()
    if (link.document?.id) {
      editorDocId.value = link.document.id
      showEditor.value = true
    }
  }
  finally {
    acting.value = false
  }
}

const openLinkGed = async () => {
  linkDocumentId.value = null
  linkRole.value = 'ged_link'
  showLinkGed.value = true
  if (!availableDocs.value.length)
    await loadAvailableDocs()
}

const linkGedDocument = async () => {
  if (!linkDocumentId.value)
    return
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/documents/link`, {
      method: 'POST',
      body: { document_id: linkDocumentId.value, role: linkRole.value || 'ged_link' },
    })
    linkDocumentId.value = null
    showLinkGed.value = false
    await load()
  }
  finally {
    acting.value = false
  }
}

const openEditor = (docId: number) => {
  editorDocId.value = docId
  showEditor.value = true
}

const openSubmitGed = (linkId: number) => {
  submitGedLinkId.value = linkId
  submitGedNodeId.value = null
  showSubmitGed.value = true
}

const submitToGed = async () => {
  if (!submitGedLinkId.value)
    return
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/documents/${submitGedLinkId.value}/submit-ged`, {
      method: 'POST',
      body: { classification_node_id: submitGedNodeId.value || undefined },
    })
    showSubmitGed.value = false
    await load()
  }
  finally {
    acting.value = false
  }
}

const unlinkDocument = async (linkId: number) => {
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/documents/${linkId}`, { method: 'DELETE' })
    await load()
  }
  finally {
    acting.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadRelatedOptions(), loadClassification(), loadAvailableDocs()])
})
</script>

<template>
  <div class="task-detail">
    <ParapheurPageHeader
      :title="task?.title || 'Tâche'"
      :subtitle="task?.reference || 'Fiche tâche'"
      icon="tabler-checkbox"
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
          prepend-icon="tabler-arrow-left"
          @click="router.push({ name: 'taches' })"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading && !task"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <template v-else-if="task">
      <VAlert
        v-if="isOverdue"
        type="error"
        variant="tonal"
        class="mb-4"
        density="comfortable"
        icon="tabler-alert-triangle"
      >
        Échéance dépassée — {{ formatTaskDue(task.due_at) }}
      </VAlert>

      <VRow dense>
        <VCol
          cols="12"
          lg="8"
        >
          <!-- Synthèse / actions -->
          <VCard class="mb-4">
            <VCardText class="pa-5">
              <div class="d-flex flex-wrap align-start justify-space-between gap-4 mb-4">
                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    size="small"
                    :color="taskStatusColor(task.status)"
                    variant="flat"
                  >
                    {{ taskStatusLabels[task.status] || task.status }}
                  </VChip>
                  <VChip
                    size="small"
                    :color="taskPriorityColor(task.priority)"
                    variant="tonal"
                  >
                    {{ taskPriorityLabels[task.priority] || task.priority }}
                  </VChip>
                  <VChip
                    v-if="task.due_at"
                    size="small"
                    :color="isOverdue ? 'error' : 'default'"
                    variant="tonal"
                    prepend-icon="tabler-calendar-event"
                  >
                    {{ formatTaskDue(task.due_at) }}
                  </VChip>
                </div>

                <div class="d-flex align-center gap-3">
                  <VProgressCircular
                    :model-value="canEditProgress ? progressDraft : displayProgress"
                    :color="taskProgressColor(canEditProgress ? progressDraft : displayProgress)"
                    :size="56"
                    :width="6"
                  >
                    <span class="text-caption font-weight-bold">
                      {{ canEditProgress ? Math.round(progressDraft) : displayProgress }}%
                    </span>
                  </VProgressCircular>
                </div>
              </div>

              <div class="text-body-1 text-high-emphasis mb-1">
                Description
              </div>
              <div
                class="text-body-2 mb-5"
                :class="{ 'text-medium-emphasis': !task.description }"
                style="white-space: pre-wrap"
              >
                {{ task.description || 'Aucune description renseignée.' }}
              </div>

              <div
                v-if="canEditProgress"
                class="mb-2"
              >
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-body-2 font-weight-medium">Avancement</span>
                  <VChip
                    size="x-small"
                    :color="savingProgress ? 'warning' : 'primary'"
                    variant="tonal"
                  >
                    {{ savingProgress ? 'Enregistrement…' : `${Math.round(progressDraft)} %` }}
                  </VChip>
                </div>
                <VSlider
                  v-model="progressDraft"
                  :min="0"
                  :max="100"
                  :step="5"
                  :color="taskProgressColor(progressDraft)"
                  thumb-label
                  hide-details
                  :disabled="savingProgress"
                  @mouseup="saveProgress"
                  @touchend="saveProgress"
                  @keyup.enter="saveProgress"
                />
                <div class="text-caption text-medium-emphasis">
                  Relâchez le curseur pour enregistrer l’avancement.
                </div>
              </div>
              <div
                v-else
                class="mb-2"
              >
                <VProgressLinear
                  :model-value="displayProgress"
                  :color="taskProgressColor(displayProgress)"
                  height="10"
                  rounded
                />
                <div class="text-caption text-medium-emphasis mt-1">
                  <template v-if="task.status === 'validee'">
                    Tâche validée — avancement finalisé.
                  </template>
                  <template v-else-if="task.status === 'annulee'">
                    Tâche annulée.
                  </template>
                  <template v-else>
                    Suivi automatique selon l’étape du workflow.
                  </template>
                </div>
              </div>
            </VCardText>

            <VDivider />

            <VCardText class="d-flex flex-wrap gap-2 py-3">
              <VBtn
                v-if="task.status === 'brouillon'"
                color="primary"
                prepend-icon="tabler-send"
                :loading="acting"
                @click="run('publish')"
              >
                Publier / Imputer
              </VBtn>
              <VBtn
                v-if="task.status === 'imputee'"
                color="info"
                prepend-icon="tabler-hand-click"
                :loading="acting"
                @click="run('take-charge')"
              >
                Prendre en charge
              </VBtn>
              <VBtn
                v-if="['prise_en_charge', 'retournee', 'en_attente'].includes(task.status)"
                color="primary"
                prepend-icon="tabler-player-play"
                :loading="acting"
                @click="run('start')"
              >
                Démarrer
              </VBtn>
              <VBtn
                v-if="task.status === 'en_cours'"
                color="success"
                prepend-icon="tabler-circle-check"
                :loading="acting"
                @click="showComplete = true"
              >
                Terminer
              </VBtn>
              <VBtn
                v-if="task.status === 'a_valider'"
                color="success"
                prepend-icon="tabler-checks"
                :loading="acting"
                @click="run('validate')"
              >
                Valider
              </VBtn>
              <VBtn
                v-if="task.status === 'a_valider'"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-arrow-back-up"
                :loading="acting"
                @click="showReturn = true"
              >
                Retourner
              </VBtn>
              <VSpacer />
              <VBtn
                v-if="!['validee', 'annulee'].includes(task.status)"
                color="error"
                variant="tonal"
                prepend-icon="tabler-x"
                :loading="acting"
                @click="showCancel = true"
              >
                Annuler
              </VBtn>
            </VCardText>
          </VCard>

          <!-- Onglets -->
          <VCard>
            <VTabs
              v-model="activeTab"
              density="comfortable"
              color="primary"
              class="px-2"
            >
              <VTab value="comments">
                <VIcon
                  start
                  icon="tabler-message"
                />
                Commentaires
                <VChip
                  v-if="commentsCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ commentsCount }}
                </VChip>
              </VTab>
              <VTab value="subtasks">
                <VIcon
                  start
                  icon="tabler-list-tree"
                />
                Sous-tâches
                <VChip
                  v-if="subtasksCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ subtasksCount }}
                </VChip>
              </VTab>
              <VTab value="dependencies">
                <VIcon
                  start
                  icon="tabler-git-branch"
                />
                Dépendances
                <VChip
                  v-if="depsCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ depsCount }}
                </VChip>
              </VTab>
              <VTab value="documents">
                <VIcon
                  start
                  icon="tabler-files"
                />
                Documents
                <VChip
                  v-if="docsCount"
                  class="ms-2"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ docsCount }}
                </VChip>
              </VTab>
            </VTabs>

            <VDivider />

            <VWindow v-model="activeTab">
              <VWindowItem value="comments">
                <div
                  v-if="!(task.comments || []).length"
                  class="text-center py-10 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-message-off"
                    size="40"
                    class="mb-2"
                  />
                  <div>Aucun commentaire pour le moment</div>
                </div>
                <VList
                  v-else
                  lines="two"
                >
                  <VListItem
                    v-for="c in task.comments || []"
                    :key="c.id"
                  >
                    <template #prepend>
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="36"
                      >
                        <span class="text-caption">
                          {{ (c.user?.name || '?').slice(0, 1).toUpperCase() }}
                        </span>
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium">
                      {{ c.user?.name || 'Utilisateur' }}
                    </VListItemTitle>
                    <VListItemSubtitle style="white-space: pre-wrap; -webkit-line-clamp: unset">
                      {{ c.body }}
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
                <VDivider />
                <VCardText class="d-flex flex-wrap gap-2">
                  <AppTextField
                    v-model="comment"
                    class="flex-grow-1"
                    label="Ajouter un commentaire"
                    hide-details
                    @keyup.enter="addComment"
                  />
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-send"
                    :loading="acting"
                    :disabled="!comment.trim()"
                    @click="addComment"
                  >
                    Envoyer
                  </VBtn>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="subtasks">
                <div
                  v-if="!(task.subtasks || []).length"
                  class="text-center py-10 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-list-tree"
                    size="40"
                    class="mb-2"
                  />
                  <div>Aucune sous-tâche</div>
                </div>
                <VList
                  v-else
                  lines="two"
                >
                  <VListItem
                    v-for="st in task.subtasks || []"
                    :key="st.id"
                    class="cursor-pointer"
                    @click="router.push(`/taches/${st.id}`)"
                  >
                    <VListItemTitle>{{ st.title }}</VListItemTitle>
                    <VListItemSubtitle>{{ st.assignee?.name || 'Non affectée' }}</VListItemSubtitle>
                    <template #append>
                      <VChip
                        size="small"
                        :color="taskStatusColor(st.status)"
                        variant="tonal"
                      >
                        {{ taskStatusLabels[st.status] || st.status }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
                <template v-if="!task.parent_id">
                  <VDivider />
                  <VCardText class="d-flex flex-wrap gap-2">
                    <AppTextField
                      v-model="subtaskTitle"
                      class="flex-grow-1"
                      label="Nouvelle sous-tâche"
                      hide-details
                      @keyup.enter="addSubtask"
                    />
                    <VBtn
                      color="primary"
                      prepend-icon="tabler-plus"
                      :loading="acting"
                      :disabled="!subtaskTitle.trim()"
                      @click="addSubtask"
                    >
                      Ajouter
                    </VBtn>
                  </VCardText>
                </template>
              </VWindowItem>

              <VWindowItem value="dependencies">
                <div
                  v-if="!(task.dependencies || []).length"
                  class="text-center py-8 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-git-branch"
                    size="40"
                    class="mb-2"
                  />
                  <div>Aucune dépendance</div>
                </div>
                <VList
                  v-else
                  lines="two"
                >
                  <VListItem
                    v-for="dep in task.dependencies || []"
                    :key="dep.id"
                    class="cursor-pointer"
                    @click="dep.related_task?.id && router.push(`/taches/${dep.related_task.id}`)"
                  >
                    <VListItemTitle>
                      {{ dep.related_task?.reference }} — {{ dep.related_task?.title }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      {{ dependencyLabels[dep.relation] || dep.relation }}
                    </VListItemSubtitle>
                    <template #append>
                      <div class="d-flex align-center gap-2">
                        <VChip
                          size="small"
                          :color="taskStatusColor(dep.related_task?.status)"
                          variant="tonal"
                        >
                          {{ taskStatusLabels[dep.related_task?.status] || dep.related_task?.status }}
                        </VChip>
                        <VBtn
                          icon="tabler-trash"
                          size="x-small"
                          variant="text"
                          color="error"
                          @click.stop="removeDependency(dep.id)"
                        />
                      </div>
                    </template>
                  </VListItem>
                </VList>
                <VDivider />
                <VCardText>
                  <VRow dense>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <AppSelect
                        v-model="depRelation"
                        label="Relation"
                        :items="Object.entries(dependencyLabels).map(([value, title]) => ({ title, value }))"
                        hide-details
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="5"
                    >
                      <AppSelect
                        v-model="depRelatedId"
                        label="Tâche liée"
                        :items="relatedOptions"
                        :item-title="(i: any) => `${i.reference} — ${i.title}`"
                        item-value="id"
                        hide-details
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="3"
                      class="d-flex align-center"
                    >
                      <VBtn
                        block
                        color="primary"
                        prepend-icon="tabler-link"
                        :loading="acting"
                        :disabled="!depRelatedId"
                        @click="addDependency"
                      >
                        Lier
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VWindowItem>

              <VWindowItem value="documents">
                <VCardItem>
                  <VCardSubtitle>
                    Pièces de travail ONLYOFFICE et liens GED
                  </VCardSubtitle>
                  <template #append>
                    <div class="d-flex flex-wrap gap-2">
                      <VBtn
                        size="small"
                        variant="tonal"
                        color="info"
                        prepend-icon="tabler-link"
                        @click="openLinkGed"
                      >
                        Lier GED
                      </VBtn>
                      <VBtn
                        size="small"
                        color="primary"
                        prepend-icon="tabler-file-plus"
                        @click="showOfficeCreate = true"
                      >
                        Nouveau Office
                      </VBtn>
                    </div>
                  </template>
                </VCardItem>
                <VDivider />

                <div
                  v-if="!(task.document_links || []).length"
                  class="text-center py-10 text-medium-emphasis"
                >
                  <VIcon
                    icon="tabler-file-off"
                    size="40"
                    class="mb-2"
                  />
                  <div class="mb-1">
                    Aucun document lié
                  </div>
                  <div class="text-caption mb-4">
                    Créez un document Office ou rattachez un document existant de la GED.
                  </div>
                  <div class="d-flex justify-center flex-wrap gap-2">
                    <VBtn
                      size="small"
                      color="primary"
                      prepend-icon="tabler-file-plus"
                      @click="showOfficeCreate = true"
                    >
                      Créer un document
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-link"
                      @click="openLinkGed"
                    >
                      Lier depuis la GED
                    </VBtn>
                  </div>
                </div>

                <VList
                  v-else
                  lines="two"
                >
                  <VListItem
                    v-for="link in task.document_links || []"
                    :key="link.id"
                  >
                    <template #prepend>
                      <VAvatar
                        :color="link.submitted_to_ged ? 'success' : 'primary'"
                        variant="tonal"
                        rounded
                        size="40"
                      >
                        <VIcon :icon="officeFormatIcon(link.document?.latest_version?.original_name || link.document?.object)" />
                      </VAvatar>
                    </template>
                    <VListItemTitle class="font-weight-medium">
                      {{ link.document?.object || link.document?.title || link.document?.reference || 'Document' }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      <span class="me-2">{{ link.document?.reference || '—' }}</span>
                      <VChip
                        size="x-small"
                        class="me-1"
                        :color="documentRoleColors[link.role] || 'secondary'"
                        variant="tonal"
                      >
                        {{ documentRoleLabels[link.role] || link.role }}
                      </VChip>
                      <VChip
                        v-if="link.submitted_to_ged"
                        size="x-small"
                        color="success"
                        variant="tonal"
                      >
                        Versé GED
                      </VChip>
                    </VListItemSubtitle>
                    <template #append>
                      <div class="d-flex flex-wrap gap-1 justify-end">
                        <VBtn
                          icon
                          size="small"
                          variant="tonal"
                          color="primary"
                          :disabled="!link.document?.id"
                          @click="openEditor(link.document.id)"
                        >
                          <VIcon icon="tabler-edit" />
                          <VTooltip
                            activator="parent"
                            location="top"
                          >
                            Éditer
                          </VTooltip>
                        </VBtn>
                        <VBtn
                          icon
                          size="small"
                          variant="tonal"
                          color="info"
                          :disabled="!link.document?.id"
                          :to="{ name: 'ged-id', params: { id: link.document.id } }"
                        >
                          <VIcon icon="tabler-folder" />
                          <VTooltip
                            activator="parent"
                            location="top"
                          >
                            Ouvrir GED
                          </VTooltip>
                        </VBtn>
                        <VBtn
                          v-if="!link.submitted_to_ged"
                          icon
                          size="small"
                          color="success"
                          variant="tonal"
                          @click="openSubmitGed(link.id)"
                        >
                          <VIcon icon="tabler-upload" />
                          <VTooltip
                            activator="parent"
                            location="top"
                          >
                            Verser à la GED
                          </VTooltip>
                        </VBtn>
                        <VBtn
                          icon
                          size="small"
                          variant="text"
                          color="error"
                          @click="unlinkDocument(link.id)"
                        >
                          <VIcon icon="tabler-unlink" />
                          <VTooltip
                            activator="parent"
                            location="top"
                          >
                            Délier
                          </VTooltip>
                        </VBtn>
                      </div>
                    </template>
                  </VListItem>
                </VList>
              </VWindowItem>
            </VWindow>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard class="mb-4">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="info"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-info-circle"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Synthèse
              </VCardTitle>
              <VCardSubtitle>
                Acteurs et contexte
              </VCardSubtitle>
            </VCardItem>
            <VDivider />
            <VList density="comfortable">
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-user"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Créateur
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ task.creator?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-user-check"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Responsable
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ task.assignee?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-checks"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Validateur
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ task.validator?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-building"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Structure
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ task.structure?.name || '—' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-affiliate"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Source
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-high-emphasis">
                  {{ taskSourceLabels[task.source_kind] || task.source_kind || 'Manuelle' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem
                v-if="task.instruction"
                class="cursor-pointer"
                @click="router.push(`/taches/instructions/${task.instruction.id}`)"
              >
                <template #prepend>
                  <VIcon
                    icon="tabler-list-check"
                    size="20"
                    class="me-2 text-medium-emphasis"
                  />
                </template>
                <VListItemTitle class="text-caption text-medium-emphasis">
                  Instruction
                </VListItemTitle>
                <VListItemSubtitle class="text-body-2 text-primary">
                  {{ task.instruction.reference || task.instruction.title }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCard>

          <VCard class="mb-4">
            <VCardItem>
              <template #prepend>
                <VAvatar
                  color="secondary"
                  variant="tonal"
                  rounded
                  size="36"
                >
                  <VIcon
                    icon="tabler-users"
                    size="20"
                  />
                </VAvatar>
              </template>
              <VCardTitle class="text-h6">
                Contributeurs
              </VCardTitle>
            </VCardItem>
            <VDivider />
            <VList v-if="(task.contributors || []).length">
              <VListItem
                v-for="c in task.contributors || []"
                :key="c.id"
                :title="c.name"
              >
                <template #prepend>
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="32"
                    class="me-2"
                  >
                    <span class="text-caption">{{ (c.name || '?').slice(0, 1).toUpperCase() }}</span>
                  </VAvatar>
                </template>
              </VListItem>
            </VList>
            <div
              v-else
              class="text-center py-6 text-medium-emphasis text-caption"
            >
              Aucun contributeur
            </div>
          </VCard>

          <VCard>
            <VCardText class="d-flex justify-space-between py-3">
              <div class="text-center flex-grow-1">
                <div class="text-h6">
                  {{ commentsCount }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Commentaires
                </div>
              </div>
              <VDivider vertical />
              <div class="text-center flex-grow-1">
                <div class="text-h6">
                  {{ subtasksCount }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Sous-tâches
                </div>
              </div>
              <VDivider vertical />
              <div class="text-center flex-grow-1">
                <div class="text-h6">
                  {{ docsCount }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Documents
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog
      v-model="showComplete"
      max-width="480"
    >
      <VCard title="Terminer la tâche">
        <VCardText>
          <AppTextarea
            v-model="completeSummary"
            label="Compte rendu / preuve"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showComplete = false">
            Annuler
          </VBtn>
          <VBtn
            color="success"
            :loading="acting"
            @click="run('complete', { summary: completeSummary })"
          >
            Confirmer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showReturn"
      max-width="480"
    >
      <VCard title="Retour pour correction">
        <VCardText>
          <AppTextarea
            v-model="returnMotif"
            label="Motif *"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showReturn = false">
            Annuler
          </VBtn>
          <VBtn
            color="warning"
            :loading="acting"
            @click="run('return', { motif: returnMotif })"
          >
            Retourner
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showCancel"
      max-width="480"
    >
      <VCard title="Annuler la tâche">
        <VCardText>
          <AppTextarea
            v-model="cancelReason"
            label="Motif *"
            rows="3"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showCancel = false">
            Fermer
          </VBtn>
          <VBtn
            color="error"
            :loading="acting"
            @click="run('cancel', { reason: cancelReason })"
          >
            Annuler la tâche
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showOfficeCreate"
      max-width="480"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Créer un document Office</VCardTitle>
          <VCardSubtitle>
            Ouverture automatique dans ONLYOFFICE
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <AppTextField
            v-model="officeTitle"
            class="mb-3"
            label="Titre"
            placeholder="Ex. Projet de note / rapport"
          />
          <AppSelect
            v-model="officeFormat"
            label="Format"
            :items="[
              { title: 'Word (DOCX)', value: 'docx' },
              { title: 'Excel (XLSX)', value: 'xlsx' },
              { title: 'PowerPoint (PPTX)', value: 'pptx' },
            ]"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="showOfficeCreate = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="acting"
            @click="createOfficeDoc"
          >
            Créer & ouvrir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showLinkGed"
      max-width="520"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>Lier un document GED</VCardTitle>
          <VCardSubtitle>
            Rattacher un document existant sans duplication
          </VCardSubtitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <AppSelect
            v-model="linkDocumentId"
            class="mb-3"
            label="Document"
            :items="availableDocs"
            :item-title="(i: any) => docLabel(i)"
            item-value="id"
            clearable
          />
          <AppSelect
            v-model="linkRole"
            label="Rôle dans la tâche"
            :items="Object.entries(documentRoleLabels).map(([value, title]) => ({ title, value }))"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="showLinkGed = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="acting"
            :disabled="!linkDocumentId"
            @click="linkGedDocument"
          >
            Lier
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showSubmitGed"
      max-width="480"
    >
      <VCard title="Verser à la GED">
        <VCardText>
          <AppSelect
            v-model="submitGedNodeId"
            label="Nœud de classement (optionnel)"
            :items="classificationNodes"
            item-title="name"
            item-value="id"
            clearable
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="showSubmitGed = false">
            Annuler
          </VBtn>
          <VBtn
            color="success"
            :loading="acting"
            @click="submitToGed"
          >
            Verser
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="showEditor"
      fullscreen
      transition="dialog-bottom-transition"
    >
      <VCard>
        <VToolbar color="primary">
          <VBtn
            icon="tabler-x"
            @click="showEditor = false"
          />
          <VToolbarTitle>Édition ONLYOFFICE</VToolbarTitle>
        </VToolbar>
        <VCardText v-if="editorDocId">
          <OnlyOfficeEditor
            :document-id="editorDocId"
            @saved="load"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>
