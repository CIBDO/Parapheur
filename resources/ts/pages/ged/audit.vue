<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'GedAdmin',
  },
})

const loading = ref(true)
const items = ref<any[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const actionFilter = ref('')

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/audit-logs', {
      query: {
        page: page.value,
        per_page: perPage.value,
        action: actionFilter.value || undefined,
      },
    })
    items.value = res.data || []
    total.value = res.total || 0
  }
  catch {
    items.value = []
  }
  finally {
    loading.value = false
  }
}

watch([page, perPage], load)
onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Journal d’audit documentaire"
      subtitle="Dépôts, validations, publications, partages et permissions"
      icon="tabler-shield-search"
    />

    <VCard class="mb-4 parapheur-section-card">
      <VCardText class="d-flex gap-3 flex-wrap">
        <AppTextField
          v-model="actionFilter"
          label="Filtrer par action…"
          clearable
          style="max-width: 280px"
          @keyup.enter="() => { page = 1; load() }"
        />
        <VBtn
          color="primary"
          @click="() => { page = 1; load() }"
        >
          Filtrer
        </VBtn>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="[
          { title: 'Date', key: 'created_at' },
          { title: 'Action', key: 'action' },
          { title: 'Utilisateur', key: 'user' },
          { title: 'Objet', key: 'auditable' },
          { title: 'IP', key: 'ip_address' },
        ]"
        :items="items"
        :items-length="total"
        :loading="loading"
      >
        <template #item.created_at="{ item }">
          {{ formatDateFr(item.created_at) }}
        </template>
        <template #item.user="{ item }">
          {{ item.user?.name || '—' }}
        </template>
        <template #item.auditable="{ item }">
          <span class="text-caption">
            {{ item.auditable_type?.split('\\').pop() }} #{{ item.auditable_id }}
          </span>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
