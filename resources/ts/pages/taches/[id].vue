<script setup lang="ts">
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useTasks } from '@/composables/useTasks'
import {
  formatTaskDue,
  taskPriorityColor,
  taskPriorityLabels,
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
const showOfficeCreate = ref(false)
const officeTitle = ref('')
const officeFormat = ref('docx')
const editorDocId = ref<number | null>(null)
const showEditor = ref(false)
const classificationNodes = ref<{ id: number; name: string; code: string }[]>([])
const submitGedNodeId = ref<number | null>(null)
const submitGedLinkId = ref<number | null>(null)
const showSubmitGed = ref(false)

const dependencyLabels: Record<string, string> = {
  blocks: 'Bloque',
  blocked_by: 'Bloquée par',
  depends_on: 'Dépend de',
  related_to: 'Liée à',
}

const taskId = computed(() => String(route.params.id))

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

const linkGedDocument = async () => {
  if (!linkDocumentId.value)
    return
  acting.value = true
  try {
    await $api(`/tasks/${taskId.value}/documents/link`, {
      method: 'POST',
      body: { document_id: linkDocumentId.value, role: 'ged_link' },
    })
    linkDocumentId.value = null
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
  await Promise.all([load(), loadRelatedOptions(), loadClassification()])
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="task?.title || 'Tâche'"
      :subtitle="task?.reference"
      icon="tabler-checkbox"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          @click="router.push('/taches')"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VProgressLinear
      v-if="loading"
      indeterminate
      class="mb-4"
    />

    <template v-if="task">
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <VCard class="mb-4">
            <VCardText>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip :color="taskStatusColor(task.status)">
                  {{ taskStatusLabels[task.status] || task.status }}
                </VChip>
                <VChip :color="taskPriorityColor(task.priority)">
                  {{ taskPriorityLabels[task.priority] || task.priority }}
                </VChip>
                <VChip
                  v-if="task.due_at"
                  variant="tonal"
                >
                  Échéance {{ formatTaskDue(task.due_at) }}
                </VChip>
              </div>
              <div class="text-body-1 mb-4">
                {{ task.description || 'Pas de description' }}
              </div>
              <VProgressLinear
                :model-value="task.progress || 0"
                color="primary"
                height="8"
                rounded
              />
              <div class="text-caption mt-1">
                Progression {{ task.progress || 0 }} %
              </div>
            </VCardText>
            <VDivider />
            <VCardText class="d-flex flex-wrap gap-2">
              <VBtn
                v-if="task.status === 'brouillon'"
                color="primary"
                :loading="acting"
                @click="run('publish')"
              >
                Publier / Imputer
              </VBtn>
              <VBtn
                v-if="task.status === 'imputee'"
                color="info"
                :loading="acting"
                @click="run('take-charge')"
              >
                Prendre en charge
              </VBtn>
              <VBtn
                v-if="['prise_en_charge', 'retournee', 'en_attente'].includes(task.status)"
                color="primary"
                :loading="acting"
                @click="run('start')"
              >
                Démarrer
              </VBtn>
              <VBtn
                v-if="task.status === 'en_cours'"
                color="success"
                :loading="acting"
                @click="showComplete = true"
              >
                Terminer
              </VBtn>
              <VBtn
                v-if="task.status === 'a_valider'"
                color="success"
                :loading="acting"
                @click="run('validate')"
              >
                Valider
              </VBtn>
              <VBtn
                v-if="task.status === 'a_valider'"
                color="warning"
                :loading="acting"
                @click="showReturn = true"
              >
                Retourner
              </VBtn>
              <VBtn
                v-if="!['validee', 'annulee'].includes(task.status)"
                color="error"
                variant="tonal"
                :loading="acting"
                @click="showCancel = true"
              >
                Annuler
              </VBtn>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem><VCardTitle>Commentaires</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="c in task.comments || []"
                :key="c.id"
                :title="c.user?.name"
                :subtitle="c.body"
              />
            </VList>
            <VCardText class="d-flex gap-2">
              <AppTextField
                v-model="comment"
                label="Ajouter un commentaire"
                hide-details
              />
              <VBtn
                color="primary"
                :loading="acting"
                @click="addComment"
              >
                Envoyer
              </VBtn>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem><VCardTitle>Sous-tâches</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="st in task.subtasks || []"
                :key="st.id"
                :title="st.title"
                :subtitle="st.assignee?.name"
                @click="router.push(`/taches/${st.id}`)"
              >
                <template #append>
                  <VChip
                    size="small"
                    :color="taskStatusColor(st.status)"
                  >
                    {{ taskStatusLabels[st.status] || st.status }}
                  </VChip>
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-if="!task.parent_id"
              class="d-flex gap-2"
            >
              <AppTextField
                v-model="subtaskTitle"
                label="Nouvelle sous-tâche"
                hide-details
              />
              <VBtn
                color="primary"
                :loading="acting"
                @click="addSubtask"
              >
                Ajouter
              </VBtn>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem><VCardTitle>Dépendances</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="dep in task.dependencies || []"
                :key="dep.id"
                :title="dep.related_task?.reference + ' — ' + dep.related_task?.title"
                :subtitle="dependencyLabels[dep.relation] || dep.relation"
                @click="dep.related_task?.id && router.push(`/taches/${dep.related_task.id}`)"
              >
                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VChip
                      size="small"
                      :color="taskStatusColor(dep.related_task?.status)"
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
              <VListItem v-if="!(task.dependencies || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune dépendance
                </VListItemTitle>
              </VListItem>
            </VList>
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
                  md="6"
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
                  md="2"
                  class="d-flex align-center"
                >
                  <VBtn
                    block
                    color="primary"
                    :loading="acting"
                    @click="addDependency"
                  >
                    Lier
                  </VBtn>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VCard class="mb-4">
            <VCardItem>
              <VCardTitle>Documents (GED / ONLYOFFICE)</VCardTitle>
              <template #append>
                <VBtn
                  size="small"
                  color="primary"
                  prepend-icon="tabler-file-plus"
                  @click="showOfficeCreate = true"
                >
                  Nouveau Office
                </VBtn>
              </template>
            </VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="link in task.document_links || []"
                :key="link.id"
                :title="link.document?.object || link.document?.title || link.document?.reference"
                :subtitle="`${link.document?.reference || ''} · ${link.role}${link.submitted_to_ged ? ' · GED' : ''}`"
              >
                <template #append>
                  <div class="d-flex gap-1">
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      @click="openEditor(link.document.id)"
                    >
                      Éditer
                    </VBtn>
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="info"
                      @click="router.push(`/ged/${link.document.id}`)"
                    >
                      GED
                    </VBtn>
                    <VBtn
                      v-if="!link.submitted_to_ged"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      @click="openSubmitGed(link.id)"
                    >
                      Verser GED
                    </VBtn>
                    <VBtn
                      size="x-small"
                      icon="tabler-unlink"
                      variant="text"
                      color="error"
                      @click="unlinkDocument(link.id)"
                    />
                  </div>
                </template>
              </VListItem>
              <VListItem v-if="!(task.document_links || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucun document lié
                </VListItemTitle>
              </VListItem>
            </VList>
            <VCardText class="d-flex gap-2">
              <AppTextField
                v-model="linkDocumentId"
                label="ID document GED à lier"
                type="number"
                hide-details
              />
              <VBtn
                color="primary"
                :loading="acting"
                @click="linkGedDocument"
              >
                Lier
              </VBtn>
            </VCardText>
          </VCard>

          <VCard>
            <VCardItem><VCardTitle>Historique</VCardTitle></VCardItem>
            <VDivider />
            <VTimeline
              density="compact"
              side="end"
              class="pa-4"
            >
              <VTimelineItem
                v-for="h in task.histories || []"
                :key="h.id"
                size="x-small"
                dot-color="primary"
              >
                <div class="text-sm font-weight-medium">
                  {{ h.event }} — {{ h.user?.name || 'Système' }}
                </div>
                <div class="text-caption">
                  {{ h.comment }}
                </div>
              </VTimelineItem>
            </VTimeline>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <VCard class="mb-4">
            <VCardItem><VCardTitle>Synthèse</VCardTitle></VCardItem>
            <VDivider />
            <VList density="compact">
              <VListItem title="Créateur" :subtitle="task.creator?.name || '—'" />
              <VListItem title="Responsable" :subtitle="task.assignee?.name || '—'" />
              <VListItem title="Validateur" :subtitle="task.validator?.name || '—'" />
              <VListItem title="Structure" :subtitle="task.structure?.name || '—'" />
              <VListItem title="Source" :subtitle="task.source_kind || 'manual'" />
              <VListItem
                v-if="task.instruction"
                title="Instruction"
                :subtitle="task.instruction.reference || task.instruction.title"
              />
            </VList>
          </VCard>

          <VCard>
            <VCardItem><VCardTitle>Contributeurs</VCardTitle></VCardItem>
            <VDivider />
            <VList>
              <VListItem
                v-for="c in task.contributors || []"
                :key="c.id"
                :title="c.name"
              />
              <VListItem v-if="!(task.contributors || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucun
                </VListItemTitle>
              </VListItem>
            </VList>
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
      <VCard title="Créer un document Office">
        <VCardText>
          <AppTextField
            v-model="officeTitle"
            class="mb-3"
            label="Titre"
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
          <VBtn @click="showOfficeCreate = false">
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
