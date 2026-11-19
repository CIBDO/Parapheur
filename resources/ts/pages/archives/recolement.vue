<script setup lang="ts">
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { useArchives } from '@/composables/useArchives'

definePage({ meta: { action: 'read', subject: 'ArchivesInventory' } })

const {
  loading,
  fetchInventories,
  createInventory,
  startInventory,
  scanInventory,
  closeInventory,
  getJson,
} = useArchives()

const items = ref<any[]>([])
const selected = ref<any | null>(null)
const scanCode = ref('')
const structures = ref<any[]>([])
const dialog = ref(false)
const form = ref({ title: '', structure_id: null as number | null })

async function load() {
  const [inv, structs] = await Promise.all([
    fetchInventories(),
    getJson('/meta/structures').catch(() => ({ data: [] })),
  ])
  items.value = inv.data || []
  structures.value = structs.data || structs || []
  if (!form.value.structure_id && structures.value[0])
    form.value.structure_id = structures.value[0].id
}

async function open(id: number) {
  selected.value = await getJson(`/archives/inventories/${id}`)
}

async function create() {
  const inv = await createInventory({ ...form.value })
  dialog.value = false
  await load()
  await open(inv.id)
}

async function scan() {
  if (!selected.value || !scanCode.value.trim())
    return
  await scanInventory(selected.value.id, scanCode.value.trim())
  scanCode.value = ''
  await open(selected.value.id)
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Récolement"
      subtitle="Campagnes d’inventaire physique"
      icon="tabler-clipboard-check"
    >
      <template #actions>
        <VBtn
          color="primary"
          @click="dialog = true"
        >
          Nouvelle campagne
        </VBtn>
      </template>
    </ParapheurPageHeader>

    <VRow>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="parapheur-section-card">
          <VList :loading="loading">
            <VListItem
              v-for="inv in items"
              :key="inv.id"
              :title="inv.reference"
              :subtitle="`${inv.title} · ${inv.status}`"
              @click="open(inv.id)"
            />
            <VListItem
              v-if="!items.length"
              title="Aucune campagne"
            />
          </VList>
        </VCard>
      </VCol>
      <VCol
        cols="12"
        md="7"
      >
        <VCard
          v-if="selected"
          class="parapheur-section-card"
        >
          <VCardItem>
            <VCardTitle>{{ selected.reference }}</VCardTitle>
            <VCardSubtitle>
              Attendus {{ selected.expected_count }} · trouvés {{ selected.found_count }} · manquants {{ selected.missing_count }}
            </VCardSubtitle>
          </VCardItem>
          <VCardText>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VBtn
                v-if="selected.status === 'brouillon'"
                @click="startInventory(selected.id).then(() => open(selected.id))"
              >
                Démarrer
              </VBtn>
              <VBtn
                v-if="selected.status === 'en_cours'"
                color="success"
                @click="closeInventory(selected.id).then(() => open(selected.id))"
              >
                Clôturer
              </VBtn>
            </div>
            <div
              v-if="selected.status === 'en_cours'"
              class="d-flex gap-2 mb-4"
            >
              <VTextField
                v-model="scanCode"
                label="Scanner code / QR"
                hide-details
                @keyup.enter="scan"
              />
              <VBtn
                color="primary"
                @click="scan"
              >
                Scanner
              </VBtn>
            </div>
            <VList>
              <VListItem
                v-for="item in selected.items || []"
                :key="item.id"
                :title="item.box?.code || item.scanned_code || '—'"
                :subtitle="item.result"
              />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="dialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle>Campagne de récolement</VCardTitle>
        <VCardText class="d-flex flex-column gap-3">
          <VTextField
            v-model="form.title"
            label="Intitulé"
          />
          <VSelect
            v-model="form.structure_id"
            :items="structures.map(s => ({ title: s.name || s.code, value: s.id }))"
            label="Structure"
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
            Créer
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
