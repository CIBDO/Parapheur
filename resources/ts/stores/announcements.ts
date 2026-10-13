import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

export interface AnnouncementCard {
  id: number
  reference: string
  title: string
  summary?: string | null
  content?: string | null
  status: string
  confidentiality: string
  acknowledgement_required: boolean
  banner_enabled: boolean
  banner_scroll: boolean
  banner_duration_seconds: number
  banner_priority: number
  dismissible: boolean
  viewed: boolean
  acknowledged: boolean
  favorite: boolean
  published_at?: string | null
  expires_at?: string | null
  category?: { id: number; code: string; name: string; validation_circuit?: string } | null
  importance?: { id: number; code: string; name: string; color: string; rank: number } | null
  structure?: { id: number; code: string; name: string } | null
  author?: { id: number; name: string } | null
}

const TTL_MS = 60_000

export const useAnnouncementStore = defineStore('announcements', () => {
  const items = ref<AnnouncementCard[]>([])
  const loadedAt = ref(0)
  const loading = ref(false)

  async function load(force = false) {
    if (!force && loadedAt.value && Date.now() - loadedAt.value < TTL_MS)
      return

    loading.value = true
    try {
      items.value = await $api<AnnouncementCard[]>('/announcements/banner')
      loadedAt.value = Date.now()
    }
    catch {
      items.value = []
    }
    finally {
      loading.value = false
    }
  }

  function invalidate() {
    loadedAt.value = 0
  }

  return { items, loading, load, invalidate }
})
