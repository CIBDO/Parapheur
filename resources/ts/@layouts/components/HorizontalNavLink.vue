<script lang="ts" setup>
import { layoutConfig } from '@layouts';
import { can } from '@layouts/plugins/casl';
import type { NavLink } from '@layouts/types';
import { getComputedNavLinkToProp, getDynamicI18nProps, isNavLinkActive } from '@layouts/utils';

interface Props {
  item: NavLink;

  // ℹ️ We haven't added this prop in vertical nav because we don't need such differentiation in vertical nav for styling
  isSubItem?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isSubItem: false,
});

const router = useRouter();

/** Évite de planter toute la barre si un `to` nom de route est invalide. */
const isRouteResolvable = computed(() => {
  if (!props.item.to)
    return true;
  if (typeof props.item.to === 'string') {
    if (props.item.to.startsWith('/'))
      return true;

    return router.hasRoute(props.item.to);
  }
  if ('name' in props.item.to && props.item.to.name)
    return router.hasRoute(String(props.item.to.name));

  return true;
});
</script>

<template>
  <li
    v-if="can(item.action, item.subject) && isRouteResolvable"
    class="nav-link"
    :class="[
      {
        'sub-item': props.isSubItem,
        disabled: item.disable,
      },
    ]"
  >
    <Component
      :is="item.to ? 'RouterLink' : 'a'"
      v-bind="getComputedNavLinkToProp(item)"
      :class="{
        'router-link-active router-link-exact-active': isNavLinkActive(item, $router),
      }"
    >
      <Component
        :is="layoutConfig.app.iconRenderer || 'div'"
        class="nav-item-icon"
        v-bind="
          item.icon && typeof item.icon === 'object' && item.icon !== null
            ? item.icon
            : layoutConfig.verticalNav.defaultNavItemIconProps || {}
        "
      />
      <Component
        :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
        class="nav-item-title"
        v-bind="getDynamicI18nProps(item.title, 'span')"
      >
        {{ item.title }}
      </Component>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-horizontal-nav {
  .nav-link a {
    display: flex;
    align-items: center;
  }
}
</style>
