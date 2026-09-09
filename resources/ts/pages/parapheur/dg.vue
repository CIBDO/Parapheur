<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'DashboardDg',
  },
});

const stats = ref<any>(null);
const documents = ref<any[]>([]);

onMounted(async () => {
  stats.value = await $api('/dashboard/dg');
  const res = await $api('/parapheur/documents', { query: { folder: 'a_valider' } });
  documents.value = res.data ?? res;
});
</script>

<template>
  <div>
    <h4 class="text-h4 mb-1">Bureau du Directeur Général</h4>
    <p class="text-body-1 mb-6">Vue orientée action — documents à traiter</p>

    <VRow v-if="stats" class="mb-6">
      <VCol cols="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-h4">
              {{ stats.to_process }}
            </div>
            <div>À traiter</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard color="error" variant="tonal">
          <VCardText>
            <div class="text-h4">
              {{ stats.urgent }}
            </div>
            <div>Urgents</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-h4">
              {{ stats.overdue }}
            </div>
            <div>En retard</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-h4">
              {{ stats.instructions_open }}
            </div>
            <div>Instructions ouvertes</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardTitle>Documents à valider</VCardTitle>
      <VList lines="two">
        <VListItem v-for="doc in documents" :key="doc.id" :to="{ name: 'parapheur-id', params: { id: doc.id } }">
          <VListItemTitle>{{ doc.structure?.code }} — {{ doc.object }}</VListItemTitle>
          <VListItemSubtitle> {{ doc.expected_action }} · {{ doc.priority }} </VListItemSubtitle>
          <template #append>
            <VBtn size="small" color="primary"> Ouvrir </VBtn>
          </template>
        </VListItem>
      </VList>
    </VCard>
  </div>
</template>
