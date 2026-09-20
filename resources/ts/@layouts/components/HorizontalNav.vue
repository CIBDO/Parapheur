<script lang="ts" setup>
import { HorizontalNavGroup, HorizontalNavLink } from '@layouts/components';
import type { HorizontalNavItems, NavGroup, NavLink } from '@layouts/types';

defineProps<{
  navItems: HorizontalNavItems;
}>();

const resolveNavItemComponent = (item: NavLink | NavGroup) => {
  if ('children' in item) return HorizontalNavGroup;

  return HorizontalNavLink;
};
</script>

<template>
  <ul class="nav-items">
    <Component :is="resolveNavItemComponent(item)" v-for="(item, index) in navItems" :key="index" data-allow-mismatch :item="item" />
  </ul>
</template>

<style lang="scss">
.layout-wrapper.layout-nav-type-horizontal {
  .nav-items {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 2px;
    overflow-x: auto;
    white-space: nowrap;

    // Évite le wrap tout en compactant les entrées top-level
    > .nav-link:not(.sub-item) a,
    > .nav-group:not(.sub-item) > .popper-triggerer > .nav-group-label {
      padding-inline: 0.625rem;
    }

    > .nav-link:not(.sub-item) .nav-item-icon,
    > .nav-group:not(.sub-item) .nav-item-icon {
      font-size: 1.25rem;
      margin-inline-end: 0.375rem;
    }

    > .nav-group:not(.sub-item) .nav-group-arrow {
      font-size: 1.125rem;
    }
  }
}
</style>
