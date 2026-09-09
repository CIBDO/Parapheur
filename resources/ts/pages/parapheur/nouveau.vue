<script setup lang="ts">
definePage({
  meta: {
    action: 'create',
    subject: 'Document',
  },
});

const router = useRouter();

const documentTypes = ref<Array<{ id: number; name: string }>>([]);
const structures = ref<Array<{ id: number; name: string; code: string }>>([]);
const users = ref<Array<{ id: number; name: string; email: string }>>([]);

const form = ref({
  object: '',
  reference: '',
  document_type_id: null as number | null,
  structure_id: null as number | null,
  priority: 'normale',
  confidentiality: 'normal',
  expected_action: 'validation',
  due_date: '',
  transmit_to: null as number | null,
  transmit_message: '',
});

const mainFile = ref<File[]>([]);
const attachments = ref<File[]>([]);
const saving = ref(false);
const errorMessage = ref('');

onMounted(async () => {
  const [types, structs, people] = await Promise.all([$api('/meta/document-types'), $api('/meta/structures'), $api('/meta/users')]);
  documentTypes.value = types;
  structures.value = structs;
  users.value = people;
});

const submit = async () => {
  errorMessage.value = '';
  saving.value = true;
  try {
    const body = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      if (value !== null && value !== '') body.append(key, String(value));
    });
    if (mainFile.value[0]) body.append('main_file', mainFile.value[0]);
    attachments.value.forEach(file => body.append('attachments[]', file));

    const doc = await $api('/parapheur/documents', {
      method: 'POST',
      body,
    });

    await router.push({ name: 'parapheur-id', params: { id: doc.id } });
  } catch (e: any) {
    errorMessage.value = e?.data?.message || "Échec de l'enregistrement";
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <VCard>
    <VCardTitle>Nouveau document</VCardTitle>
    <VCardText>
      <VAlert v-if="errorMessage" type="error" class="mb-4">
        {{ errorMessage }}
      </VAlert>

      <VRow>
        <VCol cols="12">
          <AppTextField v-model="form.object" label="Objet" required />
        </VCol>
        <VCol cols="12" md="6">
          <AppTextField v-model="form.reference" label="Référence (auto si vide)" />
        </VCol>
        <VCol cols="12" md="6">
          <AppSelect v-model="form.document_type_id" :items="documentTypes" item-title="name" item-value="id" label="Type de document" />
        </VCol>
        <VCol cols="12" md="6">
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            :item-title="(i: any) => `${i.code} — ${i.name}`"
            item-value="id"
            label="Structure émettrice"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppSelect
            v-model="form.expected_action"
            :items="[
              { title: 'Pour information', value: 'information' },
              { title: 'Pour consultation', value: 'consultation' },
              { title: 'Pour avis', value: 'avis' },
              { title: 'Pour observations', value: 'observations' },
              { title: 'Pour instruction', value: 'instruction' },
              { title: 'Pour visa', value: 'visa' },
              { title: 'Pour validation', value: 'validation' },
            ]"
            label="Action attendue"
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppSelect
            v-model="form.priority"
            :items="[
              { title: 'Normale', value: 'normale' },
              { title: 'Importante', value: 'importante' },
              { title: 'Urgente', value: 'urgente' },
              { title: 'Très urgente', value: 'tres_urgente' },
            ]"
            label="Priorité"
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppSelect
            v-model="form.confidentiality"
            :items="[
              { title: 'Normal', value: 'normal' },
              { title: 'Restreint', value: 'restreint' },
              { title: 'Confidentiel', value: 'confidentiel' },
              { title: 'Très confidentiel', value: 'tres_confidentiel' },
            ]"
            label="Confidentialité"
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppTextField v-model="form.due_date" type="date" label="Date limite" />
        </VCol>
        <VCol cols="12">
          <VFileInput v-model="mainFile" label="Document principal" show-size />
        </VCol>
        <VCol cols="12">
          <VFileInput v-model="attachments" label="Pièces jointes" multiple show-size />
        </VCol>
        <VCol cols="12" md="6">
          <AppSelect
            v-model="form.transmit_to"
            :items="users"
            item-title="name"
            item-value="id"
            label="Transmettre à (optionnel)"
            clearable
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppTextField v-model="form.transmit_message" label="Message de transmission" />
        </VCol>
      </VRow>
    </VCardText>
    <VCardActions>
      <VSpacer />
      <VBtn variant="text" :to="{ name: 'parapheur' }"> Annuler </VBtn>
      <VBtn color="primary" :loading="saving" @click="submit"> Enregistrer </VBtn>
    </VCardActions>
  </VCard>
</template>
