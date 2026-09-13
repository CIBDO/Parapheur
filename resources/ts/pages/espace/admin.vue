<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { formatBytes } from '@/utils/workspaceUi'

definePage({
  meta: {
    layout: 'default',
    action: 'manage',
    subject: 'WorkspaceAdmin',
  },
})

const loading = ref(true)
const saving = ref(false)
const policy = ref<any>({
  default_personal_quota_bytes: 10 * 1024 ** 3,
  default_shared_quota_bytes: 20 * 1024 ** 3,
  max_upload_bytes: 100 * 1024 ** 2,
  warn_threshold_percent: 80,
  block_on_exceed: true,
  trash_retention_days: 30,
  allowed_extensions: ['pdf', 'docx', 'xlsx', 'pptx', 'odt', 'txt', 'png', 'jpg'],
  denied_extensions: [],
})
const overrides = ref<any[]>([])
const overrideForm = ref({
  scope_type: 'user',
  scope_id: null as number | null,
  quota_bytes: 5 * 1024 ** 3,
  note: '',
})
const users = ref<any[]>([])
const message = ref('')
const errorMsg = ref('')

const personalQuotaGo = computed({
  get: () => Number(((policy.value.default_personal_quota_bytes || 0) / (1024 ** 3)).toFixed(2)),
  set: (v: number) => { policy.value.default_personal_quota_bytes = Math.round(Number(v) * (1024 ** 3)) },
})
const sharedQuotaGo = computed({
  get: () => Number(((policy.value.default_shared_quota_bytes || 0) / (1024 ** 3)).toFixed(2)),
  set: (v: number) => { policy.value.default_shared_quota_bytes = Math.round(Number(v) * (1024 ** 3)) },
})
const maxUploadMo = computed({
  get: () => Number(((policy.value.max_upload_bytes || 0) / (1024 ** 2)).toFixed(0)),
  set: (v: number) => { policy.value.max_upload_bytes = Math.round(Number(v) * (1024 ** 2)) },
})
const allowedExtStr = computed({
  get: () => (policy.value.allowed_extensions || []).join(', '),
  set: (v: string) => {
    policy.value.allowed_extensions = v.split(',').map(s => s.trim().toLowerCase()).filter(Boolean)
  },
})

async function load() {
  loading.value = true
  try {
    const [p, o, u] = await Promise.all([
      $api('/workspace/admin/storage-policy'),
      $api('/workspace/admin/quota-overrides'),
      $api('/meta/users'),
    ])
    policy.value = { ...policy.value, ...(p.data || p) }
    overrides.value = o.data || o.items || o || []
    users.value = u.data || u || []
  }
  finally {
    loading.value = false
  }
}

async function savePolicy() {
  saving.value = true
  message.value = ''
  errorMsg.value = ''
  try {
    await $api('/workspace/admin/storage-policy', {
      method: 'PUT',
      body: policy.value,
    })
    message.value = 'Politique enregistrée.'
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Erreur enregistrement'
  }
  finally {
    saving.value = false
  }
}

async function addOverride() {
  errorMsg.value = ''
  try {
    await $api('/workspace/admin/quota-overrides', {
      method: 'POST',
      body: overrideForm.value,
    })
    overrideForm.value.scope_id = null
    overrideForm.value.note = ''
    await load()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || 'Override impossible'
  }
}

async function removeOverride(id: number) {
  await $api(`/workspace/admin/quota-overrides/${id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Administration — Quotas d’espace"
      subtitle="Plafonds de stockage du module Mon espace documentaire"
      icon="tabler-database-cog"
    />

    <VAlert
      v-if="message"
      type="success"
      class="mb-4"
      closable
    >
      {{ message }}
    </VAlert>
    <VAlert
      v-if="errorMsg"
      type="error"
      class="mb-4"
      closable
    >
      {{ errorMsg }}
    </VAlert>

    <VCard
      class="parapheur-section-card mb-6"
      :loading="loading"
    >
      <VCardItem>
        <VCardTitle>Politique globale</VCardTitle>
      </VCardItem>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model.number="personalQuotaGo"
              label="Quota espace personnel (Go)"
              type="number"
              min="0.1"
              step="0.5"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model.number="sharedQuotaGo"
              label="Quota espace collaboratif (Go)"
              type="number"
              min="0.1"
              step="0.5"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model.number="maxUploadMo"
              label="Taille max fichier (Mo)"
              type="number"
              min="1"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model.number="policy.warn_threshold_percent"
              label="Seuil d’alerte (%)"
              type="number"
              min="50"
              max="100"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model.number="policy.trash_retention_days"
              label="Rétention corbeille (jours)"
              type="number"
              min="1"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSwitch
              v-model="policy.block_on_exceed"
              label="Bloquer les uploads si quota dépassé"
              color="primary"
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model="allowedExtStr"
              label="Extensions autorisées (séparées par des virgules)"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          :loading="saving"
          @click="savePolicy"
        >
          Enregistrer la politique
        </VBtn>
      </VCardActions>
    </VCard>

    <VCard class="parapheur-section-card">
      <VCardItem>
        <VCardTitle>Dérogations (overrides)</VCardTitle>
      </VCardItem>
      <VCardText>
        <VRow class="mb-4">
          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="overrideForm.scope_type"
              :items="[
                { title: 'Utilisateur', value: 'user' },
                { title: 'Workspace', value: 'workspace' },
              ]"
              label="Portée"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-if="overrideForm.scope_type === 'user'"
              v-model="overrideForm.scope_id"
              :items="users.map((u: any) => ({ title: u.name || u.email, value: u.id }))"
              label="Utilisateur"
            />
            <VTextField
              v-else
              v-model.number="overrideForm.scope_id"
              label="ID workspace"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              v-model.number="overrideForm.quota_bytes"
              label="Quota (octets)"
              type="number"
              :hint="formatBytes(overrideForm.quota_bytes)"
              persistent-hint
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
            class="d-flex align-center"
          >
            <VBtn
              color="primary"
              block
              @click="addOverride"
            >
              Ajouter
            </VBtn>
          </VCol>
        </VRow>

        <VTable v-if="overrides.length">
          <thead>
            <tr>
              <th>Portée</th>
              <th>ID</th>
              <th>Quota</th>
              <th>Note</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="o in overrides"
              :key="o.id"
            >
              <td>{{ o.scope_type }}</td>
              <td>{{ o.scope_id }}</td>
              <td>{{ formatBytes(o.quota_bytes) }}</td>
              <td>{{ o.note || '—' }}</td>
              <td>
                <VBtn
                  size="small"
                  variant="text"
                  color="error"
                  @click="removeOverride(o.id)"
                >
                  Supprimer
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
        <div
          v-else
          class="text-medium-emphasis"
        >
          Aucune dérogation.
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
