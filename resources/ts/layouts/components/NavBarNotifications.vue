<script lang="ts" setup>
import type { Notification } from '@layouts/types'
import Notifications from '@core/components/Notifications.vue'

type AppNotification = Notification & {
  url?: string | null
}

const router = useRouter()
const notifications = ref<AppNotification[]>([])
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
      color: item.isSeen ? undefined : 'primary',
      url: item.url,
    }))
  }
  catch {
    notifications.value = []
  }
}

const removeNotification = async (notificationId: number | string) => {
  try {
    await $api(`/notifications/${notificationId}`, { method: 'DELETE' })
  }
  catch {}
  notifications.value = notifications.value.filter(item => item.id !== notificationId)
}

const markRead = async (notificationIds: Array<number | string>) => {
  try {
    await $api('/notifications/read', {
      method: 'POST',
      body: { ids: notificationIds },
    })
  }
  catch {}
  notifications.value.forEach(item => {
    if (notificationIds.includes(item.id))
      item.isSeen = true
  })
}

const markUnRead = async (notificationIds: Array<number | string>) => {
  try {
    await $api('/notifications/unread', {
      method: 'POST',
      body: { ids: notificationIds },
    })
  }
  catch {}
  notifications.value.forEach(item => {
    if (notificationIds.includes(item.id))
      item.isSeen = false
  })
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
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
