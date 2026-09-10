<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'AppointmentType',
  },
})

const items = ref<any[]>([])
const form = ref({
  code: '',
  name: '',
  default_duration_minutes: 30,
  blocks_calendar: true,
  is_active: true,
  sort_order: 0,
})

const load = async () => {
  items.value = await $api('/appointment-types')
}

const save = async () => {
  await $api('/appointment-types', { method: 'POST', body: form.value })
  form.value = { code: '', name: '', default_duration_minutes: 30, blocks_calendar: true, is_active: true, sort_order: 0 }
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Types de rendez-vous"
      subtitle="Référentiel administrable (audiences, partenaires, visios…)"
      icon="tabler-calendar-stats"
    />

    <VCard class="mb-4">
      <VCardText>
        <VRow>
          <VCol cols="12" md="3">
            <VTextField v-model="form.code" label="Code" />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model="form.name" label="Libellé" />
          </VCol>
          <VCol cols="12" md="2">
            <VTextField v-model="form.default_duration_minutes" type="number" label="Durée (min)" />
          </VCol>
          <VCol cols="12" md="3" class="d-flex align-center">
            <VBtn color="primary" :disabled="!form.code || !form.name" @click="save">
              Ajouter
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Code</th>
            <th>Nom</th>
            <th>Durée</th>
            <th>Bloquant</th>
            <th>Actif</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.name }}</td>
            <td>{{ row.default_duration_minutes }} min</td>
            <td>{{ row.blocks_calendar ? 'Oui' : 'Non' }}</td>
            <td>{{ row.is_active ? 'Oui' : 'Non' }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
