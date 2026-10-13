<script setup lang="ts">
import { computed, ref } from 'vue'
import UserAutocomplete from '@/components/common/UserAutocomplete.vue'
import { useCorrespondence } from '@/composables/useCorrespondence'
import { $api } from '@/utils/api'
import { formatCorrespondenceNumber } from '@/utils/courrierUi'

const props = defineProps<{
  item: any
}>()

const emit = defineEmits<{
  imputed: []
}>()

const { assign } = useCorrespondence()

const printing = ref(false)
const assigning = ref(false)
const showAssign = ref(false)
const structures = ref<any[]>([])
const serviceAgents = ref<any[]>([])
const form = ref({
  to_structure_id: null as number | null,
  to_user_id: null as number | null,
  instruction_text: '',
  due_date: '',
})

const structureItems = computed(() =>
  structures.value.map(structure => ({
    value: structure.id,
    title: structure.code ? `${structure.code} — ${structure.name}` : structure.name,
  })),
)

async function printCirculationSheet() {
  printing.value = true
  try {
    const blob = await $api(`/mail/correspondences/${props.item.id}/circulation-sheet/pdf`, {
      responseType: 'blob',
    }) as Blob
    const file = blob instanceof Blob ? blob : new Blob([blob], { type: 'application/pdf' })
    window.open(URL.createObjectURL(file), '_blank')
  }
  catch (error: any) {
    alert(error?.data?.message || 'Impression de la fiche impossible')
  }
  finally {
    printing.value = false
  }
}

async function openAssign() {
  form.value = { to_structure_id: null, to_user_id: null, instruction_text: '', due_date: '' }
  serviceAgents.value = []
  showAssign.value = true
  if (structures.value.length)
    return

  const list = await $api('/meta/structures')
  structures.value = Array.isArray(list) ? list : []
}

async function loadServiceAgents(structureId: number | null) {
  form.value.to_user_id = null
  serviceAgents.value = []
  if (!structureId)
    return

  const query: Record<string, string | number> = { structure_id: structureId }
  const clearance = props.item.confidentiality
  if (clearance === 'confidentiel' || clearance === 'tres_confidentiel')
    query.clearance = clearance

  const list = await $api('/meta/users', { query })
  serviceAgents.value = Array.isArray(list) ? list : []
}

async function confirmAssign() {
  if (!form.value.to_user_id || !form.value.to_structure_id)
    return

  assigning.value = true
  try {
    await assign(props.item.id, [{
      to_user_id: form.value.to_user_id,
      to_structure_id: form.value.to_structure_id,
      instruction_text: form.value.instruction_text || undefined,
      due_date: form.value.due_date || undefined,
    }])
    showAssign.value = false
    emit('imputed')
    const blob = await $api(`/mail/correspondences/${props.item.id}/imputation-sheet`, {
      responseType: 'blob',
    }) as Blob
    const file = blob instanceof Blob ? blob : new Blob([blob], { type: 'application/pdf' })
    window.open(URL.createObjectURL(file), '_blank')
  }
  catch (error: any) {
    alert(error?.data?.message || 'Imputation impossible')
  }
  finally {
    assigning.value = false
  }
}
</script>

<template>
  <div
    class="d-flex justify-end gap-1"
    @click.stop
  >
    <VBtn
      icon
      size="small"
      variant="tonal"
      color="primary"
      :loading="printing"
      @click="printCirculationSheet"
    >
      <VIcon icon="tabler-printer" />
      <VTooltip
        activator="parent"
        location="top"
      >
        Imprimer la fiche de circulation
      </VTooltip>
    </VBtn>
    <VBtn
      icon
      size="small"
      variant="tonal"
      color="secondary"
      @click="openAssign"
    >
      <VIcon icon="tabler-user-plus" />
      <VTooltip
        activator="parent"
        location="top"
      >
        Imputer
      </VTooltip>
    </VBtn>
  </div>

  <VDialog
    v-model="showAssign"
    max-width="520"
  >
    <VCard :title="`Imputer — ${formatCorrespondenceNumber(item)}`">
      <VCardText>
        <AppAutocomplete
          :model-value="form.to_structure_id"
          class="mb-3"
          :items="structureItems"
          label="Service *"
          placeholder="Rechercher un service…"
          item-title="title"
          item-value="value"
          clearable
          @update:model-value="(value: number | null) => { form.to_structure_id = value; loadServiceAgents(value) }"
        />
        <UserAutocomplete
          v-model="form.to_user_id"
          class="mb-3"
          :items="serviceAgents"
          :disabled="!form.to_structure_id"
          label="Agent *"
          placeholder="Agents du service…"
          hint="Seuls les agents du service sélectionné sont proposés."
          persistent-hint
        />
        <AppTextField
          v-model="form.due_date"
          class="mb-3"
          type="date"
          label="Échéance"
        />
        <AppTextarea
          v-model="form.instruction_text"
          label="Instruction"
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="showAssign = false">
          Annuler
        </VBtn>
        <VBtn
          color="primary"
          :loading="assigning"
          :disabled="!form.to_user_id || !form.to_structure_id"
          @click="confirmAssign"
        >
          Confirmer
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
