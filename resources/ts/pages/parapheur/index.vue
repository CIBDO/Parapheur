<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'Parapheur',
  },
});

interface DocItem {
  id: number;
  reference: string;
  object: string;
  status: string;
  priority: string;
  expected_action: string;
  due_date?: string;
  structure?: { code: string; name: string };
  type?: { name: string };
  author?: { name: string };
}

const folders = [
  { key: 'a_traiter', title: 'À traiter' },
  { key: 'a_consulter', title: 'À consulter' },
  { key: 'pour_information', title: 'Pour information' },
  { key: 'a_viser', title: 'À viser' },
  { key: 'a_valider', title: 'À valider' },
  { key: 'en_attente', title: 'En attente' },
  { key: 'retournes', title: 'Retournés' },
  { key: 'traites', title: 'Traités' },
  { key: 'archives', title: 'Archivés' },
];

const activeFolder = ref<string | null>('a_traiter');
const counts = ref<Record<string, number>>({});
const documents = ref<DocItem[]>([]);
const loading = ref(false);

interface StructureOption {
  id: number;
  code: string;
  name: string;
}

interface DocumentTypeOption {
  id: number;
  code: string;
  name: string;
}

interface UserOption {
  id: number;
  name: string;
  structure?: { code: string; name: string };
}

const structures = ref<StructureOption[]>([]);
const documentTypes = ref<DocumentTypeOption[]>([]);
const authors = ref<UserOption[]>([]);

const filters = ref({
  q: '',
  reference: '',
  object: '',
  document_type_id: null as number | null,
  structure_id: null as number | null,
  author_id: null as number | null,
  status: '',
  priority: '',
  confidentiality: '',
  keywords: '',
  document_date_from: '',
  document_date_to: '',
  due_date_from: '',
  due_date_to: '',
});

const statusOptions = [
  'brouillon',
  'depose',
  'en_circuit',
  'transmis',
  'en_consultation',
  'en_attente',
  'a_corriger',
  'corrige',
  'a_viser',
  'vise',
  'a_valider',
  'valide',
  'rejete',
  'traite',
  'classe',
  'archive',
  'annule',
];

const priorityOptions = ['normale', 'importante', 'urgente', 'tres_urgente'];
const confidentialityOptions = ['normal', 'restreint', 'confidentiel', 'tres_confidentiel'];

const loadCounts = async () => {
  counts.value = await $api('/parapheur/counts');
};

const loadMeta = async () => {
  const [types, structList, usersList] = await Promise.all([
    $api('/meta/document-types'),
    $api('/meta/structures'),
    $api('/meta/users'),
  ]);

  documentTypes.value = types as DocumentTypeOption[];
  structures.value = structList as StructureOption[];
  authors.value = usersList as UserOption[];
};

const loadDocuments = async () => {
  loading.value = true;
  try {
    const query: Record<string, unknown> = {
      folder: activeFolder.value || undefined,
    };

    if (filters.value.q) query.q = filters.value.q;
    if (filters.value.reference) query.reference = filters.value.reference;
    if (filters.value.object) query.object = filters.value.object;
    if (filters.value.document_type_id) query.document_type_id = filters.value.document_type_id;
    if (filters.value.structure_id) query.structure_id = filters.value.structure_id;
    if (filters.value.author_id) query.author_id = filters.value.author_id;
    if (filters.value.status) query.status = filters.value.status;
    if (filters.value.priority) query.priority = filters.value.priority;
    if (filters.value.confidentiality) query.confidentiality = filters.value.confidentiality;
    if (filters.value.keywords) query.keywords = filters.value.keywords;

    if (filters.value.document_date_from) query.document_date_from = filters.value.document_date_from;
    if (filters.value.document_date_to) query.document_date_to = filters.value.document_date_to;
    if (filters.value.due_date_from) query.due_date_from = filters.value.due_date_from;
    if (filters.value.due_date_to) query.due_date_to = filters.value.due_date_to;

    const res = await $api('/parapheur/documents', {
      query,
    });
    documents.value = res.data ?? res;
  } finally {
    loading.value = false;
  }
};

const selectFolder = async (key: string) => {
  activeFolder.value = key;
  await loadDocuments();
};

