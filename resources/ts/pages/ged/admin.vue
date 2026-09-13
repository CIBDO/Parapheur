<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: { action: 'manage', subject: 'GedAdmin' },
})

const tab = ref('categories')
const categories = ref<any[]>([])
const retentionRules = ref<any[]>([])
const classificationRules = ref<any[]>([])
const documentTypes = ref<any[]>([])
const classificationNodes = ref<any[]>([])
const loading = ref(false)
const dialog = ref(false)
const retentionDialog = ref(false)
const classificationDialog = ref(false)
const form = ref({ code: '', name: '', description: '', sort_order: 0 })
const retentionForm = ref({
  code: '',
  name: '',
  document_type_id: null as number | null,
  category_id: null as number | null,
  retention_years: 5,
  final_disposition: 'archiver',
  notes: '',
})
const classificationForm = ref({
  code: '',
  name: '',
  document_type_id: null as number | null,
  target_classification_node_id: null as number | null,
  trigger_status: 'valide',
  priority: 100,
})
const errorMessage = ref('')

const flattenNodes = (nodes: any[], depth = 0): any[] => {
  const rows: any[] = []
  for (const n of nodes || []) {
    rows.push({ ...n, label: `${'— '.repeat(depth)}${n.code} · ${n.name}` })
    if (n.children?.length)
      rows.push(...flattenNodes(n.children, depth + 1))
  }

  return rows
}

const loadCategories = async () => {
  loading.value = true
  try {
    const res = await $api('/ged/categories')
    categories.value = res.data || []
  }
  finally {
    loading.value = false
  }
}

const loadRetention = async () => {
  const res = await $api('/ged/retention-rules')
  retentionRules.value = res.data || []
}

const loadClassificationRules = async () => {
  const res = await $api('/ged/classification-rules')
  classificationRules.value = res.data || []
}

const loadReferentials = async () => {
  const [types, tree] = await Promise.all([
    $api('/meta/document-types'),
    $api('/ged/classification-nodes'),
  ])
  documentTypes.value = types.data || types || []
  classificationNodes.value = flattenNodes(tree.data || tree || [])
}

onMounted(async () => {
  await Promise.all([loadCategories(), loadRetention(), loadClassificationRules(), loadReferentials()])
})

watch(tab, async (v) => {
  if (v === 'retention')
    await loadRetention()
  if (v === 'auto-class')
    await loadClassificationRules()
})

const saveCategory = async () => {
  errorMessage.value = ''
  try {
    await $api('/ged/categories', { method: 'POST', body: form.value })
    dialog.value = false
    form.value = { code: '', name: '', description: '', sort_order: 0 }
    await loadCategories()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Erreur'
  }
}

const removeCategory = async (id: number) => {
  if (!confirm('Supprimer cette catégorie ?'))
    return
  try {
    await $api(`/ged/categories/${id}`, { method: 'DELETE' })
    await loadCategories()
  }
  catch (e: any) {
    alert(e?.data?.message || 'Impossible')
  }
}

const saveRetention = async () => {
  errorMessage.value = ''
  try {
    await $api('/ged/retention-rules', { method: 'POST', body: retentionForm.value })
    retentionDialog.value = false
    retentionForm.value = {
      code: '',
      name: '',
      document_type_id: null,
      category_id: null,
      retention_years: 5,
      final_disposition: 'archiver',
      notes: '',
    }
    await loadRetention()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Erreur'
  }
}

