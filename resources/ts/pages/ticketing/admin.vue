<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { $api } from '@/utils/api'
import { useTicketing } from '@/composables/useTicketing'
import { listItems } from '@/utils/ticketingUi'

definePage({
  meta: { layout: 'default', action: 'manage', subject: 'TicketingAdmin' },
})

const { adminList, adminStore, fetchMeta } = useTicketing()

const activeTab = ref('types')
const loading = ref(true)
const saving = ref(false)
const types = ref<any[]>([])
const teams = ref<any[]>([])
const catalogs = ref<any[]>([])
const slaPolicies = ref<any[]>([])
const matrix = ref<any[]>([])
const priorities = ref<any[]>([])
const impacts = ref<any[]>([])
const urgencies = ref<any[]>([])
const calendars = ref<any[]>([])
const users = ref<any[]>([])
const showDialog = ref(false)
const memberDialog = ref(false)
const selectedTeam = ref<any>(null)
const errorMsg = ref('')
const successMsg = ref('')

const form = ref({
  code: '',
  name: '',
  description: '',
  is_active: true,
  priority_id: null as number | null,
  sla_calendar_id: null as number | null,
  response_minutes: 240,
  resolution_minutes: 960,
  warning_percent: 80,
  impact_id: null as number | null,
  urgency_id: null as number | null,
})

const memberForm = ref({
  user_id: null as number | null,
  level: 'N1',
  is_lead: false,
})

const canCreateOnTab = computed(() =>
  ['types', 'teams', 'catalogs', 'sla', 'matrix'].includes(activeTab.value),
)

onMounted(async () => {
  await loadAll()
})

watch(activeTab, () => {
  showDialog.value = false
})

async function loadAll() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [t, tm, c, sla, mx, meta, cal, usr] = await Promise.all([
      adminList('types'),
      adminList('teams'),
      adminList('catalogs'),
      adminList('sla-policies'),
      adminList('priority-matrix'),
      fetchMeta(),
      adminList('sla-calendars').catch(() => []),
      $api('/users', { query: { per_page: 100 } }).catch(() => null),
    ])
    types.value = listItems(t).length ? listItems(t) : (Array.isArray(t) ? t : [])
    teams.value = listItems(tm).length ? listItems(tm) : (Array.isArray(tm) ? tm : [])
    catalogs.value = listItems(c).length ? listItems(c) : (Array.isArray(c) ? c : [])
    slaPolicies.value = listItems(sla).length ? listItems(sla) : (Array.isArray(sla) ? sla : [])
    matrix.value = listItems(mx).length ? listItems(mx) : (Array.isArray(mx) ? mx : [])
    calendars.value = listItems(cal).length ? listItems(cal) : (Array.isArray(cal) ? cal : [])
    priorities.value = meta?.priorities || []
    impacts.value = meta?.impacts || []
    urgencies.value = meta?.urgencies || []
    users.value = listItems(usr).length ? listItems(usr) : (Array.isArray(usr?.data) ? usr.data : [])
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Impossible de charger l\'administration'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = {
    code: '',
    name: '',
    description: '',
    is_active: true,
    priority_id: priorities.value[0]?.id ?? null,
    sla_calendar_id: calendars.value[0]?.id ?? null,
    response_minutes: 240,
    resolution_minutes: 960,
    warning_percent: 80,
    impact_id: impacts.value[0]?.id ?? null,
    urgency_id: urgencies.value[0]?.id ?? null,
  }
  showDialog.value = true
}

function openMembers(team: any) {
  selectedTeam.value = team
  memberForm.value = { user_id: null, level: 'N1', is_lead: false }
  memberDialog.value = true
}

async function save() {
  errorMsg.value = ''
  saving.value = true
  try {
    if (activeTab.value === 'sla') {
      await adminStore('sla-policies', {
        code: form.value.code,
        name: form.value.name,
        priority_id: form.value.priority_id,
        sla_calendar_id: form.value.sla_calendar_id,
        response_minutes: form.value.response_minutes,
        resolution_minutes: form.value.resolution_minutes,
        warning_percent: form.value.warning_percent,
        is_active: form.value.is_active,
      })
    }
    else if (activeTab.value === 'matrix') {
      await adminStore('priority-matrix', {
        impact_id: form.value.impact_id,
        urgency_id: form.value.urgency_id,
        priority_id: form.value.priority_id,
      })
    }
    else {
      const payload: Record<string, any> = {
        code: form.value.code,
        name: form.value.name,
        is_active: form.value.is_active,
      }
      if (activeTab.value !== 'types' && form.value.description)
        payload.description = form.value.description
      await adminStore(activeTab.value, payload)
    }
    showDialog.value = false
    successMsg.value = 'Enregistré'
    await loadAll()
  }
  catch (e: any) {
    const errors = e?.data?.errors
    errorMsg.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e?.data?.message || e.message || 'Erreur de sauvegarde')
  }
  finally {
    saving.value = false
  }
}

