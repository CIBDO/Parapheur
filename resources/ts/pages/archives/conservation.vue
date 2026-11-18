<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'Archives' } })

const {
  loading,
  fetchRetentionRules,
  createRetentionRule,
  validateRetentionRule,
  fetchRetentionDue,
} = useArchives()

const rules = ref<any[]>([])
const due = ref<any[]>([])
const dialog = ref(false)
const form = ref({
  code: '',
  name: '',
  current_years: 5,
  intermediate_years: 5,
  final_disposition: 'elimination',
  legal_basis: '',
})

async function load() {
  const [r, d] = await Promise.all([fetchRetentionRules(), fetchRetentionDue()])
  rules.value = r.data || []
  due.value = d.data || []
}

async function create() {
  const rule = await createRetentionRule({ ...form.value })
  await validateRetentionRule(rule.id)
  dialog.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Conservation et échéances"
      subtitle="Tableaux de gestion versionnés (délais à valider institutionnellement)"
      icon="tabler-hourglass"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouvelle règle
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Règles de conservation</VCardTitle>
          </VCardItem>
          <VList :loading="loading">
            <VListItem
              v-for="rule in rules"
              :key="rule.id"
              :title="`${rule.code} v${rule.version} — ${rule.name}`"
              :subtitle="`${rule.current_years}+${rule.intermediate_years} ans → ${rule.final_disposition} · ${rule.is_validated ? 'validée' : 'brouillon'}`"
            />
            <VListItem
              v-if="!rules.length"
              title="Aucune règle"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="parapheur-section-card">
          <VCardItem>
            <VCardTitle>Échéances arrivées</VCardTitle>
          </VCardItem>
          <VList>
            <VListItem
              v-for="record in due"
              :key="record.id"
              :title="record.title"
              :subtitle="`${record.permanent_id} · échéance ${record.disposition_due_at}`"
            />
            <VListItem
              v-if="!due.length"
              title="Aucune échéance"
            />
          </VList>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Règle de conservation</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.code"
            label="Code"
          />
          <VTextField
            v-model="form.name"
            label="Libellé"
          />
          <VTextField
            v-model.number="form.current_years"
            type="number"
            label="Années courantes"
          />
          <VTextField
            v-model.number="form.intermediate_years"
            type="number"
            label="Années intermédiaires"
          />
          <VSelect
            v-model="form.final_disposition"
            :items="['conservation_definitive', 'transfert', 'reevaluation', 'elimination']"
            label="Sort final"
          />
          <VTextField
            v-model="form.legal_basis"
            label="Fondement (à valider Mali)"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="create"
          >
            Créer et valider
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
