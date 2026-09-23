<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { $api } from '@/utils/api'
import { listItems } from '@/utils/listItems'
import { getTemplateKindLabel } from '@/utils/courrierUi'

definePage({
  meta: { layout: 'default', action: 'read', subject: 'DocumentTemplate' },
})

const templates = ref<any>(null)
const loading = ref(true)
const createOpen = ref(false)
const creating = ref(false)
const form = ref({
  code: '',
  name: '',
  kind: 'bordereau_transmission',
  description: '',
})
const file = ref<File | null>(null)

const items = computed(() => listItems(templates.value))

async function load() {
  loading.value = true
  try {
    templates.value = await $api('/mail/document-templates')
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function createTemplate() {
  creating.value = true
  try {
    const body = new FormData()
    body.append('code', form.value.code)
    body.append('name', form.value.name)
    body.append('kind', form.value.kind)
    if (form.value.description)
      body.append('description', form.value.description)
    if (file.value)
      body.append('template_file', file.value)

    await $api('/mail/document-templates', { method: 'POST', body })
    createOpen.value = false
    await load()
  }
  finally {
    creating.value = false
  }
}

async function publishLatest(item: any) {
  const versionId = item.versions?.[0]?.id || item.current_published_version_id
  if (!versionId && item.versions?.length) {
    // no-op
  }
  const latest = item.current_published_version_id
    ? null
    : (await $api(`/mail/document-templates/${item.id}`)).versions?.[0]

  const id = latest?.id
  if (!id)
    return

  await $api(`/mail/document-templates/${item.id}/publish`, {
    method: 'POST',
    body: { version_id: id },
  })
  await load()
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Modèles documentaires"
      subtitle="Templates DOCX versionnés"
    >
      <template #actions>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="createOpen = true"
        >
          Nouveau modèle
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard>
      <VDataTable
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Type', key: 'kind' },
          { title: 'Publié', key: 'published' },
          { title: 'Actif', key: 'is_active' },
          { title: 'Actions', key: 'actions' },
        ]"
        :items="items"
        :loading="loading"
      >
        <template #item.kind="{ item }">
          {{ getTemplateKindLabel(item.kind) }}
        </template>
        <template #item.published="{ item }">
          <VChip
            size="small"
            :color="item.current_published_version_id ? 'success' : 'default'"
            variant="tonal"
          >
            {{ item.current_published_version_id ? 'Oui' : 'Non' }}
          </VChip>
        </template>
        <template #item.is_active="{ item }">
          <VIcon
            :icon="item.is_active ? 'tabler-check' : 'tabler-x'"
            :color="item.is_active ? 'success' : 'error'"
          />
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="text"
            :disabled="!!item.current_published_version_id"
            @click="publishLatest(item)"
          >
            Publier
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="createOpen"
      max-width="560"
    >
      <VCard title="Nouveau modèle">
        <VCardText>
          <AppTextField
            v-model="form.code"
            class="mb-3"
            label="Code *"
          />
          <AppTextField
            v-model="form.name"
            class="mb-3"
            label="Nom *"
          />
          <AppSelect
            v-model="form.kind"
            class="mb-3"
            :items="[
              { value: 'bordereau_transmission', title: 'Bordereau de transmission' },
              { value: 'bordereau_envoi', title: 'Bordereau d\'envoi' },
              { value: 'fiche_circulation', title: 'Fiche circulation' },
              { value: 'accuse_reception', title: 'Accusé de réception' },
              { value: 'lettre', title: 'Lettre' },
              { value: 'note', title: 'Note' },
              { value: 'autre', title: 'Autre' },
            ]"
            label="Type"
          />
          <VFileInput
            v-model="file"
            class="mb-3"
            label="Fichier DOCX"
            accept=".doc,.docx"
          />
          <AppTextarea
            v-model="form.description"
            label="Description"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn @click="createOpen = false">
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="creating"
            @click="createTemplate"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
