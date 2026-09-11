<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'manage',
    subject: 'MeetingTemplate',
  },
})

interface MeetingTemplateItem {
  id: number
  kind: string
  name: string
  body: string
  is_default: boolean
  is_active: boolean
}

const kindOptions = [
  { value: 'convocation', title: 'Convocation' },
  { value: 'agenda', title: 'Ordre du jour' },
  { value: 'attendance', title: 'Liste de présence' },
  { value: 'decisions', title: 'Relevé de décisions' },
  { value: 'cr_simple', title: 'Compte rendu simple' },
  { value: 'cr_detaille', title: 'Compte rendu détaillé' },
  { value: 'pv', title: 'Procès-verbal' },
  { value: 'releve_decisions', title: 'Relevé décisions (CR)' },
]

const items = ref<MeetingTemplateItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const isDialogOpen = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  kind: 'convocation',
  name: '',
  body: '',
  is_default: false,
  is_active: true,
})

const dialogTitle = computed(() =>
  editingId.value ? 'Modifier le modèle' : 'Nouveau modèle de document',
)

const kindLabel = (kind: string) =>
  kindOptions.find(k => k.value === kind)?.title || kind

const placeholders = [
  'reference',
  'title',
  'object',
  'date',
  'time',
  'end_time',
  'location',
  'chair',
  'secretary',
  'structure',
  'participants',
  'agenda',
  'observations',
  'signatory',
] as const

const placeholderToken = (key: string) => `{{${key}}}`

const insertPlaceholder = (key: string) => {
  const token = placeholderToken(key)
  const current = form.value.body || ''
  const needsSpace = current.length > 0 && !/\s$/.test(current)

  form.value.body = `${current}${needsSpace ? ' ' : ''}${token}`
}

const extractError = (e: any) => {
  const errors = e?.data?.errors
  const firstError = errors ? Object.values(errors).flat()[0] : null

  return String(firstError || e?.data?.message || 'Échec de l’opération')
}

const load = async () => {
  loading.value = true
  try {
    items.value = await $api('/meeting-templates')
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    loading.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = {
    kind: 'convocation',
    name: '',
    body: '<h1>{{title}}</h1>\n<p>Référence : {{reference}}</p>\n<p>Date : {{date}} — {{time}}</p>\n<p>Lieu : {{location}}</p>\n<p>Président : {{chair}}</p>\n<p>{{participants}}</p>\n<p>{{agenda}}</p>\n<p>{{observations}}</p>',
    is_default: false,
    is_active: true,
  }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const openEdit = (item: MeetingTemplateItem) => {
  editingId.value = item.id
  form.value = {
    kind: item.kind,
    name: item.name,
    body: item.body,
    is_default: item.is_default,
    is_active: item.is_active,
  }
  errorMessage.value = ''
  isDialogOpen.value = true
}

const save = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    if (editingId.value) {
      await $api(`/meeting-templates/${editingId.value}`, { method: 'PUT', body: form.value })
      successMessage.value = 'Modèle mis à jour'
    }
    else {
      await $api('/meeting-templates', { method: 'POST', body: form.value })
      successMessage.value = 'Modèle créé'
    }
    isDialogOpen.value = false
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
  finally {
    saving.value = false
  }
}

const remove = async (item: MeetingTemplateItem) => {
  if (!confirm(`Supprimer le modèle « ${item.name} » ?`))
    return
  try {
    await $api(`/meeting-templates/${item.id}`, { method: 'DELETE' })
    successMessage.value = 'Modèle supprimé'
    await load()
  }
  catch (e: any) {
    errorMessage.value = extractError(e)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Modèles de documents réunion"
      subtitle="Convocations, ODJ, présence et comptes rendus personnalisables"
      icon="tabler-template"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Nouveau modèle
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMessage = ''"
    >
      {{ successMessage }}
    </VAlert>
    <VAlert
      v-if="errorMessage && !isDialogOpen"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <VCard>
      <VDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Nature', key: 'kind' },
          { title: 'Nom', key: 'name' },
          { title: 'Défaut', key: 'is_default' },
          { title: 'Statut', key: 'is_active' },
          { title: '', key: 'actions', sortable: false },
        ]"
      >
        <template #item.kind="{ item }">
          {{ kindLabel(item.kind) }}
        </template>
        <template #item.is_default="{ item }">
          <VChip
            v-if="item.is_default"
            size="small"
            color="primary"
            label
          >
            Défaut
          </VChip>
          <span v-else>—</span>
        </template>
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            label
            :color="item.is_active ? 'success' : 'secondary'"
          >
            {{ item.is_active ? 'Actif' : 'Inactif' }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <IconBtn @click="openEdit(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn
              color="error"
              @click="remove(item)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="isDialogOpen"
      max-width="840"
      persistent
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ dialogTitle }}</VCardTitle>
        </VCardItem>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorMessage }}
          </VAlert>
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.kind"
                :items="kindOptions"
                label="Nature"
              />
            </VCol>
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="form.name"
                label="Nom du modèle"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-body-2 mb-2">
                Contenu du modèle
              </div>
              <TiptapEditor
                v-model="form.body"
                placeholder="Rédigez le modèle… Utilisez les balises ci-dessous pour les champs dynamiques."
                class="meeting-template-editor border rounded"
              />
              <div class="text-caption text-medium-emphasis mt-3 mb-2">
                Champs dynamiques (cliquer pour insérer) :
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="key in placeholders"
                  :key="key"
                  size="small"
                  label
                  variant="tonal"
                  color="primary"
                  class="cursor-pointer"
                  @click="insertPlaceholder(key)"
                >
                  {{ placeholderToken(key) }}
                </VChip>
              </div>
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_default"
                label="Modèle par défaut pour cette nature"
                color="primary"
                hide-details
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                label="Actif"
                color="primary"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-6 pb-5">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="isDialogOpen = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="save"
          >
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped lang="scss">
.meeting-template-editor {
  :deep(.ProseMirror) {
    min-block-size: 220px;
    max-block-size: 420px;
    overflow: auto;
    padding-inline: 1rem;
    padding-block: 0.75rem;
  }
}
</style>
