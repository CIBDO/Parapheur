import { deepMerge } from '@antfu/utils';
import type { App } from 'vue';
import { useI18n } from 'vue-i18n';
import { createVuetify } from 'vuetify';
import { VBtn } from 'vuetify/components/VBtn';
import { VVideo } from 'vuetify/labs/VVideo';
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n';
import defaults from './defaults';
import { icons } from './icons';
import { staticPrimaryColor, staticPrimaryDarkenColor, themes } from './theme';
import { themeConfig } from '@themeConfig';
import { getI18n } from '@/plugins/i18n/index';

// Styles
import { cookieRef } from '@/@layouts/stores/config';
import '@core-scss/template/libs/vuetify/index.scss';
import 'vuetify/styles';

export default function (app: App) {
  // Remplace l'ancien violet Vuexy éventuellement stocké en cookie
  const legacyPrimary = '#7367F0';
  const lightPrimaryCookie = cookieRef('lightThemePrimaryColor', staticPrimaryColor);
  const lightDarkenCookie = cookieRef('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor);
  const darkPrimaryCookie = cookieRef('darkThemePrimaryColor', staticPrimaryColor);
  const darkDarkenCookie = cookieRef('darkThemePrimaryDarkenColor', staticPrimaryDarkenColor);

  if (lightPrimaryCookie.value === legacyPrimary || !lightPrimaryCookie.value) lightPrimaryCookie.value = staticPrimaryColor;
  if (lightDarkenCookie.value === '#675DD8' || !lightDarkenCookie.value) lightDarkenCookie.value = staticPrimaryDarkenColor;
  if (darkPrimaryCookie.value === legacyPrimary || !darkPrimaryCookie.value) darkPrimaryCookie.value = staticPrimaryColor;
  if (darkDarkenCookie.value === '#675DD8' || !darkDarkenCookie.value) darkDarkenCookie.value = staticPrimaryDarkenColor;

  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          primary: lightPrimaryCookie.value,
          'primary-darken-1': lightDarkenCookie.value,
        },
      },
      dark: {
        colors: {
          primary: darkPrimaryCookie.value,
          'primary-darken-1': darkDarkenCookie.value,
        },
      },
    },
  };

  const optionTheme = deepMerge({ themes }, cookieThemeValues);

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    components: {
      VVideo,
    },
    defaults,
    icons,
    theme: optionTheme,
    locale: {
      adapter: createVueI18nAdapter({ i18n: getI18n(), useI18n }),
    },
  });

  app.use(vuetify);
}
