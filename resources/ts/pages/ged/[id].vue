<script setup lang="ts">
import OnlyOfficeEditor from '@/components/parapheur/OnlyOfficeEditor.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  attachmentKindLabels,
  linkRelationLabels,
  originLabels,
} from '@/utils/gedUi'
import {
  confidentialityLabels,
  formatDateFr,
  labelOf,
  statusColor,
  statusLabels,
} from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'read',
    subject: 'Ged',
  },
})

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const doc = ref<any>(null)
const loading = ref(true)
const tab = ref('synthese')
const errorMessage = ref('')
const classifyNodeId = ref<number | null>(null)
const classifFlat = ref<Array<{ id: number; label: string }>>([])
const shareForm = ref({ user_id: null as number | null, ability: 'view' })
const users = ref<Array<{ id: number; name: string }>>([])
const linkForm = ref({ target_document_id: null as number | null, relation_type: 'related_to' })
const linkCandidates = ref<Array<{ id: number; reference: string; object: string }>>([])
const versionFile = ref<File | File[] | null>(null)
const attachmentFile = ref<File | File[] | null>(null)
const attachmentKind = ref('piece_jointe')

const flatten = (nodes: any[], prefix = ''): Array<{ id: number; label: string }> => {
  const out: Array<{ id: number; label: string }> = []
  for (const n of nodes) {
    const label = prefix ? `${prefix} / ${n.name}` : n.name
    out.push({ id: n.id, label })
    if (n.children?.length)
      out.push(...flatten(n.children, label))
  }

  return out
}

const favorited = ref(false)

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    doc.value = await $api(`/ged/documents/${id.value}`)
    classifyNodeId.value = doc.value.classification_node?.id ?? null
    favorited.value = !!doc.value.is_favorite
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Document inaccessible'
    doc.value = null
  }
  finally {
    loading.value = false
  }
}

onMounted(async () => {
  const [classif, people] = await Promise.all([
    $api('/meta/classification-nodes'),
    $api('/meta/users'),
  ])
  classifFlat.value = flatten(classif.data || [])
  users.value = people
  await load()
})

const saveClassify = async () => {
  await $api(`/ged/documents/${id.value}/classify`, {
    method: 'POST',
    body: { classification_node_id: classifyNodeId.value },
  })
  await load()
}

const archive = async () => {
  if (!confirm('Archiver ce document ?'))
    return
  await $api(`/ged/documents/${id.value}/archive`, { method: 'POST', body: {} })
  await load()
}

const toggleFavorite = async () => {
  const res = await $api(`/ged/documents/${id.value}/favorite`, { method: 'POST' })
  favorited.value = !!res.favorited
}

const exportZip = async () => {
  try {
    const blob = await $api(`/ged/documents/${id.value}/export`, {
      responseType: 'blob',
    }) as Blob
    const url = URL.createObjectURL(blob)
    const link = window.document.createElement('a')
    link.href = url
    link.download = `ged_${doc.value?.reference || id.value}.zip`
    link.click()
    URL.revokeObjectURL(url)
  }
  catch (e: any) {
    alert(e?.data?.message || 'Export ZIP impossible')
  }
}

const reindex = async () => {
  await $api(`/ged/documents/${id.value}/reindex`, { method: 'POST' })
}

const share = async () => {
  if (!shareForm.value.user_id)
    return
  await $api(`/ged/documents/${id.value}/share`, {
    method: 'POST',
    body: shareForm.value,
  })
  shareForm.value.user_id = null
  await load()
}

const searchLinks = async (q: string) => {
  if (!q || q.length < 2)
    return
  const res = await $api('/ged/documents', { query: { q, per_page: 10 } })
  linkCandidates.value = (res.data || []).filter((d: any) => d.id !== id.value)
}

const addLink = async () => {
  if (!linkForm.value.target_document_id)
    return
  await $api(`/ged/documents/${id.value}/links`, {
    method: 'POST',
    body: linkForm.value,
  })
  linkForm.value.target_document_id = null
  await load()
}

const asFile = (v: File | File[] | null) => Array.isArray(v) ? v[0] ?? null : v

const uploadVersion = async () => {
  const file = asFile(versionFile.value)
  if (!file)
    return
  const body = new FormData()
  body.append('file', file)
  await $api(`/ged/documents/${id.value}/versions`, { method: 'POST', body })
  versionFile.value = null
  await load()
}

