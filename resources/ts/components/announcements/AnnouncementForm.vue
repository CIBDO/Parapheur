<script setup lang="ts">
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { useAnnouncementStore } from '@/stores/announcements'

const props = defineProps<{
  announcementId?: number
}>()

const emit = defineEmits<{
  saved: [id: number]
}>()

const router = useRouter()
const announcementStore = useAnnouncementStore()
const error = ref('')
const saving = ref(false)
const preview = ref<{ count: number; sample: { id: number; name: string }[] } | null>(null)

const categories = ref<any[]>([])
const importances = ref<any[]>([])
const structures = ref<any[]>([])
const users = ref<any[]>([])

const form = reactive({
  title: '',
  summary: '',
  body: '<p></p>',
  category_id: null as number | null,
  importance_id: null as number | null,
  structure_id: null as number | null,
  confidentiality: 'general',
  acknowledgement_required: false,
  banner_enabled: false,
  banner_priority: 0,
  banner_scroll: true,
  banner_duration_seconds: 8,
  dismissible: true,
  publish_at: '',
  expires_at: '',
  banner_starts_at: '',
  banner_ends_at: '',
  target: 'all' as 'all' | 'structure' | 'role' | 'user',
  structure_ids: [] as number[],
  include_descendants: true,
  role_name: '',
  user_ids: [] as number[],
  excluded_user_ids: [] as number[],
  links: [] as { label: string; url: string }[],
  document_ids: [] as number[],
  document_input: '',
})

const roles = [
  'Directeur Général',
  'DGA',
  'Secrétariat DG',
  'Directeur',
  'Chef de division',
  'Chef de section',
  'Agent',
  'Conseiller',
  'Lecteur',
]

const confidentialities = [
  { title: 'Générale', value: 'general' },
  { title: 'Interne ciblée', value: 'targeted' },
  { title: 'Restreinte', value: 'restricted' },
  { title: 'Confidentielle', value: 'confidential' },
]

function audiences() {
  const rules: Record<string, unknown>[] = []
  if (form.target === 'all')
    rules.push({ mode: 'include', target_type: 'all' })
  if (form.target === 'structure') {
    for (const id of form.structure_ids)
      rules.push({ mode: 'include', target_type: 'structure', structure_id: id, include_descendants: form.include_descendants })
  }
  if (form.target === 'role' && form.role_name)
    rules.push({ mode: 'include', target_type: 'role', role_name: form.role_name, structure_id: form.structure_ids[0] ?? null, include_descendants: form.include_descendants })
  if (form.target === 'user') {
    for (const id of form.user_ids)
      rules.push({ mode: 'include', target_type: 'user', user_id: id })
  }
  for (const id of form.excluded_user_ids)
    rules.push({ mode: 'exclude', target_type: 'user', user_id: id })

  return rules
}

function payload() {
  return {
    title: form.title,
    summary: form.summary || null,
    body: form.body,
    category_id: form.category_id,
    importance_id: form.importance_id,
    structure_id: form.structure_id,
    confidentiality: form.confidentiality,
    acknowledgement_required: form.acknowledgement_required,
    banner_enabled: form.banner_enabled,
    banner_priority: form.banner_priority,
    banner_scroll: form.banner_scroll,
    banner_duration_seconds: form.banner_duration_seconds,
    dismissible: form.dismissible,
    publish_at: form.publish_at || null,
    expires_at: form.expires_at || null,
    banner_starts_at: form.banner_starts_at || null,
    banner_ends_at: form.banner_ends_at || null,
    audiences: audiences(),
    links: form.links.filter(link => link.label && link.url),
    document_ids: form.document_ids,
  }
}

async function loadMeta() {
  const [cats, levels, structs, people] = await Promise.all([
    $api<any[]>('/announcement-categories'),
    $api<any[]>('/announcement-importances'),
    $api<any[]>('/meta/structures'),
    $api<any[]>('/meta/users'),
  ])
  categories.value = cats.filter(item => item.is_active !== false)
  importances.value = levels
  structures.value = structs
  users.value = people
}

