<script lang="ts" setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { Notification } from '@layouts/types'

interface Props {
  notifications: Notification[]
  badgeProps?: object
  location?: any
  /** Compteur non lus (API) ; sinon calculé sur la liste chargée */
  unreadCount?: number | null
}
interface Emit {
  (e: 'read', value: Array<number | string>): void
  (e: 'unread', value: Array<number | string>): void
  (e: 'remove', value: number | string): void
  (e: 'click:notification', value: Notification): void
}

const props = withDefaults(defineProps<Props>(), {
  location: 'bottom end',
  badgeProps: undefined,
  unreadCount: null,
})

const emit = defineEmits<Emit>()

const isAllMarkRead = computed(() => {
  return props.notifications.some(item => item.isSeen === false)
})

const markAllReadOrUnread = () => {
  const allNotificationsIds = props.notifications.map(item => item.id)

  if (!isAllMarkRead.value)
    emit('unread', allNotificationsIds)
  else emit('read', allNotificationsIds)
}

const totalUnseenNotifications = computed(() => {
  if (props.unreadCount !== null && props.unreadCount !== undefined)
    return props.unreadCount

  return props.notifications.filter(item => item.isSeen === false).length
})

const toggleReadUnread = (isSeen: boolean, Id: number | string) => {
  if (isSeen)
    emit('unread', [Id])
  else emit('read', [Id])
}
</script>

<template>
  <IconBtn id="notification-btn">
    <VBadge
      v-bind="props.badgeProps"
      :model-value="totalUnseenNotifications > 0"
      :content="totalUnseenNotifications > 99 ? '99+' : totalUnseenNotifications"
      color="error"
      offset-x="2"
      offset-y="3"
    >
      <VIcon icon="tabler-bell" />
    </VBadge>

    <VMenu
      activator="parent"
      width="380px"
      :location="props.location"
      offset="12px"
      :close-on-content-click="false"
    >
      <VCard class="d-flex flex-column">
        <VCardItem class="notification-section">
          <VCardTitle class="text-h6">
            Notifications
          </VCardTitle>

          <template #append>
            <VChip
              v-show="totalUnseenNotifications > 0"
              size="small"
              color="primary"
              class="me-2"
            >
              {{ totalUnseenNotifications }} nouvelle{{ totalUnseenNotifications > 1 ? 's' : '' }}
            </VChip>
            <IconBtn
              v-show="props.notifications.length"
              size="34"
              @click="markAllReadOrUnread"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                :icon="!isAllMarkRead ? 'tabler-mail' : 'tabler-mail-opened'"
              />

              <VTooltip
                activator="parent"
                location="start"
              >
                {{ !isAllMarkRead ? 'Tout marquer comme non lu' : 'Tout marquer comme lu' }}
              </VTooltip>
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          style="max-block-size: 23.75rem"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in props.notifications"
              :key="notification.id ?? notification.title"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="one"
                min-height="66px"
                class="list-item-hover-class"
                @click="$emit('click:notification', notification)"
              >
                <div class="d-flex align-start gap-3">
                  <VAvatar
                    :color="notification.color && !notification.img ? notification.color : undefined"
                    :variant="notification.img ? undefined : 'tonal'"
                  >
                    <span v-if="notification.text">{{ avatarText(notification.text) }}</span>
                    <VImg
                      v-if="notification.img"
                      :src="notification.img"
                    />
                    <VIcon
                      v-if="notification.icon"
                      :icon="notification.icon"
                    />
                  </VAvatar>

                  <div>
                    <p class="text-sm font-weight-medium mb-1">
                      {{ notification.title }}
                    </p>
                    <p
                      class="text-body-2 mb-2"
                      style="letter-spacing: 0.4px !important; line-height: 18px"
                    >
                      {{ notification.subtitle }}
                    </p>
                    <p
                      class="text-sm text-disabled mb-0"
                      style="letter-spacing: 0.4px !important; line-height: 18px"
                    >
                      {{ notification.time }}
                    </p>
                  </div>
                  <VSpacer />

                  <div class="d-flex flex-column align-end">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.isSeen ? 'primary' : '#a8aaae'"
                      :class="`${notification.isSeen ? 'visible-in-hover' : ''}`"
                      class="mb-2"
                      @click.stop="toggleReadUnread(notification.isSeen, notification.id)"
                    />

                    <VIcon
                      size="20"
                      icon="tabler-x"
                      class="visible-in-hover"
                      @click="$emit('remove', notification.id)"
                    />
                  </div>
                </div>
              </VListItem>
            </template>

            <VListItem
              v-show="!props.notifications.length"
              class="text-center text-medium-emphasis"
              style="block-size: 56px"
            >
              <VListItemTitle>Aucune notification</VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>

        <VDivider />

        <VCardText
          v-show="props.notifications.length"
          class="pa-4"
        >
          <VBtn
            block
            size="small"
            variant="tonal"
            color="primary"
            :to="{ name: 'parapheur' }"
          >
            Voir mon parapheur
          </VBtn>
        </VCardText>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.notification-section {
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover {
    .visible-in-hover {
      display: block;
    }
  }
}

.notification-list.v-list {
  .v-list-item {
    border-radius: 0 !important;
    margin: 0 !important;

    .v-list-item__append {
      .v-list-item__spacer {
        inline-size: 0.5rem !important;
      }

      .visible-in-hover {
        display: none;
      }
    }

    &:hover {
      .v-list-item__append {
        .visible-in-hover {
          display: block;
        }
      }
    }
  }
}
</style>