const saveClassificationRule = async () => {
  errorMessage.value = ''
  try {
    await $api('/ged/classification-rules', { method: 'POST', body: classificationForm.value })
    classificationDialog.value = false
    classificationForm.value = {
      code: '',
      name: '',
      document_type_id: null,
      target_classification_node_id: null,
      trigger_status: 'valide',
      priority: 100,
    }
    await loadClassificationRules()
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Erreur'
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Administration GED"
      subtitle="Catégories, conservation et classement automatique"
      icon="tabler-settings"
    />

    <VTabs
      v-model="tab"
      class="mb-4"
    >
      <VTab value="categories">
        Catégories
      </VTab>
      <VTab value="retention">
        Conservation
      </VTab>
      <VTab value="auto-class">
        Classement auto
      </VTab>
      <VTab
        value="classification"
        :to="{ name: 'ged-classification' }"
      >
        Plan de classement
      </VTab>
      <VTab
        value="types"
        :to="{ name: 'parapheur-document-types' }"
      >
        Types documentaires
      </VTab>
    </VTabs>

    <VCard
      v-if="tab === 'categories'"
      class="parapheur-section-card"
    >
      <VCardItem>
        <VCardTitle>Catégories documentaires</VCardTitle>
        <template #append>
          <VBtn
            color="primary"
            size="small"
            prepend-icon="tabler-plus"
            @click="dialog = true"
          >
            Ajouter
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VDataTable
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Description', key: 'description' },
          { title: 'Actif', key: 'is_active' },
          { title: '', key: 'actions' },
        ]"
        :items="categories"
        :loading="loading"
      >
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            label
          >
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="text"
            color="error"
            icon="tabler-trash"
            @click="removeCategory(item.id)"
          />
        </template>
      </VDataTable>
    </VCard>

    <VCard
      v-else-if="tab === 'retention'"
      class="parapheur-section-card"
    >
      <VCardItem>
        <VCardTitle>Règles de conservation</VCardTitle>
        <template #append>
          <VBtn
            color="primary"
            size="small"
            prepend-icon="tabler-plus"
            @click="retentionDialog = true"
          >
            Ajouter
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VDataTable
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Années', key: 'retention_years' },
          { title: 'Sort final', key: 'final_disposition' },
          { title: 'Type', key: 'document_type.name' },
          { title: 'Actif', key: 'is_active' },
        ]"
        :items="retentionRules"
      >
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            label
          >
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
      </VDataTable>
    </VCard>

    <VCard
      v-else-if="tab === 'auto-class'"
      class="parapheur-section-card"
    >
      <VCardItem>
        <VCardTitle>Règles de classement automatique</VCardTitle>
        <template #append>
          <VBtn
            color="primary"
            size="small"
            prepend-icon="tabler-plus"
            @click="classificationDialog = true"
          >
            Ajouter
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VDataTable
        :headers="[
          { title: 'Priorité', key: 'priority' },
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Type', key: 'document_type.name' },
          { title: 'Nœud cible', key: 'target_node.name' },
          { title: 'Déclencheur', key: 'trigger_status' },
          { title: 'Actif', key: 'is_active' },
        ]"
        :items="classificationRules"
      >
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            label
          >
            {{ item.is_active ? 'Oui' : 'Non' }}
          </VChip>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Nouvelle catégorie</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-3"
          >
            {{ errorMessage }}
          </VAlert>
          <AppTextField
            v-model="form.code"
            label="Code"
            class="mb-3"
          />
          <AppTextField
            v-model="form.name"
            label="Nom"
            class="mb-3"
          />
          <AppTextarea
            v-model="form.description"
            label="Description"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="dialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="saveCategory"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="retentionDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouvelle règle de conservation</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-3"
          >
            {{ errorMessage }}
          </VAlert>
          <AppTextField
            v-model="retentionForm.code"
            label="Code"
            class="mb-3"
          />
          <AppTextField
            v-model="retentionForm.name"
            label="Nom"
            class="mb-3"
          />
          <AppTextField
            v-model.number="retentionForm.retention_years"
            type="number"
            label="Durée (années)"
            class="mb-3"
          />
          <AppSelect
            v-model="retentionForm.final_disposition"
            :items="[
              { title: 'Archiver', value: 'archiver' },
              { title: 'Conserver', value: 'conserver' },
              { title: 'Détruire après validation', value: 'detruire_apres_validation' },
            ]"
            label="Sort final"
            class="mb-3"
          />
          <AppSelect
            v-model="retentionForm.document_type_id"
            :items="documentTypes.map(t => ({ title: t.name, value: t.id }))"
            label="Type documentaire (optionnel)"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="retentionForm.category_id"
            :items="categories.map(c => ({ title: c.name, value: c.id }))"
            label="Catégorie (optionnel)"
            clearable
            class="mb-3"
          />
          <AppTextarea
            v-model="retentionForm.notes"
            label="Notes"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="retentionDialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="saveRetention"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="classificationDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouvelle règle de classement</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-3"
          >
            {{ errorMessage }}
          </VAlert>
          <AppTextField
            v-model="classificationForm.code"
            label="Code"
            class="mb-3"
          />
          <AppTextField
            v-model="classificationForm.name"
            label="Nom"
            class="mb-3"
          />
          <AppSelect
            v-model="classificationForm.document_type_id"
            :items="documentTypes.map(t => ({ title: t.name, value: t.id }))"
            label="Type documentaire"
            clearable
            class="mb-3"
          />
          <AppSelect
            v-model="classificationForm.target_classification_node_id"
            :items="classificationNodes.map(n => ({ title: n.label, value: n.id }))"
            label="Nœud cible"
            class="mb-3"
          />
          <AppSelect
            v-model="classificationForm.trigger_status"
            :items="[
              { title: 'Validé', value: 'valide' },
              { title: 'Traité', value: 'traite' },
              { title: 'Archivé', value: 'archive' },
            ]"
            label="Statut déclencheur"
            class="mb-3"
          />
          <AppTextField
            v-model.number="classificationForm.priority"
            type="number"
            label="Priorité (plus petit = prioritaire)"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="classificationDialog = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            @click="saveClassificationRule"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
