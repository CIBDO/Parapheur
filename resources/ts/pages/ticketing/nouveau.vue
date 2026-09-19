<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import { formatTicketNumber, listItems } from '@/utils/ticketingUi'

definePage({
  meta: {
    layout: 'default',
    action: 'create',
    subject: 'Ticketing',
  },
})

const route = useRoute()
const router = useRouter()
const {
  fetchServiceCatalog,
  fetchServiceForm,
  fetchMeta,
  createTicket,
  duplicateCheck,
  uploadAttachment,
  loading,
} = useTicketing()

const step = ref(1)
const catalogs = ref<any[]>([])
const selectedItem = ref<any>(null)
const formFields = ref<any[]>([])
const customValues = ref<Record<string, any>>({})
const impacts = ref<any[]>([])
const urgencies = ref<any[]>([])
const applications = ref<any[]>([])
const assets = ref<any[]>([])
const duplicates = ref<any[]>([])
const aiSuggestions = ref<any>(null)
const aiLoading = ref(false)
const errorMsg = ref('')
const submitLoading = ref(false)
const file = ref<File | null>(null)

const form = ref({
  title: '',
  description: '',
  impact_id: null as number | null,
  urgency_id: null as number | null,
  application_id: null as number | null,
  asset_id: null as number | null,
})

const flatItems = computed(() => {
  const items: any[] = []
  for (const cat of catalogs.value) {
    for (const item of cat.items || []) {
      items.push({
        ...item,
        catalog_name: cat.name,
        catalog_id: cat.id,
      })
    }
  }

  return items
})

const impactItems = computed(() =>
  impacts.value.map(i => ({ value: i.id, title: i.name })),
)

const urgencyItems = computed(() =>
  urgencies.value.map(u => ({ value: u.id, title: u.name })),
)

const applicationItems = computed(() =>
  applications.value.map(a => ({ value: a.id, title: `${a.code} — ${a.name}` })),
)

const assetItems = computed(() =>
  assets.value.map(a => ({
    value: a.id,
    title: a.inventory_number ? `${a.name} (${a.inventory_number})` : a.name,
  })),
)

onMounted(async () => {
  try {
    const [catalog, meta, apps, ast] = await Promise.all([
      fetchServiceCatalog(),
      fetchMeta(),
      $api('/ticketing/applications', { query: { per_page: 100, is_active: 1 } }).catch(() => null),
      $api('/ticketing/assets', { query: { per_page: 100 } }).catch(() => null),
    ])
    catalogs.value = listItems(catalog).length ? listItems(catalog) : (Array.isArray(catalog) ? catalog : [])
    impacts.value = meta?.impacts || []
    urgencies.value = meta?.urgencies || []
    applications.value = listItems(apps)
    assets.value = listItems(ast)

    const preselectId = Number(route.query.service_item_id)
    if (preselectId) {
      const item = flatItems.value.find(i => i.id === preselectId)
      if (item)
        await selectService(item)
    }
  }
  catch (e) {
    console.error(e)
  }
})

async function selectService(item: any) {
  selectedItem.value = item
  customValues.value = {}
  errorMsg.value = ''
  try {
    const response = await fetchServiceForm(item.id)
    formFields.value = response?.fields || response?.item?.fields || []
    form.value.title = item.name || ''
    step.value = 2
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger le formulaire'
  }
}

watch(() => form.value.title, async (title) => {
  if (!title || title.length < 5 || step.value !== 2)
    return
  try {
    const res = await duplicateCheck({ title })
    duplicates.value = res?.duplicates || []
  }
  catch {
    duplicates.value = []
  }
})

async function askAiSuggestions() {
  if (!form.value.title.trim())
    return
  aiLoading.value = true
  try {
    const res = await $api('/ticketing/ai/suggest', {
      method: 'POST',
      body: {
        title: form.value.title,
        description: form.value.description || null,
      },
    })
    aiSuggestions.value = res?.suggestions || res
  }
  catch {
    aiSuggestions.value = null
  }
  finally {
    aiLoading.value = false
  }
}

