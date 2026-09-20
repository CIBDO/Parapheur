<script lang="ts" setup>
import type { Notification } from '@layouts/types'
import Notifications from '@core/components/Notifications.vue'

type AppNotification = Notification & {
  url?: string | null
}

const router = useRouter()
const notifications = ref<AppNotification[]>([])
const unreadCount = ref(0)
let pollTimer: ReturnType<typeof setInterval> | undefined

const load = async () => {
  try {
    const res = await $api('/notifications')
    notifications.value = (res.data || []).map((item: any) => ({
      id: item.id,
      icon: item.icon || 'tabler-bell',
      title: item.title,
      subtitle: item.subtitle,
      time: item.time,
      isSeen: item.isSeen,
      color: item.color || (item.isSeen ? undefined : 'primary'),
      domain: item.domain || undefined,
      url: item.url,
    }))
    unreadCount.value = Number(res.unread_count ?? notifications.value.filter(n => !n.isSeen).length)
  }
  catch {
    notifications.value = []
    unreadCount.value = 0
  }
}

const removeNotification = async (notificationId: number | string) => {
  const wasUnread = notifications.value.find(item => item.id === notificationId && !item.isSeen)
  try {
    await $api(`/notifications/${notificationId}`, { method: 'DELETE' })
  }
  catch {}
  notifications.value = notifications.value.filter(item => item.id !== notificationId)
  if (wasUnread)
    unreadCount.value = Math.max(0, unreadCount.value - 1)
}

const markRead = async (notificationIds: Array<number | string>) => {
  const unreadIds = notifications.value
    .filter(item => notificationIds.includes(item.id) && !item.isSeen)
    .map(item => item.id)

  try {
    await $api('/notifications/read', {
      method: 'POST',
      body: { ids: notificationIds },
    })
  }
  catch {}
  notifications.value.forEach(item => {
    if (notificationIds.includes(item.id)) {
      item.isSeen = true
      item.color = undefined
    }
  })
  unreadCount.value = Math.max(0, unreadCount.value - unreadIds.length)
}

const markUnRead = async (notificationIds: Array<number | string>) => {
  const alreadyUnread = new Set(
    notifications.value.filter(item => !item.isSeen).map(item => item.id),
  )
  const newlyUnread = notificationIds.filter(id => !alreadyUnread.has(id))

  try {
    await $api('/notifications/unread', {
      method: 'POST',
      body: { ids: notificationIds },
    })
  }
  catch {}
  notifications.value.forEach(item => {
    if (notificationIds.includes(item.id)) {
      item.isSeen = false
      item.color = item.color || 'primary'
    }
  })
  unreadCount.value += newlyUnread.length
}

const handleNotificationClick = async (notification: AppNotification) => {
  if (!notification.isSeen)
    await markRead([notification.id])

  if (notification.url)
    await router.push(notification.url)
}

onMounted(() => {
  load()
  pollTimer = setInterval(load, 60000)
})

onBeforeUnmount(() => {
  if (pollTimer)
    clearInterval(pollTimer)
})
</script>

<template>
  <Notifications
    :notifications="notifications"
    :unread-count="unreadCount"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
