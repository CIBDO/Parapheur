<script setup lang="ts">
import CreateTaskDialog from '@/components/tasks/CreateTaskDialog.vue'

definePage({
  meta: {
    action: 'create',
    subject: 'Task',
  },
})

const router = useRouter()
const isDialogVisible = ref(true)

const onClose = (visible: boolean) => {
  isDialogVisible.value = visible
  if (!visible)
    router.replace({ name: 'taches' })
}
</script>

<template>
  <CreateTaskDialog
    :is-dialog-visible="isDialogVisible"
    @update:is-dialog-visible="onClose"
  />
</template>