function fieldOptions(field: any) {
  const opts = field.options || field.config?.options || []
  if (Array.isArray(opts)) {
    return opts.map((o: any) => {
      if (typeof o === 'string')
        return { value: o, title: o }

      return { value: o.value ?? o.code ?? o.id, title: o.label ?? o.name ?? o.title ?? String(o.value) }
    })
  }

  return []
}

async function submit() {
  errorMsg.value = ''
  if (!form.value.title.trim()) {
    errorMsg.value = 'Le titre est obligatoire.'

    return
  }
  submitLoading.value = true
  try {
    const payload: Record<string, any> = {
      title: form.value.title,
      description: form.value.description || null,
      service_item_id: selectedItem.value?.id,
      impact_id: form.value.impact_id,
      urgency_id: form.value.urgency_id,
      application_id: form.value.application_id,
      asset_id: form.value.asset_id,
      custom_fields: customValues.value,
      ticket_type_id: selectedItem.value?.ticket_type_id || selectedItem.value?.ticket_type?.id,
      ticket_category_id: selectedItem.value?.ticket_category_id || selectedItem.value?.ticket_category?.id,
      support_team_id: selectedItem.value?.support_team_id || selectedItem.value?.support_team?.id,
    }

    const created = await createTicket(payload)

    const uploadFile = Array.isArray(file.value) ? file.value[0] : file.value
    if (uploadFile && created?.id) {
      try {
        await uploadAttachment(created.id, uploadFile)
      }
      catch (e) {
        console.error(e)
      }
    }

    step.value = 3
    router.push({ name: 'ticketing-id', params: { id: created.id } })
  }
  catch (error: any) {
    const errors = error?.data?.errors
    if (errors && typeof errors === 'object')
      errorMsg.value = Object.values(errors).flat().join(' ')
    else
      errorMsg.value = error?.data?.message || error.message || 'Erreur lors de la création'
  }
  finally {
    submitLoading.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Nouveau ticket"
      subtitle="Assistant de création en 3 étapes"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          :to="{ name: 'ticketing' }"
        >
          Retour
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VCard class="mb-4">
      <VCardText>
        <VStepper
          :model-value="step"
          alt-labels
          :items="['Service', 'Détails', 'Confirmation']"
        />
      </VCardText>
    </VCard>

    <VAlert
      v-if="errorMsg"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="errorMsg = ''"
    >
      {{ errorMsg }}
    </VAlert>

    <!-- Étape 1 : catalogue -->
    <VCard v-if="step === 1">
      <VCardItem>
        <VCardTitle>Choisir un service</VCardTitle>
        <VCardSubtitle>Sélectionnez l'offre du catalogue concernée</VCardSubtitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <div
          v-if="!flatItems.length"
          class="text-center py-8 text-medium-emphasis"
        >
          Aucun service disponible dans le catalogue.
        </div>
        <VRow v-else dense>
          <VCol
            v-for="item in flatItems"
            :key="item.id"
            cols="12"
            md="6"
            lg="4"
          >
            <VCard
              variant="outlined"
              class="h-100 cursor-pointer"
              @click="selectService(item)"
            >
              <VCardText>
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ item.catalog_name }}
                </div>
                <div class="text-body-1 font-weight-medium">
                  {{ item.name }}
                </div>
                <div
                  v-if="item.description"
                  class="text-caption mt-2"
                >
                  {{ item.description }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Étape 2 : formulaire -->
    <VCard v-else-if="step === 2">
      <VCardItem>
        <VCardTitle>{{ selectedItem?.name }}</VCardTitle>
        <VCardSubtitle>Renseignez les informations de la demande</VCardSubtitle>
        <template #append>
          <VBtn
            variant="text"
            @click="step = 1"
          >
            Changer de service
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.title"
              label="Titre *"
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              label="Description"
              rows="4"
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12">
            <div class="d-flex flex-wrap gap-2 align-center mb-2">
              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-sparkles"
                :loading="aiLoading"
                :disabled="!form.title.trim()"
                @click="askAiSuggestions"
              >
                Suggestions assistées
              </VBtn>
              <span class="text-caption text-medium-emphasis">
                Proposition uniquement — validation humaine requise
              </span>
            </div>
            <VAlert
              v-if="aiSuggestions"
              type="info"
              variant="tonal"
              class="mb-2"
            >
              <div class="text-body-2 mb-1">
                <strong>Catégorie :</strong> {{ aiSuggestions.category?.name || '—' }}
              </div>
              <div class="text-body-2 mb-1">
                <strong>Priorité suggérée :</strong> {{ aiSuggestions.priority?.name || '—' }}
              </div>
              <div class="text-body-2 mb-1">
                <strong>Équipe :</strong> {{ aiSuggestions.team?.name || '—' }}
              </div>
              <div
                v-if="aiSuggestions.knowledge_articles?.length"
                class="text-body-2"
              >
                <strong>Articles :</strong>
                {{ aiSuggestions.knowledge_articles.map((a: any) => a.title).join(', ') }}
              </div>
            </VAlert>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.impact_id"
              :items="impactItems"
              label="Impact"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.urgency_id"
              :items="urgencyItems"
              label="Urgence"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.application_id"
              :items="applicationItems"
              label="Application concernée"
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.asset_id"
              :items="assetItems"
              label="Actif concerné"
              clearable
              hide-details="auto"
            />
          </VCol>

          <VCol
            v-for="field in formFields"
            :key="field.id || field.code"
            cols="12"
            :md="field.field_type === 'textarea' ? 12 : 6"
          >
            <AppTextarea
              v-if="field.field_type === 'textarea'"
              v-model="customValues[field.code]"
              :label="field.label + (field.is_required ? ' *' : '')"
              rows="3"
              hide-details="auto"
            />
            <AppSelect
              v-else-if="['select', 'radio'].includes(field.field_type)"
              v-model="customValues[field.code]"
              :items="fieldOptions(field)"
              :label="field.label + (field.is_required ? ' *' : '')"
              hide-details="auto"
            />
            <VCheckbox
              v-else-if="['boolean', 'checkbox'].includes(field.field_type)"
              v-model="customValues[field.code]"
              :label="field.label"
              hide-details
            />
            <AppTextField
              v-else
              v-model="customValues[field.code]"
              :type="['number', 'integer'].includes(field.field_type) ? 'number' : 'text'"
              :label="field.label + (field.is_required ? ' *' : '')"
              hide-details="auto"
            />
          </VCol>

          <VCol cols="12">
            <VFileInput
              v-model="file"
              label="Pièce jointe (optionnel)"
              prepend-icon="tabler-paperclip"
              show-size
              hide-details="auto"
            />
          </VCol>
        </VRow>

        <VAlert
          v-if="duplicates.length"
          type="warning"
          variant="tonal"
          class="mt-4"
        >
          <div class="font-weight-medium mb-2">
            Tickets similaires détectés
          </div>
          <ul class="mb-0">
            <li
              v-for="d in duplicates"
              :key="d.id"
            >
              <RouterLink :to="{ name: 'ticketing-id', params: { id: d.id } }">
                {{ formatTicketNumber(d) }} — {{ d.title }}
              </RouterLink>
            </li>
          </ul>
        </VAlert>

        <div class="d-flex justify-end gap-2 mt-6">
          <VBtn
            variant="tonal"
            @click="step = 1"
          >
            Précédent
          </VBtn>
          <VBtn
            color="primary"
            :loading="submitLoading || loading"
            @click="submit"
          >
            Créer le ticket
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
