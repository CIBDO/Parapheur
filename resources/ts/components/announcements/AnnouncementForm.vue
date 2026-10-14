<script setup lang="ts">
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { useAnnouncementStore } from '@/stores/announcements'

const props = defineProps<{
  announcementId?: number
}>()

const router = useRouter()
const announcementStore = useAnnouncementStore()
const error = ref('')
const saving = ref(false)
const categories = ref<any[]>([])
const structures = ref<any[]>([])

const form = reactive({
  title: '',
  body: '<p></p>',
  category_id: null as number | null,
  target: 'all' as 'all' | 'structure',
  structure_id: null as number | null,
  expires_at: '',
  banner_enabled: false,
  acknowledgement_required: false,
})

function audiences() {
  if (form.target === 'structure' && form.structure_id)
    return [{ mode: 'include', target_type: 'structure', structure_id: form.structure_id, include_descendants: true }]

  return [{ mode: 'include', target_type: 'all' }]
}

function payload() {
  const category = categories.value.find(item => item.id === form.category_id)

  return {
    title: form.title.trim(),
    summary: null,
    body: form.body,
    category_id: form.category_id,
    importance_id: category?.default_importance_id ?? category?.default_importance?.id ?? null,
    confidentiality: 'general',
    acknowledgement_required: form.acknowledgement_required,
    banner_enabled: form.banner_enabled,
    expires_at: form.expires_at || null,
    audiences: audiences(),
    links: [],
    document_ids: [],
  }
}

async function loadMeta() {
  const [cats, structs] = await Promise.all([
    $api<any[]>('/announcement-categories'),
    $api<any[]>('/meta/structures'),
  ])
  categories.value = cats.filter(item => item.is_active !== false)
  structures.value = structs
  if (!form.category_id && categories.value.length)
    form.category_id = categories.value[0].id
}

async function loadExisting() {
  if (!props.announcementId)
    return
  const data = await $api<any>(`/announcements/${props.announcementId}`)
  form.title = data.title
  form.body = data.body || '<p></p>'
  form.category_id = data.category?.id ?? form.category_id
  form.banner_enabled = Boolean(data.banner_enabled)
  form.acknowledgement_required = Boolean(data.acknowledgement_required)
  form.expires_at = toLocal(data.expires_at)
  const audience = (data.audiences ?? []).find((item: any) => item.mode === 'include')
  if (audience?.target_type === 'structure' && audience.structure_id) {
    form.target = 'structure'
    form.structure_id = audience.structure_id
  }
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

function plainEnough() {
  return form.body.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0
}

async function publish() {
  error.value = ''
  if (!form.title.trim() || !plainEnough()) {
    error.value = 'Indiquez un titre et un message.'

    return
  }
  if (form.target === 'structure' && !form.structure_id) {
    error.value = 'Choisissez la structure destinataire.'

    return
  }

  saving.value = true
  try {
    const body = payload()
    const saved = props.announcementId
      ? await $api<any>(`/announcements/${props.announcementId}`, { method: 'PUT', body })
      : await $api<any>('/announcements', { method: 'POST', body })
    if (saved.status !== 'published')
      await $api(`/announcements/${saved.id}/publish`, { method: 'POST' })
    announcementStore.invalidate()
    router.push({ name: 'annonces-id', params: { id: saved.id } })
  }
  catch (err: any) {
    error.value = err?.data?.message ?? 'Publication impossible.'
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

  <VCard>
    <VCardText class="d-flex flex-column gap-4">
      <AppTextField
        v-model="form.title"
        label="Titre"
        placeholder="Ex. Fermeture des guichets vendredi"
      />
      <div>
        <div class="text-body-2 mb-1">
          Message
        </div>
        <TiptapEditor
          v-model="form.body"
          placeholder="Écrivez l'annonce telle que les agents doivent la lire"
        />
      </div>
      <VRow>
        <VCol cols="12" md="6">
          <AppSelect
            v-model="form.category_id"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Catégorie"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppSelect
            v-model="form.target"
            :items="[
              { title: 'Tous les agents', value: 'all' },
              { title: 'Une structure', value: 'structure' },
            ]"
            label="Destinataires"
          />
        </VCol>
        <VCol v-if="form.target === 'structure'" cols="12">
          <AppSelect
            v-model="form.structure_id"
            :items="structures"
            item-title="name"
            item-value="id"
            label="Structure"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppTextField
            v-model="form.expires_at"
            type="datetime-local"
            label="Visible jusqu'au"
            hint="Laissez vide pour la garder affichée"
            persistent-hint
          />
        </VCol>
      </VRow>
      <div class="d-flex flex-column">
        <VSwitch
          v-model="form.banner_enabled"
          label="Afficher aussi dans le bandeau en haut de l'écran"
          hide-details
        />
        <VSwitch
          v-model="form.acknowledgement_required"
          label="Demander une confirmation de lecture"
          hide-details
        />
      </div>
    </VCardText>
    <VCardActions class="px-4 pb-4">
      <VSpacer />
      <VBtn
        variant="text"
        :to="{ name: 'annonces' }"
      >
        Annuler
      </VBtn>
      <VBtn
        color="primary"
        :loading="saving"
        @click="publish"
      >
        Publier
      </VBtn>
    </VCardActions>
  </VCard>
</template>
