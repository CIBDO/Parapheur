<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'AuditLog',
  },
})

const logs = ref<any[]>([])
const loading = ref(false)
const errorMessage = ref('')
const filters = ref({
  q: '',
  action: '',
})
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

const load = async (page = 1) => {
  loading.value = true
  errorMessage.value = ''
  try {
    const query: Record<string, string | number> = {
      page,
      per_page: 25,
    }
    if (filters.value.q)
      query.q = filters.value.q
    if (filters.value.action)
      query.action = filters.value.action

    const res = await $api('/audit-logs', { query })
    logs.value = res.data ?? []
    pagination.value = {
      current_page: res.current_page,
      last_page: res.last_page,
      total: res.total,
    }
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Impossible de charger le journal'
  }
  finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="mb-6">
      <h4 class="text-h4 mb-1">
        Journal d’audit
      </h4>
      <p class="text-body-1 mb-0 text-medium-emphasis">
        Traçabilité append-only des actions sensibles
      </p>
    </div>

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <AppTextField
              v-model="filters.q"
              label="Recherche"
              placeholder="Action, IP, type…"
              clearable
              @keyup.enter="load(1)"
            />
          </VCol>
          <VCol
            cols="12"
            md="5"
          >
            <AppTextField
              v-model="filters.action"
              label="Action"
              placeholder="document.transmitted"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-end"
          >
            <VBtn
              block
              color="primary"
              variant="tonal"
              :loading="loading"
              @click="load(1)"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VDataTable
        :items="logs"
        :loading="loading"
        :headers="[
          { title: 'Date', key: 'created_at' },
          { title: 'Acteur', key: 'user' },
          { title: 'Action', key: 'action' },
          { title: 'Cible', key: 'auditable' },
          { title: 'IP', key: 'ip_address' },
        ]"
        hide-default-footer
      >
        <template #item.created_at="{ item }">
          {{ item.created_at ? new Date(item.created_at).toLocaleString('fr-FR') : '—' }}
        </template>
        <template #item.user="{ item }">
          {{ item.user?.name || 'Système' }}
        </template>
        <template #item.auditable="{ item }">
          <span v-if="item.auditable_type">
            {{ String(item.auditable_type).split('\\').pop() }} #{{ item.auditable_id }}
          </span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>
      </VDataTable>

      <div
        v-if="pagination.last_page > 1"
        class="d-flex justify-center pa-4"
      >
        <VPagination
          :model-value="pagination.current_page"
          :length="pagination.last_page"
          @update:model-value="load"
        />
      </div>
    </VCard>
  </div>
</template>
