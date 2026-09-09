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

const loadCounts = async () => {
  counts.value = await $api('/parapheur/counts');
};

const loadDocuments = async () => {
  loading.value = true;
  try {
    const res = await $api('/parapheur/documents', {
      query: { folder: activeFolder.value || undefined },
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
  await Promise.all([loadCounts(), loadDocuments()]);
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
