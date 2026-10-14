<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import MailReferentialPanel from '@/components/courrier/MailReferentialPanel.vue'
import ParapheurPageHeader from '@/components/parapheur/ParapheurPageHeader.vue'
import { $api } from '@/utils/api'

definePage({
  meta: {
    action: 'manage',
    subject: 'MailSettings',
  },
})

interface LetterheadProfile {
  ministry_line: string | null
  header_title: string | null
  append_structure_name: boolean
  footer_text: string | null
  address: string | null
  phone: string | null
  email: string | null
  republic_line: string | null
  motto: string | null
  signatory_left_label: string | null
  signatory_right_label: string | null
}

interface StructureTypeItem {
  id: number
  code: string
  name: string
  document_profile: LetterheadProfile | null
}

const activeTab = ref('letterhead')
const types = ref<StructureTypeItem[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = ref({
  ministry_line: '',
  header_title: '',
  append_structure_name: true,
  footer_text: '',
  address: '',
  phone: '',
  email: '',
  republic_line: '',
  motto: '',
  signatory_left_label: '',
  signatory_right_label: '',
})

const selected = computed(() => types.value.find(type => type.id === selectedId.value) ?? null)

const fillForm = (profile: LetterheadProfile | null) => {
  form.value = {
    ministry_line: profile?.ministry_line ?? '',
    header_title: profile?.header_title ?? '',
    append_structure_name: profile?.append_structure_name ?? true,
    footer_text: profile?.footer_text ?? '',
    address: profile?.address ?? '',
    phone: profile?.phone ?? '',
    email: profile?.email ?? '',
    republic_line: profile?.republic_line ?? '',
    motto: profile?.motto ?? '',
    signatory_left_label: profile?.signatory_left_label ?? '',
    signatory_right_label: profile?.signatory_right_label ?? '',
  }
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    types.value = await $api('/mail/admin/letterhead-profiles')
    if (!selectedId.value && types.value.length)
      selectedId.value = types.value[0].id
    fillForm(selected.value?.document_profile ?? null)
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Impossible de charger les paramètres.'
  }
  finally {
    loading.value = false
  }
}

watch(selectedId, () => {
  fillForm(selected.value?.document_profile ?? null)
  successMessage.value = ''
})

const blank = (value: string) => {
  const trimmed = value.trim()

  return trimmed === '' ? null : trimmed
}

const save = async () => {
  if (!selectedId.value)
    return

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const updated = await $api(`/mail/admin/letterhead-profiles/${selectedId.value}`, {
      method: 'PUT',
      body: {
        ministry_line: blank(form.value.ministry_line),
        header_title: blank(form.value.header_title),
        append_structure_name: form.value.append_structure_name,
        footer_text: blank(form.value.footer_text),
        address: blank(form.value.address),
        phone: blank(form.value.phone),
        email: blank(form.value.email),
        republic_line: blank(form.value.republic_line),
        motto: blank(form.value.motto),
        signatory_left_label: blank(form.value.signatory_left_label),
        signatory_right_label: blank(form.value.signatory_right_label),
      },
    })
    const index = types.value.findIndex(type => type.id === updated.id)
    if (index >= 0)
      types.value[index] = updated
    successMessage.value = 'En-tête et signataires enregistrés pour ce type de structure.'
  }
  catch (e: any) {
    errorMessage.value = e?.data?.message || 'Enregistrement impossible.'
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <ParapheurPageHeader
      title="Paramètres du courrier"
      subtitle="En-tête et signataires appliqués à chaque type de structure."
      icon="tabler-mail-cog"
    />

    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ errorMessage }}
    </VAlert>
    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      {{ successMessage }}
    </VAlert>

    <VTabs
      v-model="activeTab"
      class="mb-4"
    >
      <VTab value="letterhead">
        En-tête
      </VTab>
      <VTab value="categories">
        Catégories
      </VTab>
      <VTab value="priorities">
        Priorités
      </VTab>
      <VTab value="channels">
        Canaux
      </VTab>
      <VTab value="qualifications">
        Qualifications
      </VTab>
      <VTab value="actions">
        Actions
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <VWindowItem value="letterhead">
    <VCard :loading="loading">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              v-model="selectedId"
              :items="types"
              item-title="name"
              item-value="id"
              label="Type de structure"
              :disabled="!types.length"
            />
          </VCol>
        </VRow>

        <p class="text-body-2 text-medium-emphasis mb-6">
          Ces informations sont imprimées sur les fiches du courrier. Si le titre d’en-tête est vide, le nom de chaque structure est utilisé. Une nouvelle impression reprend ces signataires tant que le courrier n’est pas expédié. Après l’expédition, l’en-tête reste celui figé à ce moment-là.
        </p>

        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.ministry_line"
              label="Ligne du ministère"
              placeholder="MINISTÈRE DE L'ÉCONOMIE ET DES FINANCES."
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.header_title"
              label="Titre d’en-tête"
              placeholder="Laisser vide pour le nom de la structure"
            />
          </VCol>
          <VCol cols="12">
            <VCheckbox
              v-model="form.append_structure_name"
              label="Ajouter le nom de la structure sous le titre"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.signatory_left_label"
              label="Signataire gauche"
              placeholder="Le Payeur"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.signatory_right_label"
              label="Signataire droit"
              placeholder="Le Fondé de pouvoir"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.address"
              label="Adresse"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.phone"
              label="Téléphone"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.email"
              label="E-mail"
              type="email"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.republic_line"
              label="République"
              placeholder="République du Mali"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.motto"
              label="Devise"
              placeholder="Un Peuple – Un But – Une Foi"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="form.footer_text"
              label="Pied de page"
              rows="2"
              auto-grow
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="px-4 pb-4">
        <VSpacer />
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="!selectedId"
          @click="save"
        >
          Enregistrer
        </VBtn>
      </VCardActions>
    </VCard>
      </VWindowItem>

      <VWindowItem value="categories">
        <VCard>
          <VCardText>
            <MailReferentialPanel
              resource="categories"
              title="Catégories"
              hint="Types de courrier proposés à l’enregistrement : administratif, financier, juridique…"
            />
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="priorities">
        <VCard>
          <VCardText>
            <MailReferentialPanel
              resource="priorities"
              title="Priorités"
              hint="Niveaux proposés sur le courrier. Une priorité utilisée ne peut pas être supprimée : désactivez-la."
            />
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="channels">
        <VCard>
          <VCardText>
            <MailReferentialPanel
              resource="channels"
              title="Canaux"
              hint="Modes d’arrivée ou de départ : courrier, email, remise en main propre…"
            />
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="qualifications">
        <VCard>
          <VCardText>
            <MailReferentialPanel
              resource="qualifications"
              title="Qualifications"
              hint="Mentions de traitement associées au courrier."
            />
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="actions">
        <VCard>
          <VCardText>
            <MailReferentialPanel
              resource="actions"
              title="Actions d’imputation"
              hint="Cases proposées sur la fiche : pour attribution, pour avis, pour suivi…"
            />
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>
  </div>
</template>
