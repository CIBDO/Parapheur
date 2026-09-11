<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import logoDgtcp from '@images/logo-dgtcp.png'

definePage({
  meta: {
    layout: 'blank',
  },
})

const router = useRouter()
const ability = useAbility()

const isSubmitting = ref(false)
const isPasswordVisible = ref(false)
const isConfirmVisible = ref(false)
const isCurrentVisible = ref(false)

const refVForm = ref<VForm>()
const form = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const errors = ref<Record<string, string | undefined>>({
  current_password: undefined,
  password: undefined,
})

const logoSrc = typeof logoDgtcp === 'string'
  ? logoDgtcp
  : (logoDgtcp as { default?: string })?.default || '/logo.png'

const userData = useCookie<any>('userData')

const homeForRole = (role: string) => {
  if (role === 'Administrateur')
    return '/parapheur/admin'
  if (role === 'Directeur Général' || role === 'DGA')
    return '/parapheur/dg'

  return '/parapheur'
}

const changePassword = async () => {
  isSubmitting.value = true
  errors.value = { current_password: undefined, password: undefined }

  try {
    const res = await $api('/auth/change-password', {
      method: 'POST',
      body: {
        current_password: form.value.current_password,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
      },
      onResponseError({ response }) {
        const payload = response._data?.errors || {}
        const message = response._data?.message

        errors.value = {
          current_password: Array.isArray(payload.current_password)
            ? payload.current_password[0]
            : (payload.current_password || undefined),
          password: Array.isArray(payload.password) ? payload.password[0] : payload.password,
        }

        if (!errors.value.current_password && !errors.value.password)
          errors.value.password = message || 'Impossible de changer le mot de passe.'
      },
    })

    if (res?.userData) {
      useCookie('userData').value = res.userData
      if (res.userData.userAbilityRules)
        ability.update(res.userData.userAbilityRules)
    }
    else if (userData.value) {
      useCookie('userData').value = {
        ...userData.value,
        mustChangePassword: false,
      }
    }

    const role = String(res?.userData?.role ?? userData.value?.role ?? '')
    await router.replace(homeForRole(role))
  }
  catch (err) {
    if (!errors.value.current_password && !errors.value.password)
      errors.value.password = 'Impossible de changer le mot de passe. Réessayez.'
    console.error(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      changePassword()
  })
}

const logout = async () => {
  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {
    // ignore
  }
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  ability.update([])
  await router.replace({ name: 'login' })
}
</script>

<template>
  <div class="change-password-page bg-background">
    <VCard
      max-width="440"
      class="change-password-card pa-2"
      elevation="2"
    >
      <VCardText class="pa-6">
        <div class="text-center mb-6">
          <img
            :src="logoSrc"
            alt="DGTCP"
            width="64"
            height="64"
            class="mb-3"
          >
          <h1 class="text-h5 mb-1">
            Changer votre mot de passe
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Pour votre sécurité, vous devez définir un nouveau mot de passe avant d’accéder à l’application.
          </p>
        </div>

        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <AppTextField
            v-model="form.current_password"
            label="Mot de passe temporaire"
            :type="isCurrentVisible ? 'text' : 'password'"
            autocomplete="current-password"
            class="mb-4"
            :append-inner-icon="isCurrentVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :rules="[requiredValidator]"
            :error-messages="errors.current_password"
            @click:append-inner="isCurrentVisible = !isCurrentVisible"
          />

          <AppTextField
            v-model="form.password"
            label="Nouveau mot de passe"
            :type="isPasswordVisible ? 'text' : 'password'"
            autocomplete="new-password"
            class="mb-4"
            :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :rules="[requiredValidator]"
            :error-messages="errors.password"
            @click:append-inner="isPasswordVisible = !isPasswordVisible"
          />

          <AppTextField
            v-model="form.password_confirmation"
            label="Confirmer le mot de passe"
            :type="isConfirmVisible ? 'text' : 'password'"
            autocomplete="new-password"
            class="mb-6"
            :append-inner-icon="isConfirmVisible ? 'tabler-eye-off' : 'tabler-eye'"
            :rules="[requiredValidator, (v: string) => v === form.password || 'Les mots de passe ne correspondent pas']"
            @click:append-inner="isConfirmVisible = !isConfirmVisible"
          />

          <VBtn
            block
            type="submit"
            color="primary"
            class="mb-3"
            :loading="isSubmitting"
          >
            Enregistrer et continuer
          </VBtn>

          <VBtn
            block
            variant="text"
            color="secondary"
            @click="logout"
          >
            Se déconnecter
          </VBtn>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped lang="scss">
.change-password-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100dvh;
  padding: 1.5rem;
}

.change-password-card {
  inline-size: 100%;
}
</style>
