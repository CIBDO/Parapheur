<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { $api } from '@/utils/api'

definePage({
  meta: { layout: 'default', action: 'manage', subject: 'CourrierAdmin' },
})

const { adminList, adminStore, adminUpdate, adminToggle, adminDelete } = useCorrespondence()

const activeTab = ref('channels')
const loading = ref(true)

// Data
const channels = ref<any[]>([])
const categories = ref<any[]>([])
const qualifications = ref<any[]>([])
const actions = ref<any[]>([])
const correspondents = ref<any[]>([])

// Dialog
const showDialog = ref(false)
const editingItem = ref<any>(null)
const editForm = ref({ name: '', code: '', description: '', is_active: true })

// Correspondents
const showCorrespondentDialog = ref(false)
const correspondentForm = ref({
  name: '',
  organization: '',
  type: 'personne',
  address: '',
  city: '',
  postal_code: '',
  country: 'France',
  email: '',
  phone: '',
})

onMounted(async () => {
  await loadAll()
})

async function loadAll() {
  loading.value = true
  try {
    const [ch, cat, qual, act, corr] = await Promise.all([
      adminList('channels'),
      adminList('categories'),
      adminList('qualifications'),
      adminList('actions'),
      $api('/mail/correspondents', { query: { per_page: 200 } }),
    ])
    channels.value = ch?.data || ch || []
    categories.value = cat?.data || cat || []
    qualifications.value = qual?.data || qual || []
    actions.value = act?.data || act || []
    correspondents.value = corr?.data || []
  } finally {
    loading.value = false
  }
}

function openCreate(type: 'channels' | 'categories' | 'qualifications' | 'actions') {
  editingItem.value = null
  editForm.value = { name: '', code: '', description: '', is_active: true }
  activeTab.value = type
  showDialog.value = true
}

function openEdit(type: 'channels' | 'categories' | 'qualifications' | 'actions', item: any) {
  editingItem.value = item
  editForm.value = { ...item }
  activeTab.value = type
  showDialog.value = true
}

async function saveItem() {
  const type = activeTab.value as 'channels' | 'categories' | 'qualifications' | 'actions'
  try {
    if (editingItem.value) {
      await adminUpdate(type, editingItem.value.id, editForm.value)
    } else {
      await adminStore(type, editForm.value)
    }
    showDialog.value = false
    await loadAll()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de sauvegarder'))
  }
}

async function toggleActive(type: 'channels' | 'categories' | 'qualifications' | 'actions', id: number) {
  try {
    await adminToggle(type, id)
    await loadAll()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de basculer'))
  }
}

async function deleteItem(type: 'channels' | 'categories' | 'qualifications' | 'actions', id: number) {
  if (!confirm('Supprimer cet élément ?')) return
  try {
    await adminDelete(type, id)
    await loadAll()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de supprimer'))
  }
}

// Correspondents
function openCorrespondentCreate() {
  correspondentForm.value = {
    name: '',
    organization: '',
    type: 'personne',
    address: '',
    city: '',
    postal_code: '',
    country: 'France',
    email: '',
    phone: '',
  }
  showCorrespondentDialog.value = true
}

async function saveCorrespondent() {
  try {
    await $api('/mail/correspondents', {
      method: 'POST',
      body: correspondentForm.value,
    })
    showCorrespondentDialog.value = false
    await loadAll()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de créer le correspondant'))
  }
}