async function loadExisting() {
  if (!props.announcementId)
    return
  const data = await $api<any>(`/announcements/${props.announcementId}`)
  form.title = data.title
  form.summary = data.summary ?? ''
  form.body = data.body || '<p></p>'
  form.category_id = data.category?.id ?? null
  form.importance_id = data.importance?.id ?? null
  form.structure_id = data.structure?.id ?? null
  form.confidentiality = data.confidentiality
  form.acknowledgement_required = data.acknowledgement_required
  form.banner_enabled = data.banner_enabled
  form.banner_priority = data.banner_priority ?? 0
  form.banner_scroll = data.banner_scroll
  form.banner_duration_seconds = data.banner_duration_seconds ?? 8
  form.dismissible = data.dismissible
  form.publish_at = toLocal(data.publish_at)
  form.expires_at = toLocal(data.expires_at)
  form.banner_starts_at = toLocal(data.banner_starts_at)
  form.banner_ends_at = toLocal(data.banner_ends_at)
  form.links = data.links ?? []
  form.document_ids = (data.attachments ?? []).map((item: { document_id: number }) => item.document_id)
  const includes = (data.audiences ?? []).filter((item: any) => item.mode === 'include')
  const first = includes[0]
  form.target = first?.target_type ?? 'all'
  form.structure_ids = includes.map((item: any) => item.structure_id).filter(Boolean)
  form.user_ids = includes.map((item: any) => item.user_id).filter(Boolean)
  form.role_name = first?.role_name ?? ''
  form.include_descendants = Boolean(first?.include_descendants)
  form.excluded_user_ids = (data.audiences ?? []).filter((item: any) => item.mode === 'exclude').map((item: any) => item.user_id)
}

