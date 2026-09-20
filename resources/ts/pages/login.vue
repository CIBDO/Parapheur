<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import logoDgtcp from '@images/logo-dgtcp.png'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)
const isSubmitting = ref(false)
const isOpen = ref(false)

const route = useRoute()
const router = useRouter()
const ability = useAbility()

const errors = ref<Record<string, string | undefined>>({
  email: undefined,
  password: undefined,
})

const refVForm = ref<VForm>()

const credentials = ref({
  email: '',
  password: '',
})

const rememberMe = ref(false)

const logoSrc = typeof logoDgtcp === 'string'
  ? logoDgtcp
  : (logoDgtcp as { default?: string })?.default || '/logo.png'

const openParapheur = () => {
  if (!isOpen.value)
    isOpen.value = true
}

const login = async () => {
  isSubmitting.value = true
  errors.value = { email: undefined, password: undefined }

  try {
    const res = await $api('/auth/login', {
      method: 'POST',
      body: {
        email: credentials.value.email,
        password: credentials.value.password,
      },
      onResponseError({ response }) {
        const payload = response._data?.errors || {}
        const message = response._data?.message

        errors.value = {
          email: Array.isArray(payload.email) ? payload.email[0] : (payload.email || message),
          password: Array.isArray(payload.password) ? payload.password[0] : payload.password,
        }

        if (!errors.value.email && !errors.value.password)
          errors.value.email = 'Connexion impossible. Réessayez.'
      },
    })

    const { accessToken, userData, userAbilityRules } = res

    useCookie('userAbilityRules').value = userAbilityRules
    useCookie('userData').value = userData
    useCookie('accessToken').value = accessToken
    ability.update(userAbilityRules || [])

    if (userData?.mustChangePassword) {
      await nextTick(() => {
        router.replace({ name: 'change-password' })
      })

      return
    }

    const role = String(userData?.role ?? '')
    const fallback = role === 'Administrateur'
      ? '/parapheur/admin'
      : (role === 'Directeur Général' || role === 'DGA' ? '/parapheur/dg' : '/parapheur')

    await nextTick(() => {
      router.replace(route.query.to ? String(route.query.to) : fallback)
    })
  }
  catch (err) {
    console.error(err)
    if (!errors.value.email && !errors.value.password)
      errors.value.email = 'Connexion impossible. Vérifiez vos identifiants ou réessayez.'
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      login()
  })
}

onMounted(() => {
  window.setTimeout(() => {
    isOpen.value = true
  }, 700)
})
</script>

<template>
  <div class="parapheur-auth bg-background">
    <div
      class="parapheur-book"
      :class="{ 'parapheur-book--open': isOpen }"
    >
      <button
        type="button"
        class="parapheur-cover"
        :aria-expanded="isOpen"
        aria-label="Ouvrir le parapheur"
        @click="openParapheur"
      >
        <span class="parapheur-cover__spine" aria-hidden="true" />
        <span class="parapheur-cover__face">
          <span class="parapheur-cover__logo-wrap">
            <img
              :src="logoSrc"
              alt=""
              width="88"
              height="88"
              class="parapheur-cover__logo"
            >
          </span>
          <span class="parapheur-cover__title">E-Tresor</span>
          <span class="parapheur-cover__sub">DGTCP — Trésor Public</span>
          <span class="parapheur-cover__hint">
            {{ isOpen ? '' : 'Cliquer pour ouvrir' }}
          </span>
        </span>
      </button>

      <div class="parapheur-inside">
        <aside class="parapheur-page parapheur-page--left">
          <div class="parapheur-page__content">
            <div class="parapheur-page__logo-wrap">
              <img
                :src="logoSrc"
                alt="DGTCP"
                width="72"
                height="72"
                class="parapheur-page__logo"
              >
            </div>
            <h1 class="parapheur-page__brand">
              E-Tresor
            </h1>
            <p class="parapheur-page__text">
              Bureau numérique des circuits de consultation, de visa et de validation.
            </p>
            <p class="parapheur-page__meta">
              Direction Générale du Trésor<br>
              et de la Comptabilité Publique
            </p>
          </div>
        </aside>

        <section class="parapheur-page parapheur-page--right">
          <div class="parapheur-page__content">
            <header class="parapheur-mobile-brand">
              <div class="parapheur-page__logo-wrap parapheur-mobile-brand__logo">
                <img
                  :src="logoSrc"
                  alt="DGTCP"
                  width="64"
                  height="64"
                  class="parapheur-page__logo"
                >
              </div>
              <h1 class="parapheur-page__brand">
                E-Tresor
              </h1>
            </header>

            <h2 class="text-h5 mb-1 text-center">
              Connexion
            </h2>
            <p class="text-body-2 text-medium-emphasis mb-6 text-center">
              Accédez à votre parapheur électronique
            </p>

            <VForm
              ref="refVForm"
              @submit.prevent="onSubmit"
            >
              <AppTextField
                v-model="credentials.email"
                label="Adresse e-mail"
                placeholder="prenom.nom@dgtcp.ml"
                type="email"
                autofocus
                autocomplete="username"
                class="mb-4"
                :rules="[requiredValidator, emailValidator]"
                :error-messages="errors.email"
              />

              <AppTextField
                v-model="credentials.password"
                label="Mot de passe"
                placeholder="············"
                autocomplete="current-password"
                class="mb-4"
                :rules="[requiredValidator]"
                :type="isPasswordVisible ? 'text' : 'password'"
                :error-messages="errors.password"
                :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />

              <div class="login-meta mb-4">
                <label class="login-meta__remember">
                  <input
                    v-model="rememberMe"
                    type="checkbox"
                    class="login-meta__check"
                  >
                  <span>Se souvenir de moi</span>
                </label>
                <RouterLink
                  class="login-meta__forgot text-primary"
                  :to="{ name: 'forgot-password' }"
                >
                  Mot de passe oublié ?
                </RouterLink>
              </div>

              <VBtn
                block
                type="submit"
                color="primary"
                :loading="isSubmitting"
              >
                Ouvrir mon parapheur
              </VBtn>
            </VForm>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