async function saveMember() {
  if (!selectedTeam.value?.id || !memberForm.value.user_id)
    return
  saving.value = true
  errorMsg.value = ''
  try {
    await $api(`/ticketing/admin/teams/${selectedTeam.value.id}/members`, {
      method: 'POST',
      body: memberForm.value,
    })
    successMsg.value = 'Membre ajouté'
    memberDialog.value = false
    await loadAll()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Ajout impossible'
  }
  finally {
    saving.value = false
  }
}

async function removeMember(team: any, member: any) {
  saving.value = true
  try {
    await $api(`/ticketing/admin/teams/${team.id}/members/${member.id}`, { method: 'DELETE' })
    successMsg.value = 'Membre retiré'
    await loadAll()
  }
  catch (e: any) {
    errorMsg.value = e?.data?.message || e.message || 'Retrait impossible'
  }
  finally {
    saving.value = false
  }
}

const dialogTitle = computed(() => {
  if (activeTab.value === 'types')
    return 'Nouveau type'
  if (activeTab.value === 'teams')
    return 'Nouvelle équipe'
  if (activeTab.value === 'catalogs')
    return 'Nouveau catalogue'
  if (activeTab.value === 'sla')
    return 'Nouvelle politique SLA'
  if (activeTab.value === 'matrix')
    return 'Entrée matrice priorité'
  return 'Nouveau'
})
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Administration Ticketing"
      subtitle="Référentiels, équipes, SLA et matrice de priorité"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-apps"
          :to="{ name: 'ticketing-applications' }"
          class="me-2"
        >
          Applications & actifs
        </VBtn>
        <VBtn
          v-if="canCreateOnTab"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Ajouter
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

    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular indeterminate />
    </div>

    <VCard v-else>
      <VTabs v-model="activeTab">
        <VTab value="types">
          Types
        </VTab>
        <VTab value="teams">
          Équipes
        </VTab>
        <VTab value="catalogs">
          Catalogues
        </VTab>
        <VTab value="sla">
          SLA
        </VTab>
        <VTab value="matrix">
          Matrice
        </VTab>
      </VTabs>
      <VDivider />
      <VWindow v-model="activeTab">
        <VWindowItem value="types">
          <VDataTable
            :headers="[
              { title: 'Code', key: 'code' },
              { title: 'Nom', key: 'name' },
              { title: 'Actif', key: 'is_active' },
            ]"
            :items="types"
          >
            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ item.is_active ? 'Oui' : 'Non' }}
              </VChip>
            </template>
          </VDataTable>
        </VWindowItem>

        <VWindowItem value="teams">
          <VDataTable
            :headers="[
              { title: 'Code', key: 'code' },
              { title: 'Nom', key: 'name' },
              { title: 'Membres', key: 'members' },
              { title: 'Actif', key: 'is_active' },
              { title: '', key: 'actions', sortable: false },
            ]"
            :items="teams"
          >
            <template #item.members="{ item }">
              {{ (item.members || []).map((m: any) => m.user?.name || m.user_id).join(', ') || '—' }}
            </template>
            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ item.is_active ? 'Oui' : 'Non' }}
              </VChip>
            </template>
            <template #item.actions="{ item }">
              <VBtn
                size="small"
                variant="tonal"
                @click="openMembers(item)"
              >
                Membres
              </VBtn>
            </template>
          </VDataTable>
        </VWindowItem>

        <VWindowItem value="catalogs">
          <VDataTable
            :headers="[
              { title: 'Code', key: 'code' },
              { title: 'Nom', key: 'name' },
              { title: 'Items', key: 'items' },
              { title: 'Actif', key: 'is_active' },
            ]"
            :items="catalogs"
          >
            <template #item.items="{ item }">
              {{ (item.items || []).length }}
            </template>
            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ item.is_active ? 'Oui' : 'Non' }}
              </VChip>
            </template>
          </VDataTable>
        </VWindowItem>

        <VWindowItem value="sla">
          <VDataTable
            :headers="[
              { title: 'Code', key: 'code' },
              { title: 'Nom', key: 'name' },
              { title: 'Priorité', key: 'priority' },
              { title: 'Réponse (min)', key: 'response_minutes' },
              { title: 'Résolution (min)', key: 'resolution_minutes' },
              { title: 'Alerte %', key: 'warning_percent' },
              { title: 'Actif', key: 'is_active' },
            ]"
            :items="slaPolicies"
          >
            <template #item.priority="{ item }">
              {{ item.priority?.name || '—' }}
            </template>
            <template #item.is_active="{ item }">
              <VChip
                size="small"
                :color="item.is_active ? 'success' : 'secondary'"
                variant="tonal"
              >
                {{ item.is_active ? 'Oui' : 'Non' }}
              </VChip>
            </template>
          </VDataTable>
        </VWindowItem>

        <VWindowItem value="matrix">
          <VDataTable
            :headers="[
              { title: 'Impact', key: 'impact' },
              { title: 'Urgence', key: 'urgency' },
              { title: 'Priorité', key: 'priority' },
            ]"
            :items="matrix"
          >
            <template #item.impact="{ item }">
              {{ item.impact?.name || '—' }}
            </template>
            <template #item.urgency="{ item }">
              {{ item.urgency?.name || '—' }}
            </template>
            <template #item.priority="{ item }">
              {{ item.priority?.name || '—' }}
            </template>
          </VDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VDialog
      v-model="showDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>
        <VCardText>
          <template v-if="activeTab === 'matrix'">
            <AppSelect
              v-model="form.impact_id"
              :items="impacts.map(i => ({ value: i.id, title: i.name }))"
              label="Impact *"
              class="mb-3"
            />
            <AppSelect
              v-model="form.urgency_id"
              :items="urgencies.map(u => ({ value: u.id, title: u.name }))"
              label="Urgence *"
              class="mb-3"
            />
            <AppSelect
              v-model="form.priority_id"
              :items="priorities.map(p => ({ value: p.id, title: p.name }))"
              label="Priorité *"
            />
          </template>
          <template v-else-if="activeTab === 'sla'">
            <AppTextField
              v-model="form.code"
              label="Code *"
              class="mb-3"
            />
            <AppTextField
              v-model="form.name"
              label="Nom *"
              class="mb-3"
            />
            <AppSelect
              v-model="form.priority_id"
              :items="priorities.map(p => ({ value: p.id, title: p.name }))"
              label="Priorité"
              clearable
              class="mb-3"
            />
            <AppSelect
              v-model="form.sla_calendar_id"
              :items="calendars.map(c => ({ value: c.id, title: c.name }))"
              label="Calendrier"
              clearable
              class="mb-3"
            />
            <AppTextField
              v-model.number="form.response_minutes"
              type="number"
              label="Réponse (minutes) *"
              class="mb-3"
            />
            <AppTextField
              v-model.number="form.resolution_minutes"
              type="number"
              label="Résolution (minutes) *"
              class="mb-3"
            />
            <AppTextField
              v-model.number="form.warning_percent"
              type="number"
              label="Seuil d’alerte (%)"
              class="mb-3"
            />
            <VCheckbox
              v-model="form.is_active"
              label="Actif"
              hide-details
            />
          </template>
          <template v-else>
            <AppTextField
              v-model="form.code"
              label="Code *"
              class="mb-3"
            />
            <AppTextField
              v-model="form.name"
              label="Nom *"
              class="mb-3"
            />
            <AppTextarea
              v-if="activeTab !== 'types'"
              v-model="form.description"
              label="Description"
              rows="2"
              class="mb-3"
            />
            <VCheckbox
              v-model="form.is_active"
              label="Actif"
              hide-details
            />
          </template>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showDialog = false"
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

    <VDialog
      v-model="memberDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>Membres — {{ selectedTeam?.name }}</VCardTitle>
        <VCardText>
          <div
            v-for="m in (selectedTeam?.members || [])"
            :key="m.id"
            class="d-flex align-center justify-space-between mb-2"
          >
            <div>
              <div class="font-weight-medium">
                {{ m.user?.name || `#${m.user_id}` }}
              </div>
              <div class="text-caption">
                {{ m.level || '—' }}{{ m.is_lead ? ' · Lead' : '' }}
              </div>
            </div>
            <VBtn
              size="small"
              variant="text"
              color="error"
              :loading="saving"
              @click="removeMember(selectedTeam, m)"
            >
              Retirer
            </VBtn>
          </div>
          <VDivider class="my-4" />
          <AppSelect
            v-model="memberForm.user_id"
            :items="users.map(u => ({ value: u.id, title: u.name }))"
            label="Utilisateur *"
            class="mb-3"
          />
          <AppSelect
            v-model="memberForm.level"
            :items="['N1', 'N2', 'N3'].map(l => ({ value: l, title: l }))"
            label="Niveau"
            class="mb-3"
          />
          <VCheckbox
            v-model="memberForm.is_lead"
            label="Lead d’équipe"
            hide-details
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="memberDialog = false"
          >
            Fermer
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!memberForm.user_id"
            @click="saveMember"
          >
            Ajouter
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
