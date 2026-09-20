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
      width="420px"
      :location="props.location"
      offset="12px"
      :close-on-content-click="false"
    >
      <VCard class="notification-panel d-flex flex-column">
        <VCardItem class="notification-section">
          <div>
            <VCardTitle class="text-h6 mb-0">
              Notifications
            </VCardTitle>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Bureau Numérique · DGTCP
            </p>
          </div>

          <template #append>
            <VChip
              v-show="totalUnseenNotifications > 0"
              size="small"
              color="primary"
              variant="tonal"
              class="me-1"
            >
              {{ totalUnseenNotifications }} non lue{{ totalUnseenNotifications > 1 ? 's' : '' }}
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
          style="max-block-size: 26rem"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in props.notifications"
              :key="notification.id ?? notification.title"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="three"
                min-height="78px"
                class="list-item-hover-class notification-item"
                :class="{ 'notification-item--unread': !notification.isSeen }"
                @click="$emit('click:notification', notification)"
              >
                <div class="d-flex align-start gap-3 w-100">
                  <VAvatar
                    size="40"
                    :color="notification.color || 'secondary'"
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
                      size="22"
                    />
                  </VAvatar>

                  <div class="notification-body flex-grow-1 min-w-0">
                    <div class="d-flex align-center gap-2 mb-1 flex-wrap">
                      <span
                        v-if="notification.domain"
                        class="notification-domain text-caption"
                      >
                        {{ notification.domain }}
                      </span>
                      <span class="text-caption text-disabled">{{ notification.time }}</span>
                    </div>
                    <p class="text-sm font-weight-medium mb-1 text-high-emphasis">
                      {{ notification.title }}
                    </p>
                    <p class="text-body-2 text-medium-emphasis mb-0 notification-subtitle">
                      {{ notification.subtitle }}
                    </p>
                  </div>

                  <div class="d-flex flex-column align-end flex-shrink-0">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.isSeen ? (notification.color || 'primary') : '#a8aaae'"
                      :class="`${notification.isSeen ? 'visible-in-hover' : ''}`"
                      class="mb-2"
                      @click.stop="toggleReadUnread(notification.isSeen, notification.id)"
                    />

                    <VIcon
                      size="18"
                      icon="tabler-x"
                      class="visible-in-hover text-disabled"
                      @click.stop="$emit('remove', notification.id)"
                    />
                  </div>
                </div>
              </VListItem>
            </template>

            <div
              v-show="!props.notifications.length"
              class="notification-empty text-center pa-8"
            >
              <VAvatar
                size="48"
                color="secondary"
                variant="tonal"
                class="mb-3"
              >
                <VIcon
                  icon="tabler-bell-off"
                  size="26"
                />
              </VAvatar>
              <p class="text-body-1 font-weight-medium mb-1">
                Aucune notification
              </p>
              <p class="text-caption text-medium-emphasis mb-0">
                Les alertes du Bureau Numérique apparaîtront ici.
              </p>
            </div>
          </VList>
        </PerfectScrollbar>

        <VDivider v-if="props.notifications.length && isAllMarkRead" />

        <VCardText
          v-show="props.notifications.length && isAllMarkRead"
          class="pa-3"
        >
          <VBtn
            block
            size="small"
            variant="tonal"
            color="primary"
            @click="markAllReadOrUnread"
          >
            Tout marquer comme lu
          </VBtn>
        </VCardText>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.notification-panel {
  overflow: hidden;
}

.notification-section {
  padding-block: 0.875rem;
  padding-inline: 1rem;
}

.notification-domain {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.notification-subtitle {
  display: -webkit-box;
  letter-spacing: 0.2px;
  line-height: 1.35;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.notification-item {
  position: relative;

  &--unread {
    background: rgba(var(--v-theme-primary), 0.04);

    &::before {
      background: rgb(var(--v-theme-primary));
      block-size: 100%;
      content: '';
      inline-size: 3px;
      inset-block-start: 0;
      inset-inline-start: 0;
      position: absolute;
    }
  }
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
    padding-block: 0.75rem !important;

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
