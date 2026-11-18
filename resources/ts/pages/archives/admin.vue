<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: { action: 'manage', subject: 'ArchivesAdmin' },
})

const router = useRouter()
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
        cols="12"
        md="4"
      >
        <VCard
          class="parapheur-section-card cursor-pointer"
          @click="router.push({ name: 'archives-fonds' })"
        >
          <VCardText>
            <div class="text-h6">
              Fonds et séries
            </div>
            <div class="text-medium-emphasis">
              Gérer le référentiel intellectuel
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="4"
      >
        <VCard
          class="parapheur-section-card cursor-pointer"
          @click="router.push({ name: 'archives-classement' })"
        >
          <VCardText>
            <div class="text-h6">
              Plan de classement
            </div>
            <div class="text-medium-emphasis">
              Nœuds versionnés
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
