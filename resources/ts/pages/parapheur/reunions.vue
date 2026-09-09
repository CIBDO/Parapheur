<script setup lang="ts">
definePage({
  meta: {
    action: 'manage',
    subject: 'Meeting',
  },
});

const meetings = ref<any[]>([]);
const users = ref<any[]>([]);
const dialog = ref(false);
const form = ref({
  title: '',
  meeting_date: '',
  meeting_time: '',
  location: '',
  chair_id: null as number | null,
  agenda: '',
  participant_ids: [] as number[],
});

const load = async () => {
  const res = await $api('/meetings');
  meetings.value = res.data ?? res;
};

onMounted(async () => {
  users.value = await $api('/meta/users');
  await load();
});

const createMeeting = async () => {
  await $api('/meetings', {
    method: 'POST',
    body: form.value,
  });
  dialog.value = false;
  await load();
};
</script>

<template>
  <div>
    <div class="d-flex justify-space-between mb-4">
      <h4 class="text-h4">Réunions</h4>
      <VBtn color="primary" @click="dialog = true"> Nouvelle réunion </VBtn>
    </div>

    <VRow>
      <VCol v-for="meeting in meetings" :key="meeting.id" cols="12" md="6">
        <VCard>
          <VCardTitle>{{ meeting.title }}</VCardTitle>
          <VCardSubtitle>
            {{ meeting.meeting_date }} {{ meeting.meeting_time || '' }} · {{ meeting.location || 'Lieu non précisé' }}
          </VCardSubtitle>
          <VCardText>
            <div class="mb-2"><strong>Président :</strong> {{ meeting.chair?.name || '—' }}</div>
            <div class="mb-2"><strong>Participants :</strong> {{ meeting.participants?.length || 0 }}</div>
            <div style="white-space: pre-wrap">
              {{ meeting.agenda }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog v-model="dialog" max-width="640">
      <VCard>
        <VCardTitle>Créer une réunion</VCardTitle>
        <VCardText>
          <AppTextField v-model="form.title" label="Objet" class="mb-3" />
          <AppTextField v-model="form.meeting_date" type="date" label="Date" class="mb-3" />
          <AppTextField v-model="form.meeting_time" type="time" label="Heure" class="mb-3" />
          <AppTextField v-model="form.location" label="Lieu" class="mb-3" />
          <AppSelect v-model="form.chair_id" :items="users" item-title="name" item-value="id" label="Président" class="mb-3" />
          <AppSelect
            v-model="form.participant_ids"
            :items="users"
            item-title="name"
            item-value="id"
            label="Participants"
            multiple
            chips
            class="mb-3"
          />
          <AppTextarea v-model="form.agenda" label="Ordre du jour" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false"> Annuler </VBtn>
          <VBtn color="primary" @click="createMeeting"> Créer </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
