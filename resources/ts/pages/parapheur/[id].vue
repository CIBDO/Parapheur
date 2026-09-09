<script setup lang="ts">
const route = useRoute('parapheur-id');

definePage({
  meta: {
    action: 'read',
    subject: 'Parapheur',
  },
});

const document = ref<any>(null);
const loading = ref(true);
const actionComment = ref('');
const busy = ref(false);
const users = ref<Array<{ id: number; name: string }>>([]);
const instructionForm = ref({
  assignee_id: null as number | null,
  title: 'Instruction DG',
  body: '',
  due_date: '',
});

const load = async () => {
  loading.value = true;
  try {
    document.value = await $api(`/parapheur/documents/${route.params.id}`);
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  users.value = await $api('/meta/users');
  await load();
});

const runAction = async (path: string, body: Record<string, unknown> = {}) => {
  busy.value = true;
  try {
    await $api(`/parapheur/documents/${route.params.id}/${path}`, {
      method: 'POST',
      body: { comment: actionComment.value || undefined, ...body },
    });
    actionComment.value = '';
    await load();
  } finally {
    busy.value = false;
  }
};

const createInstruction = async () => {
  busy.value = true;
  try {
    await $api(`/parapheur/documents/${route.params.id}/instructions`, {
      method: 'POST',
      body: instructionForm.value,
    });
    await load();
  } finally {
    busy.value = false;
  }
};

const streamUrl = computed(() => document.value?.versions?.[0]?.stream_url || null);
</script>

<template>
  <div v-if="loading">
    <VProgressLinear indeterminate />
  </div>

  <div v-else-if="document">
    <div class="d-flex flex-wrap justify-space-between gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          {{ document.object }}
        </h4>
        <div class="text-body-1">{{ document.reference }} · {{ document.type?.name }} · {{ document.structure?.code }}</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <VChip label>
          {{ document.status }}
        </VChip>
        <VChip label color="warning">
          {{ document.priority }}
        </VChip>
        <VChip label color="info">
          {{ document.expected_action }}
        </VChip>
      </div>
    </div>

    <VRow>
      <VCol cols="12" lg="8">
        <VCard class="mb-6">
          <VCardTitle>Fiche du dossier</VCardTitle>
          <VCardText>
            <VRow dense>
              <VCol cols="6"
                ><strong>Auteur</strong>
                <div>{{ document.author?.name }}</div></VCol
              >
              <VCol cols="6"
                ><strong>Destinataire actuel</strong>
                <div>{{ document.current_assignee?.name || '—' }}</div></VCol
              >
              <VCol cols="6"
                ><strong>Confidentialité</strong>
                <div>{{ document.confidentiality }}</div></VCol
              >
              <VCol cols="6"
                ><strong>Échéance</strong>
                <div>{{ document.due_date || '—' }}</div></VCol
              >
            </VRow>
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Document principal</VCardTitle>
          <VCardText>
            <div v-if="document.versions?.length" class="d-flex flex-column gap-2">
              <div v-for="version in document.versions" :key="version.id" class="d-flex justify-space-between align-center">
                <span>V{{ version.version_number }} — {{ version.original_name }}</span>
                <VBtn size="small" variant="tonal" :href="version.download_url" target="_blank"> Télécharger </VBtn>
              </div>
            </div>
            <div v-else>Aucun fichier</div>

            <iframe
              v-if="streamUrl && (document.versions?.[0]?.mime_type || '').includes('pdf')"
              class="mt-4 w-100"
              style="min-block-size: 480px; border: 0"
              :src="streamUrl"
            />
          </VCardText>
        </VCard>

        <VCard class="mb-6">
          <VCardTitle>Observations</VCardTitle>
          <VCardText>
            <div v-for="comment in document.comments" :key="comment.id" class="mb-4">
              <div class="font-weight-medium">{{ comment.user?.name }} · {{ comment.kind }}</div>
              <div>{{ comment.body }}</div>
            </div>
            <AppTextarea v-model="actionComment" label="Commentaire / motif d'action" rows="3" class="mb-4" />
            <div class="d-flex flex-wrap gap-2">
              <VBtn :loading="busy" @click="runAction('comments', { body: actionComment, kind: 'general' })"> Commenter </VBtn>
              <VBtn color="warning" :loading="busy" @click="runAction('return')"> Retourner </VBtn>
              <VBtn color="info" :loading="busy" @click="runAction('vise')"> Viser </VBtn>
              <VBtn color="success" :loading="busy" @click="runAction('validate')"> Valider </VBtn>
              <VBtn color="error" :loading="busy" @click="runAction('reject')"> Rejeter </VBtn>
              <VBtn variant="tonal" :loading="busy" @click="runAction('archive')"> Archiver </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" lg="4">
        <VCard class="mb-6">
          <VCardTitle>Circuit / historique</VCardTitle>
          <VCardText>
            <VTimeline density="compact" side="end">
              <VTimelineItem v-for="action in document.actions" :key="action.id" size="x-small">
                <div class="font-weight-medium">
                  {{ action.action_type }}
                </div>
                <div class="text-caption">
                  {{ action.actor?.name }}
                  <span v-if="action.delegator"> (délégation de {{ action.delegator.name }})</span>
                </div>
                <div v-if="action.comment">
                  {{ action.comment }}
                </div>
              </VTimelineItem>
            </VTimeline>
          </VCardText>
        </VCard>

        <VCard>
          <VCardTitle>Créer une instruction</VCardTitle>
          <VCardText>
            <AppTextField v-model="instructionForm.title" label="Titre" class="mb-3" />
            <AppTextarea v-model="instructionForm.body" label="Instruction" class="mb-3" />
            <AppSelect
              v-model="instructionForm.assignee_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Responsable"
              class="mb-3"
            />
            <AppTextField v-model="instructionForm.due_date" type="date" label="Échéance" class="mb-3" />
            <VBtn block color="primary" :loading="busy" @click="createInstruction"> Enregistrer l'instruction </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
