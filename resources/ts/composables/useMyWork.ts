export const useMyWork = () => {
  const loading = ref(false)
  const data = ref<{
    counts: Record<string, number>
    tasks: any[]
    instructions: any[]
    other: any[]
  }>({
    counts: {},
    tasks: [],
    instructions: [],
    other: [],
  })

  const load = async (limit = 10) => {
    loading.value = true
    try {
      data.value = await $api('/my-work', { query: { limit } })
      return data.value
    }
    finally {
      loading.value = false
    }
  }

  return { loading, data, load }
}
