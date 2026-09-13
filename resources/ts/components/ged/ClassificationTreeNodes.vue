<script setup lang="ts">
defineProps<{
  nodes: any[]
  canManage: boolean
  depth?: number
}>()

defineEmits<{
  add: [parentId: number | null]
  edit: [node: any]
  remove: [id: number]
}>()
</script>

<template>
  <div>
    <div
      v-for="node in nodes"
      :key="node.id"
      class="classification-node"
      :style="{ marginInlineStart: `${(depth || 0) * 1.25}rem` }"
    >
      <div class="d-flex align-center gap-2 py-1">
        <VIcon
          :icon="node.children?.length ? 'tabler-folder' : 'tabler-folder-open'"
          :size="depth && depth > 1 ? 18 : 20"
          color="warning"
        />
        <strong>{{ node.code }}</strong>
        <span>— {{ node.name }}</span>
        <VChip
          v-if="node.structure?.name"
          size="x-small"
          label
          variant="tonal"
          class="ms-1"
        >
          {{ node.structure.name }}
        </VChip>
        <span class="text-caption text-medium-emphasis ms-1">
          {{ node.path }}
        </span>
        <VSpacer />
        <template v-if="canManage">
          <VBtn
            size="x-small"
            variant="text"
            icon="tabler-plus"
            title="Ajouter un enfant"
            @click="$emit('add', node.id)"
          />
          <VBtn
            size="x-small"
            variant="text"
            icon="tabler-edit"
            title="Modifier / reparenter"
            @click="$emit('edit', node)"
          />
          <VBtn
            size="x-small"
            variant="text"
            color="error"
            icon="tabler-trash"
            title="Supprimer"
            @click="$emit('remove', node.id)"
          />
        </template>
      </div>

      <ClassificationTreeNodes
        v-if="node.children?.length"
        :nodes="node.children"
        :can-manage="canManage"
        :depth="(depth || 0) + 1"
        @add="(id) => $emit('add', id)"
        @edit="(n) => $emit('edit', n)"
        @remove="(id) => $emit('remove', id)"
      />
    </div>
  </div>
</template>
