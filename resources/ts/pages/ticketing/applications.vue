<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { $api } from '@/utils/api'
import { listItems } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'manage', subject: 'TicketingAdmin' },
})

const loading = ref(true)
const saving = ref(false)
const applications = ref<any[]>([])
const assets = ref<any[]>([])
const errorMsg = ref('')
const successMsg = ref('')
const tab = ref('apps')
const appDialog = ref(false)
const assetDialog = ref(false)
const appForm = ref({ code: '', name: '', description: '', is_active: true })
const assetForm = ref({ name: '', inventory_number: '', location: '', status: 'active' })

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [apps, ast] = await Promise.all([
      $api('/ticketing/applications'),
      $api('/ticketing/assets'),
    ])
    applications.value = listItems(apps).length ? listItems(apps) : (Array.isArray(apps) ? apps : [])
    assets.value = listItems(ast).length ? listItems(ast) : (Array.isArray(ast) ? ast : [])
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Chargement impossible'
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

async function saveApp() {
  saving.value = true
  try {
    await $api('/ticketing/applications', { method: 'POST', body: appForm.value })
    appDialog.value = false
    appForm.value = { code: '', name: '', description: '', is_active: true }
    successMsg.value = 'Application créée'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    saving.value = false
  }
}

async function saveAsset() {
  saving.value = true
  try {
    await $api('/ticketing/assets', { method: 'POST', body: assetForm.value })
    assetDialog.value = false
    assetForm.value = { name: '', inventory_number: '', location: '', status: 'active' }
    successMsg.value = 'Actif créé'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Erreur'
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Applications & actifs"
      subtitle="CMDB légère — référentiels liés aux tickets"
    >
      <template #actions>
        <VBtn
          v-if="tab === 'apps'"
          color="primary"
          prepend-icon="tabler-plus"
          @click="appDialog = true"
        >
          Application
        </VBtn>
        <VBtn
          v-else
          color="primary"
          prepend-icon="tabler-plus"
          @click="assetDialog = true"
        >
          Actif
        </VBtn>
      </template>
    </ParapheurPageHeader>

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
    <VAlert
      v-if="successMsg"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="successMsg = ''"
    >
      {{ successMsg }}
    </VAlert>

    <VTabs
      v-model="tab"
      class="mb-4"
    >
      <VTab value="apps">
        Applications
      </VTab>
      <VTab value="assets">
        Actifs
      </VTab>
    </VTabs>

    <VCard v-if="tab === 'apps'">
      <VDataTable
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Nom', key: 'name' },
          { title: 'Actif', key: 'is_active' },
        ]"
        :items="applications"
        :loading="loading"
      >
        <template #item.is_active="{ item }">
          {{ item.is_active ? 'Oui' : 'Non' }}
        </template>
      </VDataTable>
    </VCard>

    <VCard v-else>
      <VDataTable
        :headers="[
          { title: 'Nom', key: 'name' },
          { title: 'Inventaire', key: 'inventory_number' },
          { title: 'Localisation', key: 'location' },
          { title: 'Statut', key: 'status' },
        ]"
        :items="assets"
        :loading="loading"
      />
    </VCard>

    <VDialog
      v-model="appDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouvelle application</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="appForm.code"
            label="Code *"
            class="mb-3"
          />
          <AppTextField
            v-model="appForm.name"
            label="Nom *"
            class="mb-3"
          />
          <AppTextarea
            v-model="appForm.description"
            label="Description"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="appDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!appForm.code || !appForm.name"
            @click="saveApp"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="assetDialog"
      max-width="520"
    >
      <VCard>
        <VCardTitle>Nouvel actif</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="assetForm.name"
            label="Nom *"
            class="mb-3"
          />
          <AppTextField
            v-model="assetForm.inventory_number"
            label="N° inventaire"
            class="mb-3"
          />
          <AppTextField
            v-model="assetForm.location"
            label="Localisation"
            class="mb-3"
          />
          <AppSelect
            v-model="assetForm.status"
            :items="[
              { value: 'active', title: 'Actif' },
              { value: 'maintenance', title: 'Maintenance' },
              { value: 'retired', title: 'Réformé' },
            ]"
            label="Statut"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="assetDialog = false"
          >
            Annuler
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!assetForm.name"
            @click="saveAsset"
          >
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
