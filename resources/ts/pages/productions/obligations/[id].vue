<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { productionFrequencyLabels } from '@/utils/tasksUi'

definePage({
  meta: {
    action: 'manage',
    subject: 'Production',
  },
})

const route = useRoute()
const router = useRouter()
const item = ref<any>(null)
const loading = ref(false)

const actorLabel = (actor: any) => {
  const labels: Record<string, string> = {
    contributor: 'Contributeur',
    validator: 'Validateur',
    recipient: 'Destinataire',
  }

  return labels[actor.role] || actor.role
}

const reminderLabel = (rule: any) => {
  const when = rule.offset_type === 'before'
    ? `${rule.offset_minutes} min avant`
    : rule.offset_type === 'after'
      ? `${rule.offset_minutes} min après`
      : 'à l’échéance'

  return `${when} → ${rule.target}${rule.escalate ? ' (escalade)' : ''}`
}

const load = async () => {
  loading.value = true
  try {
    item.value = await $api(`/productions/obligations/${route.params.id}`)
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      :title="item?.label || 'Obligation'"
      :subtitle="item?.code || 'Fiche obligation'"
      icon="tabler-settings"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="load"
        >
          Actualiser
        </VBtn>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          @click="router.push('/productions/obligations')"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <div
      v-if="loading && !item"
      class="text-center py-12"
    >
      <VProgressCircular indeterminate />
    </div>

    <VRow
      v-else-if="item"
      dense
    >
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="mb-4">
          <VCardText class="pa-5">
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="flat"
              >
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-repeat"
              >
                {{ productionFrequencyLabels[item.frequency] || item.frequency }}
              </VChip>
              <VChip
                size="small"
                variant="tonal"
                prepend-icon="tabler-clock"
              >
                {{ item.deadline_time || '—' }} · {{ item.timezone }}
              </VChip>
            </div>
            <div class="text-body-1 text-high-emphasis mb-1">
              Description
            </div>
            <div
              class="text-body-2"
              :class="{ 'text-medium-emphasis': !item.description }"
            >
              {{ item.description || 'Aucune description renseignée.' }}
            </div>
          </VCardText>
        </VCard>

        <VCard class="mb-4">
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-users"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Acteurs
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VList v-if="item.actors?.length">
            <VListItem
              v-for="actor in item.actors"
              :key="actor.id"
              :title="actor.user?.name || actor.role_name || actor.structure?.name || '—'"
              :subtitle="actorLabel(actor)"
            />
          </VList>
          <div
            v-else
            class="text-center py-8 text-medium-emphasis"
          >
            Aucun acteur configuré
          </div>
        </VCard>

        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar
                color="warning"
                variant="tonal"
                rounded
                size="36"
              >
                <VIcon
                  icon="tabler-bell"
                  size="20"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-h6">
              Rappels
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VList v-if="item.reminder_rules?.length">
            <VListItem
              v-for="rule in item.reminder_rules"
              :key="rule.id"
              :title="reminderLabel(rule)"
            />
          </VList>
          <div
            v-else
            class="text-center py-8 text-medium-emphasis"
          >
            Aucune règle de rappel
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardItem>
            <VCardTitle class="text-h6">
              Paramètres
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <VList density="compact">
            <VListItem
              title="Producteur"
              :subtitle="item.producer?.name || '—'"
            />
            <VListItem
              title="Suppléant"
              :subtitle="item.deputy?.name || '—'"
            />
            <VListItem
              title="Structure"
              :subtitle="item.structure?.name || '—'"
            />
            <VListItem
              title="Validation"
              :subtitle="item.validation_required ? `Obligatoire (${item.validation_mode})` : 'Facultative'"
            />
            <VListItem
              title="Transmission"
              :subtitle="item.auto_transmit ? 'Automatique' : 'Manuelle'"
            />
            <VListItem
              title="Formats"
              :subtitle="(item.allowed_formats || []).join(', ') || '—'"
            />
          </VList>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
