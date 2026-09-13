<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatDateFr } from '@/utils/parapheurUi'

definePage({
  meta: {
    layout: 'default',
    action: 'read',
    subject: 'Library',
  },
})

const loading = ref(true)
const items = ref<any[]>([])
const collections = ref<any[]>([])
const types = ref<any[]>([])
const q = ref('')
const typeId = ref<number | null>(null)
const scope = ref('all')
const dialog = ref(false)
const detailOpen = ref(false)
const selected = ref<any>(null)
const noteBody = ref('')
const form = ref({
  title: '',
  reference_type_id: null as number | null,
  institutional_author: '',
  publication_year: null as number | null,
  tags: '',
  abstract: '',
})
const errorMsg = ref('')
const ability = useAbility()

async function loadMeta() {
  const [t, c] = await Promise.all([
    $api('/library/reference-types'),
    $api('/library/collections'),
  ])
  types.value = t.data || t || []
  collections.value = c.data || c || []
}

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('scope', scope.value)
    if (q.value)
      params.set('q', q.value)
    if (typeId.value)
      params.set('reference_type_id', String(typeId.value))
    const res = await $api(`/library/references?${params}`)
    items.value = res.data || []
  }
  finally {
    loading.value = false
  }
}

async function create() {
  errorMsg.value = ''
  try {
    await $api('/library/references', {
      method: 'POST',
      body: {
        ...form.value,
        tags: form.value.tags ? form.value.tags.split(',').map((s: string) => s.trim()).filter(Boolean) : [],
      },
    })
    dialog.value = false
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Enregistrement impossible'
  }
}

async function openDetail(ref: any) {
  selected.value = await $api(`/library/references/${ref.id}`)
  noteBody.value = selected.value.my_note?.body || ''
  detailOpen.value = true
}

async function propose() {
  if (!selected.value)
    return
  await $api(`/library/references/${selected.value.id}/propose`, { method: 'POST', body: {} })
  await openDetail(selected.value)
  await load()
}

async function moderate(approve: boolean) {
  if (!selected.value)
    return
  await $api(`/library/references/${selected.value.id}/moderate`, {
    method: 'POST',
    body: { approve },
  })
  await openDetail(selected.value)
  await load()
}

async function saveNote() {
  if (!selected.value)
    return
  await $api(`/library/references/${selected.value.id}/note`, {
    method: 'POST',
    body: { body: noteBody.value },
  })
}

onMounted(async () => {
  await loadMeta()
  await load()
})
watch(scope, load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Bibliothèque de références"
      subtitle="Personnelle et institutionnelle DGTCP"
      icon="tabler-books"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialog = true"
        >
          Ajouter une référence
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="parapheur-section-card mb-4">
      <VCardText>
        <VRow
          align="center"
          dense
        >
          <VCol
            cols="12"
            lg="auto"
          >
            <VBtnToggle
              v-model="scope"
              class="library-scope-toggle flex-wrap"
              mandatory
              density="comfortable"
              divided
              color="primary"
            >
              <VBtn
                value="all"
                class="text-none px-3"
              >
                Toutes
              </VBtn>
              <VBtn
                value="mine"
                class="text-none px-3"
              >
                Mes références
              </VBtn>
              <VBtn
                value="institutional"
                class="text-none px-3"
              >
                Institutionnelles
              </VBtn>
              <VBtn
                v-if="ability.can('manage', 'WorkspaceAdmin') || ability.can('manage', 'all')"
                value="pending"
                class="text-none px-3"
              >
                À valider
              </VBtn>
            </VBtnToggle>
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="4"
            lg
          >
            <AppTextField
              v-model="q"
              placeholder="Rechercher"
              prepend-inner-icon="tabler-search"
              hide-details
              density="compact"
              clearable
              @keyup.enter="load"
              @click:clear="load"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
            lg="3"
          >
            <AppSelect
              v-model="typeId"
              :items="types.map((t: any) => ({ title: t.name, value: t.id }))"
              label="Type"
              placeholder="Type"
              hide-details
              density="compact"
              clearable
              @update:model-value="load"
            />
          </VCol>

          <VCol
            cols="12"
            sm="auto"
            class="d-flex"
          >
            <VBtn
              color="success"
              variant="tonal"
              prepend-icon="tabler-filter"
              class="flex-grow-1"
              @click="load"
            >
              Filtrer
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="parapheur-section-card">
      <VList v-if="items.length">
        <VListItem
          v-for="ref in items"
          :key="ref.id"
          :title="ref.title"
          :subtitle="[ref.type?.name, ref.institutional_author, ref.publication_year, ref.publication_status].filter(Boolean).join(' · ')"
          prepend-icon="tabler-book"
          @click="openDetail(ref)"
        >
          <template #append>
            <VChip
              v-if="ref.publication_status === 'institutional'"
              size="small"
              color="primary"
              label
            >
              Institutionnelle
            </VChip>
            <VChip
              v-else-if="ref.publication_status === 'proposed'"
              size="small"
              color="warning"
              label
            >
              Proposée
            </VChip>
            <span class="text-caption text-medium-emphasis ms-2">
              {{ formatDateFr(ref.created_at) }}
            </span>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-medium-emphasis"
      >
        {{ loading ? 'Chargement…' : 'Aucune référence.' }}
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Nouvelle référence</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorMsg"
            type="error"
            class="mb-3"
          >
            {{ errorMsg }}
          </VAlert>
          <VTextField
            v-model="form.title"
            label="Titre *"
            class="mb-3"
          />
          <VSelect
            v-model="form.reference_type_id"
            :items="types.map((t: any) => ({ title: t.name, value: t.id }))"
            label="Type"
            class="mb-3"
          />
          <VTextField
            v-model="form.institutional_author"
            label="Auteur institutionnel"
            class="mb-3"
          />
          <VTextField
            v-model.number="form.publication_year"
            label="Année"
            type="number"
            class="mb-3"
          />
          <VTextarea
            v-model="form.abstract"
            label="Résumé"
            rows="2"
            class="mb-3"
          />
          <VTextField
            v-model="form.tags"
            label="Tags (séparés par des virgules)"
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
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="detailOpen"
      max-width="640"
    >
      <VCard v-if="selected">
        <VCardTitle>{{ selected.title }}</VCardTitle>
        <VCardText>
          <p class="text-body-2">
            {{ selected.abstract || 'Pas de résumé.' }}
          </p>
          <div class="text-caption text-medium-emphasis mb-4">
            {{ [selected.type?.name, selected.institutional_author, selected.publication_year, selected.organization].filter(Boolean).join(' · ') }}
          </div>
          <VTextarea
            v-model="noteBody"
            label="Notes personnelles (privées)"
            rows="3"
            class="mb-3"
          />
          <VBtn
            size="small"
            variant="tonal"
            class="me-2"
            @click="saveNote"
          >
            Enregistrer la note
          </VBtn>
          <VBtn
            v-if="selected.publication_status === 'personal'"
            size="small"
            color="primary"
            class="me-2"
            @click="propose"
          >
            Proposer à la bibliothèque institutionnelle
          </VBtn>
          <template v-if="selected.publication_status === 'proposed'">
            <VBtn
              size="small"
              color="success"
              class="me-2"
              @click="moderate(true)"
            >
              Approuver
            </VBtn>
            <VBtn
              size="small"
              color="error"
              @click="moderate(false)"
            >
              Rejeter
            </VBtn>
          </template>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="detailOpen = false"
          >
            Fermer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.library-scope-toggle :deep(.v-btn) {
  min-inline-size: max-content;
  white-space: nowrap;
}
</style>
