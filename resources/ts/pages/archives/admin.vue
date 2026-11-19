<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: { action: 'manage', subject: 'ArchivesAdmin' },
})

const router = useRouter()

const links = [
  {
    title: 'Fonds et séries',
    subtitle: 'Référentiel intellectuel',
    to: 'archives-fonds',
    icon: 'tabler-hierarchy-2',
  },
  {
    title: 'Plan de classement',
    subtitle: 'Nœuds versionnés',
    to: 'archives-classement',
    icon: 'tabler-list-tree',
  },
  {
    title: 'Magasins et emplacements',
    subtitle: 'Hiérarchie physique',
    to: 'archives-magasins',
    icon: 'tabler-building-warehouse',
  },
  {
    title: 'Conservation',
    subtitle: 'Règles et échéances',
    to: 'archives-conservation',
    icon: 'tabler-hourglass',
  },
  {
    title: 'Sort final',
    subtitle: 'Élimination et transferts',
    to: 'archives-sort-final',
    icon: 'tabler-trash',
  },
  {
    title: 'Rapports',
    subtitle: 'Indicateurs et exports',
    to: 'archives-rapports',
    icon: 'tabler-chart-bar',
  },
]
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Administration des archives"
      subtitle="Paramétrage du référentiel SAE"
      icon="tabler-settings"
    />
    <VRow>
      <VCol
        v-for="link in links"
        :key="link.to"
        cols="12"
        md="4"
      >
        <VCard
          class="parapheur-section-card cursor-pointer"
          @click="router.push({ name: link.to })"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
            >
              <VIcon :icon="link.icon" />
            </VAvatar>
            <div>
              <div class="text-h6">
                {{ link.title }}
              </div>
              <div class="text-medium-emphasis">
                {{ link.subtitle }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
