<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Auth',
  },
})

interface ProfileForm {
  first_name: string
  last_name: string
  title: string | null
  phone: string
  email: string
  position_title: string
}

const ability = useAbility()
const userData = useCookie<any>('userData')

const profileFormRef = ref<VForm>()
const passwordFormRef = ref<VForm>()

const loading = ref(true)
const savingProfile = ref(false)
const savingPassword = ref(false)

const profileSuccess = ref('')
const profileError = ref('')
const passwordSuccess = ref('')
const passwordError = ref('')

const profileErrors = ref<Record<string, string | undefined>>({})
const passwordErrors = ref<Record<string, string | undefined>>({})

const isCurrentVisible = ref(false)
const isPasswordVisible = ref(false)
const isConfirmVisible = ref(false)

const civilityOptions = [
  { title: 'M.', value: 'M.' },
  { title: 'Mme', value: 'Mme' },
  { title: 'Mlle', value: 'Mlle' },
  { title: 'Dr', value: 'Dr' },
  { title: 'Pr', value: 'Pr' },
  { title: 'Me', value: 'Me' },
]

const profile = ref<ProfileForm>({
  first_name: '',
  last_name: '',
  title: null,
  phone: '',
  email: '',
  position_title: '',
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const structureLabel = computed(() => {
  const structure = userData.value?.structure
  if (!structure)
    return '—'

  return structure.code ? `${structure.code} — ${structure.name}` : structure.name
})

const syncProfileFromUser = (data: Record<string, any>) => {
  profile.value = {
    first_name: data.firstName || '',
    last_name: data.lastName || '',
    title: data.title || null,
    phone: data.phone || '',
    email: data.email || '',
    position_title: data.position_title || '',
  }
}

const applyUserData = (data: Record<string, any>, abilityRules?: any[]) => {
  userData.value = data
  if (abilityRules)
    ability.update(abilityRules)
  syncProfileFromUser(data)
}

const loadProfile = async () => {
  loading.value = true
  profileError.value = ''

  try {
    const res = await $api('/auth/me')
    if (res?.userData)
      applyUserData(res.userData, res.userAbilityRules)
    else if (userData.value)
      syncProfileFromUser(userData.value)
  }
  catch {
    if (userData.value)
      syncProfileFromUser(userData.value)
    else
      profileError.value = 'Impossible de charger le profil.'
  }
  finally {
    loading.value = false
  }
}

const firstError = (payload: Record<string, unknown>, key: string) => {
  const value = payload[key]
  if (Array.isArray(value))
    return String(value[0] || '')
  if (typeof value === 'string')
    return value

  return undefined
}

const saveProfile = async () => {
  const { valid } = await profileFormRef.value!.validate()
  if (!valid)
    return

  savingProfile.value = true
  profileSuccess.value = ''
  profileError.value = ''
  profileErrors.value = {}

  try {
    const res = await $api('/auth/profile', {
      method: 'PUT',
      body: {
        first_name: profile.value.first_name.trim(),
        last_name: profile.value.last_name.trim(),
        title: profile.value.title || null,
        phone: profile.value.phone.trim() || null,
        email: profile.value.email.trim(),
        position_title: profile.value.position_title.trim() || null,
      },
      onResponseError({ response }) {
        const payload = response._data?.errors || {}
        profileErrors.value = {
          first_name: firstError(payload, 'first_name'),
          last_name: firstError(payload, 'last_name'),
          title: firstError(payload, 'title'),
          phone: firstError(payload, 'phone'),
          email: firstError(payload, 'email'),
          position_title: firstError(payload, 'position_title'),
        }
        profileError.value = response._data?.message || 'Impossible d’enregistrer le profil.'
      },
    })

    if (res?.userData)
      applyUserData(res.userData, res.userAbilityRules)

    profileSuccess.value = res?.message || 'Profil mis à jour.'
  }
  catch {
    if (!profileError.value)
      profileError.value = 'Impossible d’enregistrer le profil.'
  }
  finally {
    savingProfile.value = false
  }
}

const savePassword = async () => {
  const { valid } = await passwordFormRef.value!.validate()
  if (!valid)
    return

  savingPassword.value = true
  passwordSuccess.value = ''
  passwordError.value = ''
  passwordErrors.value = {}

  try {
    const res = await $api('/auth/change-password', {
      method: 'POST',
      body: {
        current_password: passwordForm.value.current_password,
        password: passwordForm.value.password,
        password_confirmation: passwordForm.value.password_confirmation,
      },
      onResponseError({ response }) {
        const payload = response._data?.errors || {}
        passwordErrors.value = {
          current_password: firstError(payload, 'current_password'),
          password: firstError(payload, 'password'),
        }
        passwordError.value = response._data?.message || 'Impossible de changer le mot de passe.'
      },
    })

    if (res?.userData)
      applyUserData(res.userData, res.userAbilityRules)

    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    }
    passwordFormRef.value?.resetValidation()
    passwordSuccess.value = res?.message || 'Mot de passe mis à jour.'
  }
  catch {
    if (!passwordError.value)
      passwordError.value = 'Impossible de changer le mot de passe.'
  }
  finally {
    savingPassword.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Mon profil"
      subtitle="Consultez et modifiez vos informations personnelles."
      icon="tabler-user-circle"
    />

    <VRow>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardText class="d-flex flex-column align-center text-center gap-3 py-8">
            <VAvatar
              size="88"
              color="primary"
              variant="tonal"
            >
              <VImg
                v-if="userData?.avatar"
                :src="userData.avatar"
              />
              <VIcon
                v-else
                icon="tabler-user"
                size="42"
              />
            </VAvatar>

            <div>
              <h5 class="text-h5 mb-1">
                {{ userData?.fullName || '—' }}
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0 text-capitalize">
                {{ userData?.role || '—' }}
              </p>
            </div>

            <VChip
              v-if="userData?.structure"
              size="small"
              color="primary"
              variant="tonal"
              label
            >
              {{ structureLabel }}
            </VChip>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="8"
      >
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle>Informations personnelles</VCardTitle>
            <VCardSubtitle>Ces informations apparaissent dans le parapheur et les notifications.</VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VAlert
              v-if="profileSuccess"
              type="success"
              variant="tonal"
              class="mb-4"
              closable
              @click:close="profileSuccess = ''"
            >
              {{ profileSuccess }}
            </VAlert>

            <VAlert
              v-if="profileError"
              type="error"
              variant="tonal"
              class="mb-4"
              closable
              @click:close="profileError = ''"
            >
              {{ profileError }}
            </VAlert>

            <VSkeletonLoader
              v-if="loading"
              type="article"
            />

            <VForm
              v-else
              ref="profileFormRef"
              @submit.prevent="saveProfile"
            >
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="profile.title"
                    :items="civilityOptions"
                    label="Civilité"
                    clearable
                    :error-messages="profileErrors.title"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="profile.first_name"
                    label="Prénom"
                    :rules="[requiredValidator]"
                    :error-messages="profileErrors.first_name"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="profile.last_name"
                    label="Nom"
                    :rules="[requiredValidator]"
                    :error-messages="profileErrors.last_name"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="profile.email"
                    label="E-mail"
                    type="email"
                    :rules="[requiredValidator, emailValidator]"
                    :error-messages="profileErrors.email"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="profile.phone"
                    label="Téléphone"
                    :error-messages="profileErrors.phone"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    v-model="profile.position_title"
                    label="Fonction"
                    :error-messages="profileErrors.position_title"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    :model-value="structureLabel"
                    label="Structure"
                    readonly
                    hint="La structure est gérée par l’administrateur."
                    persistent-hint
                  />
                </VCol>
              </VRow>

              <div class="d-flex justify-end mt-4">
                <VBtn
                  type="submit"
                  color="primary"
                  :loading="savingProfile"
                >
                  Enregistrer
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>

        <VCard>
          <VCardItem>
            <VCardTitle>Sécurité</VCardTitle>
            <VCardSubtitle>Modifiez votre mot de passe de connexion.</VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VAlert
              v-if="passwordSuccess"
              type="success"
              variant="tonal"
              class="mb-4"
              closable
              @click:close="passwordSuccess = ''"
            >
              {{ passwordSuccess }}
            </VAlert>

            <VAlert
              v-if="passwordError"
              type="error"
              variant="tonal"
              class="mb-4"
              closable
              @click:close="passwordError = ''"
            >
              {{ passwordError }}
            </VAlert>

            <VForm
              ref="passwordFormRef"
              @submit.prevent="savePassword"
            >
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="passwordForm.current_password"
                    label="Mot de passe actuel"
                    :type="isCurrentVisible ? 'text' : 'password'"
                    autocomplete="current-password"
                    :rules="[requiredValidator]"
                    :error-messages="passwordErrors.current_password"
                    :append-inner-icon="isCurrentVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isCurrentVisible = !isCurrentVisible"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="passwordForm.password"
                    label="Nouveau mot de passe"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    autocomplete="new-password"
                    :rules="[requiredValidator, passwordValidator]"
                    :error-messages="passwordErrors.password"
                    :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="passwordForm.password_confirmation"
                    label="Confirmer le mot de passe"
                    :type="isConfirmVisible ? 'text' : 'password'"
                    autocomplete="new-password"
                    :rules="[requiredValidator, confirmedValidator(passwordForm.password_confirmation, passwordForm.password)]"
                    :append-inner-icon="isConfirmVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isConfirmVisible = !isConfirmVisible"
                  />
                </VCol>
              </VRow>

              <div class="d-flex justify-end mt-4">
                <VBtn
                  type="submit"
                  color="primary"
                  variant="tonal"
                  :loading="savingPassword"
                >
                  Changer le mot de passe
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
