<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'Appointment',
  },
})

const users = ref<any[]>([])
const items = ref<any[]>([])
const loading = ref(false)
const form = ref({
  user_id: null as number | null,
  kind: 'mission',
  title: '',
  description: '',
  start_at: '',
  end_at: '',
  location: '',
  confidentiality: 'restreint',
  blocks_calendar: true,
})

const load = async () => {
  loading.value = true
  try {
    const [list, u] = await Promise.all([
      $api('/appointments/unavailabilities'),
      $api('/meta/users'),
    ])
    items.value = list.data ?? list
    users.value = u
    if (!form.value.user_id && u[0])
      form.value.user_id = u.find((x: any) => x.email === 'dg@dgtcp.local')?.id || u[0].id
  }
  finally {
    loading.value = false
  }
}

const save = async () => {
  await $api('/appointments/unavailabilities', {
    method: 'POST',
    body: {
      ...form.value,
      start_at: new Date(form.value.start_at).toISOString(),
      end_at: new Date(form.value.end_at).toISOString(),
    },
  })
  form.value.title = ''
  form.value.description = ''
  await load()
}

const remove = async (id: number) => {
  await $api(`/appointments/unavailabilities/${id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Indisponibilités"
      subtitle="Missions, absences, créneaux réservés du Directeur"
      icon="tabler-calendar-off"
    />

    <VCard class="mb-4">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="form.user_id"
              :items="users"
              item-title="name"
              item-value="id"
              label="Directeur / agent"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="form.kind"
              :items="[
                { title: 'Mission', value: 'mission' },
                { title: 'Congé', value: 'conge' },
                { title: 'Déplacement', value: 'deplacement' },
                { title: 'Indisponibilité', value: 'indisponibilite' },
                { title: 'Absence', value: 'absence' },
                { title: 'Créneau réservé', value: 'creneau_reserve' },
              ]"
              label="Type"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.title"
              label="Libellé"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.start_at"
              type="datetime-local"
              label="Début"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.end_at"
              type="datetime-local"
              label="Fin"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.location"
              label="Lieu"
            />
          </VCol>
        </VRow>
        <VBtn
          color="primary"
          class="mt-2"
          :disabled="!form.title || !form.start_at || !form.end_at"
          @click="save"
        >
          Bloquer la période
        </VBtn>
      </VCardText>
    </VCard>

    <VCard>
      <VProgressLinear
        v-if="loading"
        indeterminate
      />
      <VList>
        <VListItem
          v-for="item in items"
          :key="item.id"
        >
          <VListItemTitle>{{ item.title }}</VListItemTitle>
          <VListItemSubtitle>
            {{ item.user?.name }} · {{ new Date(item.start_at).toLocaleString('fr-FR') }} → {{ new Date(item.end_at).toLocaleString('fr-FR') }}
          </VListItemSubtitle>
          <template #append>
            <VBtn
              icon
              variant="text"
              color="error"
              @click="remove(item.id)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
        </VListItem>
      </VList>
    </VCard>
  </div>
</template>