async function deleteCorrespondent(id: number) {
  if (!confirm('Supprimer ce correspondant ?')) return
  try {
    await $api(`/mail/correspondents/${id}`, { method: 'DELETE' })
    await loadAll()
  } catch (error: any) {
    alert('Erreur : ' + (error.message || 'Impossible de supprimer'))
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Administration Courrier"
      subtitle="Référentiels et annuaire"
    />

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <VCard v-else>
      <VTabs v-model="activeTab">
        <VTab value="channels">
          Canaux
        </VTab>
        <VTab value="categories">
          Catégories
        </VTab>
        <VTab value="qualifications">
          Qualifications
        </VTab>
        <VTab value="actions">
          Actions
        </VTab>
        <VTab value="correspondents">
          Correspondants
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- Canaux -->
        <VWindowItem value="channels">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6">
                Canaux de réception
              </h3>
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openCreate('channels')"
              >
                Ajouter
              </VBtn>
            </div>
            <VDataTable
              :headers="[
                { title: 'Nom', key: 'name' },
                { title: 'Code', key: 'code' },
                { title: 'Actif', key: 'is_active' },
                { title: 'Actions', key: 'actions', sortable: false },
              ]"
              :items="channels"
              density="compact"
            >
              <template #item.is_active="{ item }">
                <VChip
                  size="small"
                  :color="item.is_active ? 'success' : 'default'"
                  variant="tonal"
                >
                  {{ item.is_active ? 'Actif' : 'Inactif' }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <VBtn
                  size="x-small"
                  icon="tabler-edit"
                  variant="text"
                  @click="openEdit('channels', item)"
                />
                <VBtn
                  size="x-small"
                  :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
                  variant="text"
                  @click="toggleActive('channels', item.id)"
                />
                <VBtn
                  size="x-small"
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="deleteItem('channels', item.id)"
                />
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>

        <!-- Catégories -->
        <VWindowItem value="categories">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6">
                Catégories de courrier
              </h3>
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openCreate('categories')"
              >
                Ajouter
              </VBtn>
            </div>
            <VDataTable
              :headers="[
                { title: 'Nom', key: 'name' },
                { title: 'Code', key: 'code' },
                { title: 'Actif', key: 'is_active' },
                { title: 'Actions', key: 'actions', sortable: false },
              ]"
              :items="categories"
              density="compact"
            >
              <template #item.is_active="{ item }">
                <VChip
                  size="small"
                  :color="item.is_active ? 'success' : 'default'"
                  variant="tonal"
                >
                  {{ item.is_active ? 'Actif' : 'Inactif' }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <VBtn
                  size="x-small"
                  icon="tabler-edit"
                  variant="text"
                  @click="openEdit('categories', item)"
                />
                <VBtn
                  size="x-small"
                  :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
                  variant="text"
                  @click="toggleActive('categories', item.id)"
                />
                <VBtn
                  size="x-small"
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="deleteItem('categories', item.id)"
                />
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>

        <!-- Qualifications -->
        <VWindowItem value="qualifications">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6">
                Qualifications
              </h3>
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openCreate('qualifications')"
              >
                Ajouter
              </VBtn>
            </div>
            <VDataTable
              :headers="[
                { title: 'Nom', key: 'name' },
                { title: 'Code', key: 'code' },
                { title: 'Actif', key: 'is_active' },
                { title: 'Actions', key: 'actions', sortable: false },
              ]"
              :items="qualifications"
              density="compact"
            >
              <template #item.is_active="{ item }">
                <VChip
                  size="small"
                  :color="item.is_active ? 'success' : 'default'"
                  variant="tonal"
                >
                  {{ item.is_active ? 'Actif' : 'Inactif' }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <VBtn
                  size="x-small"
                  icon="tabler-edit"
                  variant="text"
                  @click="openEdit('qualifications', item)"
                />
                <VBtn
                  size="x-small"
                  :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
                  variant="text"
                  @click="toggleActive('qualifications', item.id)"
                />
                <VBtn
                  size="x-small"
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="deleteItem('qualifications', item.id)"
                />
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>

        <!-- Actions -->
        <VWindowItem value="actions">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6">
                Actions d'imputation
              </h3>
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openCreate('actions')"
              >
                Ajouter
              </VBtn>
            </div>
            <VDataTable
              :headers="[
                { title: 'Nom', key: 'name' },
                { title: 'Code', key: 'code' },
                { title: 'Actif', key: 'is_active' },
                { title: 'Actions', key: 'actions', sortable: false },
              ]"
              :items="actions"
              density="compact"
            >
              <template #item.is_active="{ item }">
                <VChip
                  size="small"
                  :color="item.is_active ? 'success' : 'default'"
                  variant="tonal"
                >
                  {{ item.is_active ? 'Actif' : 'Inactif' }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <VBtn
                  size="x-small"
                  icon="tabler-edit"
                  variant="text"
                  @click="openEdit('actions', item)"
                />
                <VBtn
                  size="x-small"
                  :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
                  variant="text"
                  @click="toggleActive('actions', item.id)"
                />
                <VBtn
                  size="x-small"
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="deleteItem('actions', item.id)"
                />
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>

        <!-- Correspondants -->
        <VWindowItem value="correspondents">
          <VCardText>
            <div class="d-flex justify-space-between align-center mb-4">
              <h3 class="text-h6">
                Annuaire des correspondants
              </h3>
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                @click="openCorrespondentCreate"
              >
                Ajouter
              </VBtn>
            </div>
            <VDataTable
              :headers="[
                { title: 'Nom', key: 'name' },
                { title: 'Organisation', key: 'organization' },
                { title: 'Type', key: 'type' },
                { title: 'Email', key: 'email' },
                { title: 'Ville', key: 'city' },
                { title: 'Actions', key: 'actions', sortable: false },
              ]"
              :items="correspondents"
              density="compact"
            >
              <template #item.actions="{ item }">
                <VBtn
                  size="x-small"
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="deleteCorrespondent(item.id)"
                />
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Dialog Édition référentiel -->
    <VDialog v-model="showDialog" max-width="600">
      <VCard>
        <VCardTitle>
          {{ editingItem ? 'Modifier' : 'Créer' }}
        </VCardTitle>
        <VCardText>
          <VTextField
            v-model="editForm.name"
            label="Nom"
            class="mb-4"
          />
          <VTextField
            v-model="editForm.code"
            label="Code"
            class="mb-4"
          />
          <VTextarea
            v-model="editForm.description"
            label="Description"
            rows="3"
            class="mb-4"
          />
          <VCheckbox
            v-model="editForm.is_active"
            label="Actif"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="saveItem">
            Enregistrer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Correspondant -->
    <VDialog v-model="showCorrespondentDialog" max-width="700">
      <VCard>
        <VCardTitle>Créer un correspondant</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="correspondentForm.name"
                label="Nom"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="correspondentForm.organization"
                label="Organisation"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="correspondentForm.type"
                :items="[
                  { title: 'Personne', value: 'personne' },
                  { title: 'Organisation', value: 'organisation' },
                ]"
                label="Type"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="correspondentForm.email"
                label="Email"
                type="email"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="correspondentForm.phone"
                label="Téléphone"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="correspondentForm.city"
                label="Ville"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="correspondentForm.address"
                label="Adresse"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showCorrespondentDialog = false">
            Annuler
          </VBtn>
          <VBtn color="primary" @click="saveCorrespondent">
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
