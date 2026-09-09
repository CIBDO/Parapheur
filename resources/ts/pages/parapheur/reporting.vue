<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'Reporting',
  },
});

const dg = ref<any>(null);
const direction = ref<any>(null);

onMounted(async () => {
  try {
    dg.value = await $api('/dashboard/dg');
  } catch {}
  try {
    direction.value = await $api('/dashboard/direction');
  } catch {}
});
</script>

<template>
  <div>
    <h4 class="text-h4 mb-6">Reporting</h4>

    <VRow>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>Indicateurs DG</VCardTitle>
          <VCardText v-if="dg">
            <VList density="compact">
              <VListItem title="Dossiers reçus" :subtitle="String(dg.received)" />
              <VListItem title="À traiter" :subtitle="String(dg.to_process)" />
              <VListItem title="Urgents" :subtitle="String(dg.urgent)" />
              <VListItem title="En retard" :subtitle="String(dg.overdue)" />
              <VListItem title="Validés" :subtitle="String(dg.validated)" />
              <VListItem title="Retournés" :subtitle="String(dg.returned)" />
              <VListItem title="Instructions ouvertes" :subtitle="String(dg.instructions_open)" />
              <VListItem title="Instructions en retard" :subtitle="String(dg.instructions_late)" />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard>
          <VCardTitle>Indicateurs direction</VCardTitle>
          <VCardText v-if="direction">
            <VList density="compact">
              <VListItem title="Documents préparés" :subtitle="String(direction.prepared)" />
              <VListItem title="En validation interne" :subtitle="String(direction.in_validation)" />
              <VListItem title="Transmis DG" :subtitle="String(direction.sent_dg)" />
              <VListItem title="Retournés" :subtitle="String(direction.returned)" />
              <VListItem title="Validés" :subtitle="String(direction.validated)" />
              <VListItem title="En retard" :subtitle="String(direction.overdue)" />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
