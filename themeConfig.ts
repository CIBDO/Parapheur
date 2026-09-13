import { breakpointsVuetifyV3 } from '@vueuse/core';
import { VIcon } from 'vuetify/components/VIcon';
import { defineThemeConfig } from '@core';
import { Skins } from '@core/enums';

import logoDgtcp from '@images/logo-dgtcp.png';
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums';

export const { themeConfig, layoutConfig } = defineThemeConfig({
  app: {
    title: 'E-Tresor',
    logo: h(
      'div',
      {
        style: 'overflow:hidden;border-radius:50%;block-size:42px;inline-size:42px;',
      },
      [
        h('img', {
          src: logoDgtcp,
          alt: 'DGTCP',
          style: 'block-size:100%;inline-size:100%;object-fit:cover;transform:scale(1.14);',
        }),
      ],
    ),
    contentWidth: ContentWidth.Fluid,
    contentLayoutNav: AppContentLayoutNav.Horizontal,
    overlayNavFromBreakpoint: breakpointsVuetifyV3.lg - 1,
    i18n: {
      enable: false,
      defaultLocale: 'fr',
      langConfig: [
        {
          label: 'Français',
          i18nLang: 'fr',
          isRTL: false,
        },
      ],
    },
    theme: 'light',
    skin: Skins.Default,
    iconRenderer: VIcon,
  },
  navbar: {
    type: NavbarType.Sticky,
    navbarBlur: true,
  },
  footer: { type: FooterType.Static },
  verticalNav: {
    isVerticalNavCollapsed: false,
    defaultNavItemIconProps: { icon: 'tabler-circle' },
    isVerticalNavSemiDark: false,
  },
  horizontalNav: {
    type: 'sticky',
    transition: 'slide-y-reverse-transition',
    popoverOffset: 6,
  },
  icons: {
    chevronDown: { icon: 'tabler-chevron-down' },
    chevronRight: { icon: 'tabler-chevron-right', size: 20 },
    close: { icon: 'tabler-x', size: 20 },
    verticalNavPinned: { icon: 'tabler-circle-dot', size: 20 },
    verticalNavUnPinned: { icon: 'tabler-circle', size: 20 },
    sectionTitlePlaceholder: { icon: 'tabler-minus' },
  },
});
