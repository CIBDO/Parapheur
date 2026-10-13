<script setup lang="ts">
import type { AnnouncementCard } from '@/stores/announcements'
import { useAnnouncementStore } from '@/stores/announcements'

const store = useAnnouncementStore()
const router = useRouter()
const viewport = ref<HTMLElement | null>(null)
const segment = ref<HTMLElement | null>(null)
const paused = ref(false)
const reduceMotion = ref(false)
const offset = ref(0)
const segmentWidth = ref(0)

const animate = computed(() =>
  store.items.some(item => item.banner_scroll) && !reduceMotion.value,
)

const trackStyle = computed(() => (
  animate.value ? { transform: `translate3d(${offset.value}px, 0, 0)` } : undefined
))

let frameId = 0
let lastTime = 0

function setSegment(el: Element | null) {
  segment.value = el as HTMLElement | null
  measure()
}

function measure() {
  const view = viewport.value?.clientWidth ?? 0
  if (viewport.value && view > 0)
    viewport.value.style.setProperty('--banner-span', `${view}px`)

  segmentWidth.value = segment.value?.getBoundingClientRect().width ?? segment.value?.scrollWidth ?? 0
}

function normalize() {
  const width = segmentWidth.value
  if (width <= 0)
    return
  while (offset.value <= -width)
    offset.value += width
  while (offset.value > 0)
    offset.value -= width
}

function tick(now: number) {
  const delta = lastTime ? (now - lastTime) / 1000 : 0
  lastTime = now

  if (animate.value && !paused.value && segmentWidth.value > 0) {
    const duration = Math.max(store.items[0]?.banner_duration_seconds ?? 12, 12)
    offset.value -= (segmentWidth.value / duration) * delta
    normalize()
  }

  frameId = requestAnimationFrame(tick)
}

onMounted(() => {
  reduceMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const needsContent = store.items.length > 0 && store.items.some(item => !item.content)
  store.load(needsContent)
  window.addEventListener('focus', onFocus)
  window.addEventListener('resize', measure)
  nextTick(measure)
  frameId = requestAnimationFrame(tick)
})

onUnmounted(() => {
  window.removeEventListener('focus', onFocus)
  window.removeEventListener('resize', measure)
  cancelAnimationFrame(frameId)
})

watch(() => store.items, () => {
  offset.value = 0
  nextTick(measure)
}, { deep: true })

function onFocus() {
  store.load()
}

function shift(direction: number) {
  if (!animate.value) {
    viewport.value?.scrollBy({ left: direction * 240, behavior: 'smooth' })

    return
  }

  offset.value -= direction * 220
  normalize()
}

function open(item: AnnouncementCard) {
  router.push({ name: 'annonces-id', params: { id: item.id } })
}

function canDismiss(item: AnnouncementCard) {
  if (item.acknowledgement_required && !item.acknowledged)
    return false

  return item.dismissible || item.acknowledged
}

async function dismiss(item: AnnouncementCard) {
  await $api(`/announcements/${item.id}/dismiss`, { method: 'POST' })
  store.invalidate()
  await store.load(true)
}
</script>

<template>
  <div
    v-if="store.items.length"
    class="announcement-banner"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
    @focusin="paused = true"
    @focusout="paused = false"
  >
    <VBtn
      icon
      variant="text"
      size="small"
      aria-label="Annonces précédentes"
      @click="shift(-1)"
    >
      <VIcon icon="tabler-chevron-left" />
    </VBtn>

    <div
      ref="viewport"
      class="announcement-banner__viewport"
      :class="{ 'announcement-banner__viewport--static': !animate }"
      tabindex="0"
    >
      <div
        class="announcement-banner__marquee"
        :style="trackStyle"
      >
        <div
          v-for="copy in (animate ? [0, 1] : [0])"
          :key="copy"
          :ref="copy === 0 ? setSegment : undefined"
          class="announcement-banner__segment"
          :aria-hidden="copy === 1 ? 'true' : undefined"
        >
          <button
            v-for="item in store.items"
            :key="`${copy}-${item.id}`"
            type="button"
            class="announcement-banner__item"
            :class="`announcement-banner__item--${item.importance?.color || 'primary'}`"
            :tabindex="copy === 1 ? -1 : 0"
            @click="open(item)"
          >
            <VIcon
              icon="tabler-speakerphone"
              size="18"
            />
            <span class="text-caption font-weight-medium">{{ item.category?.name }}</span>
            <span class="announcement-banner__title">{{ item.content || item.summary }}</span>
            <VChip
              v-if="item.importance"
              size="x-small"
              :color="item.importance.color"
              variant="flat"
            >
              {{ item.importance.name }}
            </VChip>
            <VBtn
              v-if="canDismiss(item) && copy === 0"
              icon
              size="x-small"
              variant="text"
              aria-label="Masquer l'annonce"
              @click.stop="dismiss(item)"
            >
              <VIcon
                icon="tabler-x"
                size="16"
              />
            </VBtn>
          </button>
        </div>
      </div>
    </div>

    <VBtn
      icon
      variant="text"
      size="small"
      aria-label="Annonces suivantes"
      @click="shift(1)"
    >
      <VIcon icon="tabler-chevron-right" />
    </VBtn>
    <VBtn
      variant="text"
      size="small"
      class="text-none"
      :to="{ name: 'annonces' }"
    >
      Toutes les annonces
    </VBtn>
  </div>
</template>

<style scoped>
.announcement-banner {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  inline-size: 100%;
  max-inline-size: 100%;
  min-inline-size: 0;
  overflow: hidden;
  border-block-end: 1px solid rgba(11, 107, 58, 0.16);
  background: #f7faf8;
  padding-inline: 0.75rem;
}

.announcement-banner__viewport {
  overflow: hidden;
  flex: 1;
  min-inline-size: 0;
  padding-block: 0.45rem;
}

.announcement-banner__viewport--static {
  overflow-x: auto;
  scrollbar-width: none;
}

.announcement-banner__viewport--static::-webkit-scrollbar {
  display: none;
}

.announcement-banner__marquee {
  display: flex;
  inline-size: max-content;
  will-change: transform;
}

.announcement-banner__segment {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 1.5rem;
  min-inline-size: var(--banner-span, 0px);
  padding-inline-end: 2.5rem;
}

.announcement-banner__item {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.5rem;
  border: 0;
  border-radius: 999px;
  background: rgba(11, 107, 58, 0.06);
  color: inherit;
  cursor: pointer;
  padding-block: 0.35rem;
  padding-inline: 0.75rem;
  text-align: start;
}

.announcement-banner__item--warning {
  background: rgba(252, 209, 22, 0.28);
}

.announcement-banner__item--error {
  background: rgba(206, 17, 38, 0.12);
}

.announcement-banner__title {
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .announcement-banner__marquee {
    will-change: auto;
  }
}
</style>
