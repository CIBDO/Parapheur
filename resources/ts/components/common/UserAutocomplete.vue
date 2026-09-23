<script setup lang="ts">
/**
 * Sélecteur d’utilisateur avec recherche textuelle (nom, email…).
 * Remplace AppSelect pour destinataire / responsable / agent / contributeur.
 */
defineOptions({
  name: 'UserAutocomplete',
  inheritAttrs: false,
})

withDefaults(defineProps<{
  modelValue?: number | number[] | null
  items?: any[]
  label?: string
  placeholder?: string
  itemTitle?: string | ((item: any) => string)
  itemValue?: string
  multiple?: boolean
  chips?: boolean
  clearable?: boolean
  disabled?: boolean
  hint?: string
  persistentHint?: boolean
  hideDetails?: boolean | 'auto'
  density?: string
}>(), {
  items: () => [],
  placeholder: 'Rechercher par nom…',
  itemTitle: 'name',
  itemValue: 'id',
  multiple: false,
  chips: false,
  clearable: true,
  disabled: false,
  persistentHint: false,
  hideDetails: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | number[] | null): void
}>()

const attrs = useAttrs()

const customFilter = (itemTitle: string, queryText: string, item: any) => {
  const q = String(queryText || '').toLowerCase().trim()
  if (!q)
    return true
  const raw = item?.raw ?? item
  const haystack = [
    itemTitle,
    raw?.name,
    raw?.email,
    raw?.title,
    raw?.reference,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

  return haystack.includes(q)
}
</script>

<template>
  <AppAutocomplete
    :model-value="modelValue"
    :items="items"
    :item-title="itemTitle"
    :item-value="itemValue"
    :label="label"
    :placeholder="placeholder"
    :multiple="multiple"
    :chips="chips || multiple"
    :clearable="clearable"
    :disabled="disabled"
    :hint="hint"
    :persistent-hint="persistentHint"
    :hide-details="hideDetails"
    :density="density"
    :custom-filter="customFilter"
    no-data-text="Aucun résultat"
    v-bind="attrs"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
