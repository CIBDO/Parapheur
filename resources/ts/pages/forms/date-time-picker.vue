<script setup lang="ts">
import {
  basic,
  dateAndTime,
  disabledRange,
  humanFriendly,
  inline,
  multipleDates,
  range,
  timePicker,
} from '@/views/demos/forms/form-elements/date-time-picker/demoCodeDateTimePicker';
</script>

<template>
  <VRow>
    <!-- 👉 Basic -->
    <VCol cols="12" md="6">
      <AppCardCode title="Basic" :code="basic">
        <DemoDateTimePickerBasic />
      </AppCardCode>
    </VCol>

    <!-- 👉 Time Picker -->
    <VCol cols="12" md="6">
      <AppCardCode title="Time Picker" :code="timePicker">
        <DemoDateTimePickerTimePicker />
      </AppCardCode>
    </VCol>

    <!-- 👉 Date & TIme -->
    <VCol cols="12" md="6">
      <AppCardCode title="Date and Time" :code="dateAndTime">
        <DemoDateTimePickerDateAndTime />
      </AppCardCode>
    </VCol>

    <!-- 👉 Multiple Dates -->
    <VCol cols="12" md="6">
      <AppCardCode title="Multiple Dates" :code="multipleDates">
        <DemoDateTimePickerMultipleDates />
      </AppCardCode>
    </VCol>

    <!-- 👉 Range -->
    <VCol cols="12" md="6">
      <AppCardCode title="Range" :code="range">
        <DemoDateTimePickerRange />
      </AppCardCode>
    </VCol>

    <!-- 👉 Human Friendly -->
    <VCol cols="12" md="6">
      <AppCardCode title="Human Friendly" :code="humanFriendly">
        <DemoDateTimePickerHumanFriendly />
      </AppCardCode>
    </VCol>

    <!-- 👉 Disabled Range -->
    <VCol cols="12" md="6">
      <AppCardCode title="Disabled Range" :code="disabledRange">
        <DemoDateTimePickerDisabledRange />
      </AppCardCode>
    </VCol>

    <!-- 👉 Inline -->
    <VCol cols="12" md="6">
      <AppCardCode title="Inline" :code="inline">
        <DemoDateTimePickerInline />
      </AppCardCode>
    </VCol>
  </VRow>
</template>