const uploadAttachment = async () => {
  const file = asFile(attachmentFile.value)
  if (!file)
    return
  const body = new FormData()
  body.append('file', file)
  body.append('kind', attachmentKind.value)
  await $api(`/ged/documents/${id.value}/attachments`, { method: 'POST', body })
  attachmentFile.value = null
  await load()
}

const mime = computed(() => doc.value?.latest_version?.mime_type || '')
const canOnlyOffice = computed(() =>
  /word|excel|powerpoint|officedocument|opendocument|msword|ms-excel|ms-powerpoint/i.test(mime.value)
  || /\.(docx?|xlsx?|pptx?)$/i.test(doc.value?.latest_version?.original_name || ''),
)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="doc ? (doc.title || doc.object) : 'Document GED'"
      :subtitle="doc ? `${doc.reference}${doc.dossier_number ? ' · ' + doc.dossier_number : ''}` : ''"
      icon="tabler-file-description"
    >
      <VBtn
        variant="tonal"
        :to="{ name: 'parapheur-id', params: { id: String(id) } }"
      >
        Parapheur
      </VBtn>
      <VBtn
        class="ms-2"
        :variant="favorited ? 'flat' : 'tonal'"
        :color="favorited ? 'warning' : undefined"
        prepend-icon="tabler-star"
        @click="toggleFavorite"
      >
        {{ favorited ? 'Favori' : 'Favoris' }}
      </VBtn>
      <VBtn
        class="ms-2"
        variant="tonal"
        prepend-icon="tabler-download"
        @click="exportZip"
      >
        Export ZIP
      </VBtn>
      <VBtn
        v-if="doc?.permissions?.can_edit"
        class="ms-2"
        color="warning"
        variant="tonal"
        @click="archive"
      >
        Archiver
      </VBtn>
    </ParapheurPageHeader>

    <VAlert
      v-if="errorMessage"
      type="error"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <VSkeletonLoader
      v-if="loading"
      type="article"
    />

    <template v-else-if="doc">
      <div class="d-flex flex-wrap gap-2 mb-4">
        <VChip
          :color="statusColor(doc.status)"
          label
        >
          {{ labelOf(statusLabels, doc.status) }}
        </VChip>
        <VChip label>
          {{ labelOf(confidentialityLabels, doc.confidentiality) }}
        </VChip>
        <VChip label>
          {{ labelOf(originLabels, doc.origin) }}
        </VChip>
        <VChip
          v-if="doc.official_version"
          color="success"
          label
        >
          Version officielle v{{ doc.official_version.version_number }}
        </VChip>
        <VChip
          v-if="doc.legal_hold_at"
          color="error"
          label
        >
          Gelé
        </VChip>
        <VChip
          v-for="tag in doc.tags || []"
          :key="tag.id"
          size="small"
          label
        >
          #{{ tag.name }}
        </VChip>
      </div>

      <VTabs
        v-model="tab"
        class="mb-4"
      >
        <VTab value="synthese">
          Synthèse
        </VTab>
        <VTab value="fichiers">
          Fichiers
        </VTab>
        <VTab value="versions">
          Versions
        </VTab>
        <VTab value="metadata">
          Métadonnées
        </VTab>
        <VTab value="lies">
          Documents liés
        </VTab>
        <VTab value="commentaires">
          Commentaires
        </VTab>
        <VTab value="workflow">
          Workflow
        </VTab>
        <VTab value="historique">
          Historique
        </VTab>
      </VTabs>

      <VWindow v-model="tab">
        <VWindowItem value="synthese">
          <VRow>
            <VCol
              cols="12"
              md="5"
            >
              <VCard class="parapheur-section-card mb-4">
                <VCardText>
                  <div class="text-body-2 mb-2"><strong>Type :</strong> {{ doc.type?.name || '—' }}</div>
                  <div class="text-body-2 mb-2"><strong>Catégorie :</strong> {{ doc.category?.name || '—' }}</div>
                  <div class="text-body-2 mb-2"><strong>Structure :</strong> {{ doc.structure?.name || '—' }}</div>
                  <div class="text-body-2 mb-2"><strong>Auteur :</strong> {{ doc.author?.name || '—' }}</div>
                  <div class="text-body-2 mb-2"><strong>Date :</strong> {{ formatDateFr(doc.document_date) }}</div>
                  <div class="text-body-2 mb-2"><strong>Classement :</strong> {{ doc.classification_node?.path || doc.classification_node?.name || 'Non classé' }}</div>
                  <div
                    v-if="doc.description"
                    class="mt-3"
                  >
                    {{ doc.description }}
                  </div>
                </VCardText>
              </VCard>

              <VCard
                v-if="doc.permissions?.can_edit"
                class="parapheur-section-card mb-4"
              >
                <VCardItem>
                  <VCardTitle>Classer</VCardTitle>
                </VCardItem>
                <VCardText>
                  <AppSelect
                    v-model="classifyNodeId"
                    :items="classifFlat"
                    item-title="label"
                    item-value="id"
                    label="Nœud de classement"
                    clearable
                    class="mb-3"
                  />
                  <VBtn
                    color="primary"
                    size="small"
                    @click="saveClassify"
                  >
                    Enregistrer
                  </VBtn>
                </VCardText>
              </VCard>

              <VCard
                v-if="doc.permissions?.can_share"
                class="parapheur-section-card"
              >
                <VCardItem>
                  <VCardTitle>Partage interne</VCardTitle>
                </VCardItem>
                <VCardText>
                  <AppSelect
                    v-model="shareForm.user_id"
                    :items="users"
                    item-title="name"
                    item-value="id"
                    label="Utilisateur"
                    class="mb-2"
                  />
                  <VBtn
                    size="small"
                    color="primary"
                    @click="share"
                  >
                    Partager
                  </VBtn>
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              md="7"
            >
              <VCard class="parapheur-section-card">
                <VCardItem>
                  <VCardTitle>Aperçu</VCardTitle>
                </VCardItem>
                <VCardText>
                  <OnlyOfficeEditor
                    v-if="canOnlyOffice && doc.latest_version"
                    :document-id="doc.id"
                  />
                  <div
                    v-else
                    class="text-medium-emphasis"
                  >
                    Aperçu ONLYOFFICE non disponible pour ce format.
                    <VBtn
                      class="ms-2"
                      size="small"
                      variant="tonal"
                      :to="{ name: 'parapheur-id', params: { id: String(id) } }"
                    >
                      Ouvrir dans le parapheur
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <VWindowItem value="fichiers">
          <VCard class="parapheur-section-card mb-4">
            <VCardItem>
              <VCardTitle>Document principal</VCardTitle>
            </VCardItem>
            <VCardText>
              <div v-if="doc.latest_version">
                {{ doc.latest_version.original_name }}
                (v{{ doc.latest_version.version_number }})
              </div>
              <div
                v-else
                class="text-medium-emphasis"
              >
                Aucun fichier principal
              </div>
            </VCardText>
          </VCard>
          <VCard class="parapheur-section-card mb-4">
            <VCardItem>
              <VCardTitle>Pièces & annexes</VCardTitle>
            </VCardItem>
            <VList>
              <VListItem
                v-for="att in doc.attachments || []"
                :key="att.id"
              >
                <VListItemTitle>{{ att.original_name }}</VListItemTitle>
                <VListItemSubtitle>{{ labelOf(attachmentKindLabels, att.kind) }}</VListItemSubtitle>
              </VListItem>
              <VListItem v-if="!(doc.attachments || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucune pièce jointe
                </VListItemTitle>
              </VListItem>
            </VList>
            <VCardText v-if="doc.permissions?.can_edit">
              <VRow>
                <VCol cols="6">
                  <VFileInput
                    v-model="attachmentFile"
                    label="Ajouter un fichier"
                    show-size
                  />
                </VCol>
                <VCol cols="4">
                  <AppSelect
                    v-model="attachmentKind"
                    :items="Object.entries(attachmentKindLabels).map(([value, title]) => ({ value, title }))"
                    item-title="title"
                    item-value="value"
                    label="Type"
                  />
                </VCol>
                <VCol cols="2">
                  <VBtn
                    color="primary"
                    block
                    @click="uploadAttachment"
                  >
                    Ajouter
                  </VBtn>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="versions">
          <VCard class="parapheur-section-card">
            <VList>
              <VListItem
                v-for="v in doc.versions || []"
                :key="v.id"
              >
                <VListItemTitle>
                  Version {{ v.version_number }}
                  <VChip
                    v-if="v.is_official"
                    size="x-small"
                    color="success"
                    class="ms-2"
                    label
                  >
                    Officielle
                  </VChip>
                  <VChip
                    v-if="v.is_main"
                    size="x-small"
                    class="ms-1"
                    label
                  >
                    Courante
                  </VChip>
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ v.original_name }} · {{ v.uploader?.name }} · {{ formatDateFr(v.created_at) }}
                  <span v-if="v.change_note"> — {{ v.change_note }}</span>
                </VListItemSubtitle>
              </VListItem>
            </VList>
            <VCardText v-if="doc.permissions?.can_edit">
              <VFileInput
                v-model="versionFile"
                label="Nouvelle version"
                class="mb-2"
                show-size
              />
              <VBtn
                color="primary"
                @click="uploadVersion"
              >
                Déposer
              </VBtn>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="metadata">
          <VCard class="parapheur-section-card">
            <VCardText>
              <pre class="text-body-2">{{ {
                reference: doc.reference,
                dossier_number: doc.dossier_number,
                title: doc.title,
                object: doc.object,
                description: doc.description,
                summary: doc.summary,
                language: doc.language,
                source: doc.source,
                keywords: doc.keywords,
                archive_status: doc.archive_status,
                text_extraction_status: doc.text_extraction_status,
                ocr_status: doc.ocr_status,
                antivirus_status: doc.antivirus_status,
              } }}</pre>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="lies">
          <VCard class="parapheur-section-card mb-4">
            <VCardItem>
              <VCardTitle>Liens sortants</VCardTitle>
            </VCardItem>
            <VList>
              <VListItem
                v-for="link in doc.links?.outgoing || []"
                :key="link.id"
                :to="{ name: 'ged-id', params: { id: String(link.target_document_id) } }"
              >
                <VListItemTitle>{{ link.target?.object || link.target_document_id }}</VListItemTitle>
                <VListItemSubtitle>{{ labelOf(linkRelationLabels, link.relation_type) }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCard>
          <VCard
            v-if="doc.permissions?.can_edit"
            class="parapheur-section-card"
          >
            <VCardText>
              <AppTextField
                label="Rechercher un document à lier"
                class="mb-2"
                @update:model-value="searchLinks"
              />
              <AppSelect
                v-model="linkForm.target_document_id"
                :items="linkCandidates.map(d => ({ id: d.id, label: `${d.reference} — ${d.object}` }))"
                item-title="label"
                item-value="id"
                label="Document cible"
                class="mb-2"
              />
              <VBtn
                color="primary"
                @click="addLink"
              >
                Lier
              </VBtn>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="commentaires">
          <VCard class="parapheur-section-card">
            <VList>
              <VListItem
                v-for="c in doc.comments || []"
                :key="c.id"
              >
                <VListItemTitle>{{ c.user?.name }} · {{ c.kind }}</VListItemTitle>
                <VListItemSubtitle>{{ c.body }}</VListItemSubtitle>
              </VListItem>
              <VListItem v-if="!(doc.comments || []).length">
                <VListItemTitle class="text-medium-emphasis">
                  Aucun commentaire
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCard>
        </VWindowItem>

        <VWindowItem value="workflow">
          <VCard class="parapheur-section-card">
            <VCardText>
              <div><strong>Circuit :</strong> {{ doc.workflow_instance?.workflow?.name || 'Libre / hors circuit' }}</div>
              <div class="mt-2"><strong>Destinataire :</strong> {{ doc.current_assignee?.name || '—' }}</div>
              <VBtn
                class="mt-4"
                variant="tonal"
                :to="{ name: 'parapheur-id', params: { id: String(id) } }"
              >
                Ouvrir le circuit parapheur
              </VBtn>
            </VCardText>
          </VCard>
        </VWindowItem>

        <VWindowItem value="historique">
          <VCard class="parapheur-section-card">
            <VList>
              <VListItem
                v-for="a in doc.actions || []"
                :key="a.id"
              >
                <VListItemTitle>{{ a.action_type }} · {{ a.actor?.name }}</VListItemTitle>
                <VListItemSubtitle>{{ a.from_status }} → {{ a.to_status }} · {{ formatDateFr(a.created_at) }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCard>
        </VWindowItem>
      </VWindow>
    </template>
  </div>
</template>