@use '@core-scss/template/pages/page-auth';

.parapheur-auth {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100dvh;
  padding: clamp(0.75rem, 2.5vw, 1.5rem);
  perspective: 1600px;
}

.parapheur-book {
  position: relative;
  inline-size: min(100%, 920px);
  block-size: min(78dvh, 560px);
  transform-style: preserve-3d;
}

.parapheur-cover {
  position: absolute;
  z-index: 4;
  inset-block: 0;
  inset-inline-end: 0;
  display: block;
  overflow: hidden;
  border: 0;
  border-radius: 0 12px 12px 0;
  padding: 0;
  background: linear-gradient(145deg, #0d7a42 0%, #0b6b3a 45%, #08512c 100%);
  box-shadow:
    -4px 0 0 rgba(0, 0, 0, 0.12),
    0 18px 40px rgba(20, 38, 26, 0.28);
  cursor: pointer;
  inline-size: 50%;
  transform: rotateY(0deg);
  transform-origin: left center;
  transform-style: preserve-3d;
  transition: transform 0.95s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.35s ease, visibility 0.35s ease;
  backface-visibility: hidden;
}

.parapheur-book--open .parapheur-cover {
  transform: rotateY(-168deg);
  pointer-events: none;
}

.parapheur-cover__spine {
  position: absolute;
  inset-block: 0;
  inset-inline-start: 0;
  background: linear-gradient(90deg, rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.05) 70%, transparent);
  inline-size: 18px;
}

.parapheur-cover__face {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  block-size: 100%;
  color: #fff;
  gap: 0.65rem;
  padding: 1.5rem;
  text-align: center;
}

.parapheur-cover__logo-wrap {
  overflow: hidden;
  border: 2px solid rgba(252, 209, 22, 0.65);
  border-radius: 50%;
  block-size: 88px;
  inline-size: 88px;
}

.parapheur-cover__logo {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
  transform: scale(1.14);
}

.parapheur-cover__title {
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 700;
  letter-spacing: -0.02em;
}

.parapheur-cover__sub {
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.8rem;
}

.parapheur-cover__hint {
  margin-block-start: 0.75rem;
  color: #fcd116;
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.parapheur-inside {
  position: absolute;
  z-index: 1;
  inset: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  overflow: auto;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 40px rgba(20, 38, 26, 0.16);
}

.parapheur-page {
  min-block-size: 0;
  min-inline-size: 0;
}

.parapheur-page--left {
  display: flex;
  align-items: center;
  justify-content: center;
  border-inline-end: 1px solid rgba(20, 38, 26, 0.1);
  background:
    linear-gradient(90deg, rgba(20, 38, 26, 0.04), transparent 18%),
    #f7f8f5;
}

.parapheur-page--right {
  background:
    linear-gradient(270deg, rgba(20, 38, 26, 0.03), transparent 16%),
    #fff;
}

.parapheur-page__content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  block-size: 100%;
  padding: clamp(1.25rem, 3vw, 2rem) clamp(1rem, 2.5vw, 1.75rem);
}