const priorityColor = (priority: string) => {
  if (priority === 'tres_urgente' || priority === 'urgente') return 'error';
  if (priority === 'importante') return 'warning';

  return 'secondary';
};

onMounted(async () => {
  await Promise.all([loadCounts(), loadMeta(), loadDocuments()]);
});
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">Mon parapheur</h4>
        <p class="text-body-1 mb-0">Documents nécessitant votre intervention</p>
      </div>
      <VBtn color="primary" :to="{ name: 'parapheur-nouveau' }"> Nouveau document </VBtn>
    </div>

    <VRow class="mb-6">
      <VCol v-for="folder in folders" :key="folder.key" cols="6" sm="4" md="3" lg="2">
        <VCard :variant="activeFolder === folder.key ? 'tonal' : 'outlined'" class="cursor-pointer" @click="selectFolder(folder.key)">
          <VCardText class="text-center py-4">
            <div class="text-h5 font-weight-bold">
              {{ counts[folder.key] ?? 0 }}
            </div>
            <div class="text-caption">
              {{ folder.title }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" sm="4" md="3" lg="2">
        <VCard color="error" variant="tonal">
          <VCardText class="text-center py-4">
            <div class="text-h5 font-weight-bold">
              {{ counts.urgents ?? 0 }}
            </div>
            <div class="text-caption">Urgents</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6">
      <VCardTitle>Recherche native</VCardTitle>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.q"
              label="Recherche"
              placeholder="Référence, objet…"
              prepend-inner-icon="tabler-search"
              clearable
              @keyup.enter="loadDocuments"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.structure_id"
              :items="structures"
              :item-title="(i: StructureOption) => `${i.code} — ${i.name}`"
              item-value="id"
              label="Structure"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.document_type_id"
              :items="documentTypes"
              :item-title="(i: DocumentTypeOption) => `${i.code} — ${i.name}`"
              item-value="id"
              label="Type"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2" class="d-flex align-end">
            <VBtn
              block
              color="primary"
              variant="tonal"
              :loading="loading"
              @click="loadDocuments"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>

        <VRow class="mt-3">
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.reference"
              label="Référence"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.object"
              label="Objet"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.author_id"
              :items="authors"
              :item-title="(u: UserOption) => u.name"
              item-value="id"
              label="Auteur"
              clearable
            />
          </VCol>
        </VRow>

        <VRow class="mt-3">
          <VCol cols="12" md="2">
            <AppSelect
              v-model="filters.status"
              :items="statusOptions"
              label="Statut"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <AppSelect
              v-model="filters.priority"
              :items="priorityOptions"
              label="Priorité"
              clearable
            />
          </VCol>
          <VCol cols="12" md="2">
            <AppSelect
              v-model="filters.confidentiality"
              :items="confidentialityOptions"
              label="Confidentialité"
              clearable
            />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField
              v-model="filters.keywords"
              label="Mots-clés (virgule)"
              placeholder="ex: dette, DGTCP"
              clearable
            />
          </VCol>
        </VRow>

        <VRow class="mt-3">
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.document_date_from"
              type="date"
              label="Date dossier depuis"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.document_date_to"
              type="date"
              label="Date dossier jusqu’à"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.due_date_from"
              type="date"
              label="Échéance depuis"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField
              v-model="filters.due_date_to"
              type="date"
              label="Échéance jusqu’à"
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardTitle>Documents</VCardTitle>
      <VDivider />
      <VDataTable
        :items="documents"
        :loading="loading"
        :headers="[
          { title: 'Référence', key: 'reference' },
          { title: 'Objet', key: 'object' },
          { title: 'Structure', key: 'structure' },
          { title: 'Action', key: 'expected_action' },
          { title: 'Priorité', key: 'priority' },
          { title: 'Statut', key: 'status' },
          { title: '', key: 'actions', sortable: false },
        ]"
        item-value="id"
      >
        <template #item.structure="{ item }">
          {{ item.structure?.code }}
        </template>
        <template #item.priority="{ item }">
          <VChip size="small" :color="priorityColor(item.priority)" label>
            {{ item.priority }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn size="small" variant="text" :to="{ name: 'parapheur-id', params: { id: item.id } }"> Ouvrir </VBtn>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
