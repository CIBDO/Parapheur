<script setup lang="ts">
definePage({
  meta: {
    action: 'read',
    subject: 'Instruction',
  },
})

const router = useRouter()
onMounted(() => {
  router.replace('/taches/instructions')
})
</script>

<template>
  <div class="pa-6 text-medium-emphasis">
    Redirection vers le module Instructions…
  </div>
</template>