.parapheur-page--left .parapheur-page__content {
  align-items: center;
  justify-content: center;
  gap: 0.85rem;
  max-inline-size: 20rem;
  margin-inline: auto;
  text-align: center;
}

.parapheur-mobile-brand {
  display: none;
}

.parapheur-page__logo-wrap {
  overflow: hidden;
  border-radius: 50%;
  block-size: 88px;
  inline-size: 88px;
  margin-block-end: 0.25rem;
}

.parapheur-page__logo {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
  transform: scale(1.14);
}

.parapheur-page__brand {
  margin: 0;
  color: rgb(var(--v-theme-primary));
  font-size: clamp(1.35rem, 2.5vw, 1.65rem);
  font-weight: 700;
  letter-spacing: -0.02em;
}

.parapheur-page__text {
  margin: 0;
  color: rgba(20, 38, 26, 0.72);
  font-size: 0.9rem;
  line-height: 1.5;
}

.parapheur-page__meta {
  margin: 0.35rem 0 0;
  color: rgba(20, 38, 26, 0.55);
  font-size: 0.75rem;
  line-height: 1.45;
}

.login-meta {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  column-gap: 0.75rem;
  row-gap: 0.5rem;
}

.login-meta__remember {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-inline-size: 0;
  cursor: pointer;
  font-size: 0.875rem;
  user-select: none;
}

.login-meta__check {
  flex-shrink: 0;
  border: 2px solid rgba(var(--v-theme-on-surface), 0.4);
  border-radius: 4px;
  appearance: none;
  background: #fff;
  block-size: 1.125rem;
  cursor: pointer;
  inline-size: 1.125rem;
  margin: 0;

  &:checked {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgb(var(--v-theme-primary));
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
    background-position: center;
    background-repeat: no-repeat;
    background-size: 0.85rem;
  }
}

.login-meta__forgot {
  justify-self: end;
  font-size: 0.875rem;
  text-decoration: none;
  white-space: nowrap;

  &:hover {
    text-decoration: underline;
  }
}

/* Tablette / mobile large */
@media (max-width: 960px) {
  .parapheur-auth {
    align-items: flex-start;
    padding-block: 1rem;
  }

  .parapheur-book {
    block-size: auto;
    min-block-size: 0;
    max-inline-size: 480px;
  }

  .parapheur-cover {
    inset-inline: 0;
    border-radius: 12px;
    inline-size: 100%;
    min-block-size: min(65dvh, 420px);
  }

  .parapheur-book--open .parapheur-cover {
    transform: none;
    opacity: 0;
    visibility: hidden;
  }

  .parapheur-inside {
    position: relative;
    inset: auto;
    display: block;
    overflow: visible;
    min-block-size: auto;
  }

  .parapheur-page--left {
    display: none;
  }

  .parapheur-mobile-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    margin-block-end: 1.25rem;
    text-align: center;
  }

  .parapheur-mobile-brand__logo {
    block-size: 72px;
    inline-size: 72px;
    margin-block-end: 0;
  }

  .parapheur-page--right .parapheur-page__content {
    justify-content: flex-start;
    padding-block: 1.75rem;
  }
}

/* Mobile étroit */
@media (max-width: 600px) {
  .parapheur-auth {
    padding: 0.75rem;
  }

  .parapheur-book {
    max-inline-size: 100%;
  }

  .parapheur-cover {
    min-block-size: min(58dvh, 380px);
  }

  .parapheur-cover__logo-wrap {
    block-size: 72px;
    inline-size: 72px;
  }

  .parapheur-cover__face {
    padding: 1rem;
    gap: 0.5rem;
  }

  .parapheur-inside {
    border-radius: 10px;
  }

  .parapheur-page__content {
    padding: 1.25rem 1rem 1.5rem;
  }

  .login-meta {
    grid-template-columns: 1fr;
  }

  .login-meta__forgot {
    justify-self: start;
  }
}

/* Écrans bas */
@media (max-height: 700px) and (min-width: 961px) {
  .parapheur-book {
    block-size: min(92dvh, 520px);
  }

  .parapheur-page__logo-wrap {
    block-size: 64px;
    inline-size: 64px;
  }

  .parapheur-page__content {
    padding-block: 1rem;
  }

  .parapheur-page--left .parapheur-page__content {
    gap: 0.5rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .parapheur-cover {
    transition: none;
  }

  .parapheur-book--open .parapheur-cover {
    transform: none;
    opacity: 0;
    visibility: hidden;
  }
}
</style>
