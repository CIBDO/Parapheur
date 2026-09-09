<script setup lang="ts">
import type { PropertyFeatures } from './types';

const props = defineProps<{
  formData: PropertyFeatures;
}>();

const emit = defineEmits<{
  (e: 'update:formData', value: PropertyFeatures): void;
}>();

const formData = ref<PropertyFeatures>(props.formData);

watch(formData, () => {
  emit('update:formData', formData.value);
});
</script>

<template>
  <VForm>
    <VRow>
      <VCol cols="12" sm="6">
        <!-- 👉 Bedrooms -->
        <AppTextField v-model="formData.bedroomCount" label="Bedrooms" placeholder="3" type="number" />
      </VCol>
      <VCol cols="12" sm="6">
        <!-- 👉 Floor No -->
        <AppTextField v-model="formData.floorNo" label="Floor No" placeholder="12" type="number" />
      </VCol>
      <VCol cols="12" sm="6">
        <!-- 👉 Bathrooms -->
        <AppTextField v-model="formData.bathroomCount" label="Bathroom" placeholder="4" type="number" />
      </VCol>
      <VCol cols="12" sm="6">
        <!-- 👉 Furnished Status -->
        <AppSelect
          v-model="formData.furnishedStatus"
          label="Furnished Status"
          placeholder="Select Furnished Status"
          :items="['Fully Furnished', 'Furnished', 'Semi-Furnished', 'Unfurnished']"
        />
      </VCol>
      <VCol cols="12">
        <!-- 👉 Furnishing Details -->
        <AppSelect
          v-model="formData.furnishingDetails"
          label="Furnishing Details"
          placeholder="Select Furnishing Details"
          multiple
          chips
          closable-chips
          :items="['TV', 'AC', 'RO', 'Bed', 'Fridge', 'Wifi', 'Sofa', 'Cupboard', 'Microwave', 'Dining Table', 'Washing Machine']"
        />
      </VCol>
      <VCol cols="12" sm="6">
        <!-- 👉 xCommon Area? -->
        <VRadioGroup v-model="formData.isCommonArea1">
          <template #label>
            <div class="text-body-1">Is There Any Common Area?</div>
          </template>
          <VRadio label="Yes" value="true" />
          <VRadio label="No" value="false" />
        </VRadioGroup>
      </VCol>
      <VCol cols="12" sm="6">
        <!-- 👉 Common Area? -->
        <VRadioGroup v-model="formData.isCommonArea2">
          <template #label>
            <div class="text-body-1">Is There Any Common Area?</div>
          </template>
          <VRadio label="Yes" value="true" />
          <VRadio label="No" value="false" />
        </VRadioGroup>
      </VCol>
    </VRow>
  </VForm>
</template>