function toLocal(value?: string | null) {
  if (!value)
    return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return ''
  const pad = (n: number) => String(n).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

async function runPreview() {
  error.value = ''
  try {
    preview.value = await $api('/announcements/preview', { method: 'POST', body: { audiences: audiences() } })
  }
  catch (err: any) {
    error.value = err?.data?.message ?? 'Impossible de prévisualiser l\'audience.'
  }
}

async function save(thenPublish = false) {
  saving.value = true
  error.value = ''
  try {
    const body = payload()
    const saved = props.announcementId
      ? await $api<any>(`/announcements/${props.announcementId}`, { method: 'PUT', body })
      : await $api<any>('/announcements', { method: 'POST', body })
    if (thenPublish)
      await $api(`/announcements/${saved.id}/publish`, { method: 'POST' })
    announcementStore.invalidate()
    emit('saved', saved.id)
    router.push({ name: 'annonces-gestion-id', params: { id: saved.id } })
  }
  catch (err: any) {
    error.value = err?.data?.message ?? 'Enregistrement impossible.'
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadMeta()
  await loadExisting()
})
</script>

<template>
  <VAlert
    v-if="error"
    type="error"
    variant="tonal"
    class="mb-4"
  >
    {{ error }}
  </VAlert>

  <VRow>
    <VCol cols="12" md="8">
      <VCard class="mb-4">
        <VCardText class="d-flex flex-column gap-4">
          <AppTextField v-model="form.title" label="Titre" />
          <AppTextField v-model="form.summary" label="Résumé" />
          <div>
            <div class="text-body-2 mb-1">Contenu</div>
            <TiptapEditor v-model="form.body" placeholder="Rédigez l'annonce" />
          </div>
        </VCardText>
      </VCard>
      <VCard>
        <VCardTitle>Pièces et liens internes</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <AppTextField
            v-model="form.document_input"
            label="Identifiant GED"
            hint="Le document reste dans la GED. Saisissez son identifiant puis ajoutez-le."
            persistent-hint
            @keyup.enter="form.document_ids.push(Number(form.document_input)); form.document_input = ''"
          />
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="id in form.document_ids"
              :key="id"
              closable
              @click:close="form.document_ids = form.document_ids.filter(item => item !== id)"
            >
              Document {{ id }}
            </VChip>
          </div>
          <VBtn
            variant="tonal"
            size="small"
            @click="form.links.push({ label: '', url: '/' })"
          >
            Ajouter un lien interne
          </VBtn>
          <div
            v-for="(link, index) in form.links"
            :key="index"
            class="d-flex gap-2"
          >
            <AppTextField v-model="link.label" label="Libellé" />
            <AppTextField v-model="link.url" label="Chemin" placeholder="/ged" />
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <VCol cols="12" md="4">
      <VCard class="mb-4">
        <VCardText class="d-flex flex-column gap-3">
          <AppSelect
            v-model="form.category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Catégorie"
          />
          <AppSelect
            v-model="form.importance_id"
            :items="importances"
            item-title="name"
            item-value="id"
            label="Importance"
          />
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure émettrice"
          />
          <AppSelect
            v-model="form.confidentiality"
            :items="confidentialities"
            label="Confidentialité"
          />
          <AppTextField v-model="form.publish_at" type="datetime-local" label="Publication (Bamako)" />
          <AppTextField v-model="form.expires_at" type="datetime-local" label="Expiration" />
          <VSwitch v-model="form.acknowledgement_required" label="Lecture obligatoire" />
        </VCardText>
      </VCard>
      <VCard class="mb-4">
        <VCardTitle>Destinataires</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <AppSelect
            v-model="form.target"
            :items="[
              { title: 'Tous les agents', value: 'all' },
              { title: 'Structures', value: 'structure' },
              { title: 'Fonction', value: 'role' },
              { title: 'Agents désignés', value: 'user' },
            ]"
            label="Cible"
          />
          <AppSelect
            v-if="form.target === 'structure' || form.target === 'role'"
            v-model="form.structure_ids"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structures"
            multiple
            chips
          />
          <VSwitch
            v-if="form.target === 'structure' || form.target === 'role'"
            v-model="form.include_descendants"
            label="Inclure les structures rattachées"
          />
          <AppSelect
            v-if="form.target === 'role'"
            v-model="form.role_name"
            :items="roles"
            label="Fonction"
          />
          <AppSelect
            v-if="form.target === 'user'"
            v-model="form.user_ids"
            :items="users"
            item-title="name"
            item-value="id"
            label="Agents"
            multiple
            chips
          />
          <AppSelect
            v-model="form.excluded_user_ids"
            :items="users"
            item-title="name"
            item-value="id"
            label="Exclure des agents"
            multiple
            chips
            clearable
          />
          <VBtn variant="tonal" @click="runPreview">
            Prévisualiser l'audience
          </VBtn>
          <div v-if="preview">
            {{ preview.count }} destinataire(s)
            <div class="text-caption">
              {{ preview.sample.map(person => person.name).join(', ') }}
            </div>
          </div>
        </VCardText>
      </VCard>
      <VCard class="mb-4">
        <VCardTitle>Bandeau</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VSwitch v-model="form.banner_enabled" label="Afficher dans le bandeau" />
          <AppTextField v-model.number="form.banner_priority" type="number" label="Priorité" />
          <AppTextField v-model="form.banner_starts_at" type="datetime-local" label="Début d'affichage" />
          <AppTextField v-model="form.banner_ends_at" type="datetime-local" label="Fin d'affichage" />
          <VSwitch v-model="form.banner_scroll" label="Défilement" />
          <AppTextField v-model.number="form.banner_duration_seconds" type="number" label="Durée (secondes)" />
          <VSwitch v-model="form.dismissible" label="Fermeture autorisée" />
        </VCardText>
      </VCard>
      <div class="d-flex flex-wrap gap-2">
        <VBtn :loading="saving" @click="save(false)">
          Enregistrer
        </VBtn>
        <VBtn
          v-if="$can('validate', 'Announcement')"
          variant="tonal"
          :loading="saving"
          @click="save(true)"
        >
          Enregistrer et publier
        </VBtn>
      </div>
    </VCol>
  </VRow>
</template>
