<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import {
  publicationStatusColor,
  publicationStatusLabels,
} from '@/utils/gedUi'
import {
  formatDateFr,
  labelOf,
} from '@/utils/parapheurUi'

definePage({
  name: 'ged-a-valider',
  meta: {
    action: 'validate',
    subject: 'Ged',
  },
})

const router = useRouter()
const loading = ref(true)
const items = ref<any[]>([])

const load = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/documents', {
      query: { scope: 'to_validate', per_page: 50, sort: 'updated_at', dir: 'desc' },
    })
    items.value = res.data || []
  }
  finally {
    loading.value = false
  }
}

const openDoc = (id: number) => router.push({ name: 'ged-id', params: { id } })

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Documents à valider"
      subtitle="File de validation et publication institutionnelle"
      icon="tabler-file-check"
    />

    <VCard class="parapheur-section-card">
      <VCardText>
        <div
          v-if="loading"
          class="text-center py-8"
        >
          <VProgressCircular indeterminate />
        </div>
        <VTable v-else>
          <thead>
            <tr>
              <th>Référence</th>
              <th>Titre</th>
              <th>Statut publication</th>
              <th>Auteur</th>
              <th>Mis à jour</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="doc in items"
              :key="doc.id"
            >
              <td>{{ doc.reference }}</td>
              <td>{{ doc.title || doc.object }}</td>
              <td>
                <VChip
                  size="small"
                  :color="publicationStatusColor[doc.publication_status] || 'warning'"
                >
                  {{ labelOf(publicationStatusLabels, doc.publication_status) }}
                </VChip>
              </td>
              <td>{{ doc.author?.name }}</td>
              <td>{{ formatDateFr(doc.updated_at) }}</td>
              <td>
                <VBtn
                  size="small"
                  color="primary"
                  variant="tonal"
                  @click="openDoc(doc.id)"
                >
                  Traiter
                </VBtn>
              </td>
            </tr>
            <tr v-if="!items.length">
              <td
                colspan="6"
                class="text-center text-medium-emphasis py-8"
              >
                Aucun document en attente de validation.
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </div>
</template>
