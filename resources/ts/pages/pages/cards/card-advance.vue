<script setup lang="ts">
import CardAdvanceActiveProject from '@/views/pages/cards/card-advance/CardAdvanceActiveProject.vue';
import CardAdvanceActivityTimeline from '@/views/pages/cards/card-advance/CardAdvanceActivityTimeline.vue';
import CardAdvanceAssignmentProgress from '@/views/pages/cards/card-advance/CardAdvanceAssignmentProgress.vue';
import CardAdvanceBrowserStates from '@/views/pages/cards/card-advance/CardAdvanceBrowserStates.vue';
import CardAdvanceCongratulationsJohn from '@/views/pages/cards/card-advance/CardAdvanceCongratulationsJohn.vue';
import CardAdvanceDeliveryPerformance from '@/views/pages/cards/card-advance/CardAdvanceDeliveryPerformance.vue';
import CardAdvanceEarningReports from '@/views/pages/cards/card-advance/CardAdvanceEarningReports.vue';
import CardAdvanceLastTransaction from '@/views/pages/cards/card-advance/CardAdvanceLastTransaction.vue';
import CardAdvanceMonthlyCampaignState from '@/views/pages/cards/card-advance/CardAdvanceMonthlyCampaignState.vue';
import CardAdvanceOrder from '@/views/pages/cards/card-advance/CardAdvanceOrder.vue';
import CardAdvancePopularInstructor from '@/views/pages/cards/card-advance/CardAdvancePopularInstructor.vue';
import CardAdvancePopularProducts from '@/views/pages/cards/card-advance/CardAdvancePopularProducts.vue';
import CardAdvanceSalesByCountries from '@/views/pages/cards/card-advance/CardAdvanceSalesByCountries.vue';
import CardAdvanceSourceVisits from '@/views/pages/cards/card-advance/CardAdvanceSourceVisits.vue';
import CardAdvanceTopCourses from '@/views/pages/cards/card-advance/CardAdvanceTopCourses.vue';
import CardAdvanceTransactions from '@/views/pages/cards/card-advance/CardAdvanceTransactions.vue';
import CardAdvanceUpcomingWebinar from '@/views/pages/cards/card-advance/CardAdvanceUpcomingWebinar.vue';
import CardAdvanceVehicleCondition from '@/views/pages/cards/card-advance/CardAdvanceVehicleCondition.vue';
import CardAdvanceWebsiteAnalytics from '@/views/pages/cards/card-advance/CardAdvanceWebsiteAnalytics.vue';
</script>

<template>
  <VRow>
    <!-- 👉 Campaign State -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceMonthlyCampaignState />
    </VCol>

    <!-- 👉 Active Project -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceActiveProject />
    </VCol>

    <!-- 👉 Source Visits -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceSourceVisits />
    </VCol>

    <!-- 👉 Sales by countries -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceSalesByCountries />
    </VCol>

    <!-- 👉 Earning Reports -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceEarningReports />
    </VCol>

    <!-- 👉 Browser States -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceBrowserStates />
    </VCol>

    <!-- 👉 Orders -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceOrder />
    </VCol>

    <!-- 👉 Transactions -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceTransactions />
    </VCol>

    <!-- 👉 Popular Products -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvancePopularProducts />
    </VCol>

    <!-- 👉 Top Courses -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceTopCourses />
    </VCol>

    <!-- 👉 Upcoming Webinar -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceUpcomingWebinar />
    </VCol>

    <!-- 👉 Assignment Progress -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceAssignmentProgress />
    </VCol>

    <!-- 👉 Delivery Performance -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceDeliveryPerformance />
    </VCol>

    <!-- 👉 Vehicle Condition -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvanceVehicleCondition />
    </VCol>

    <!-- 👉 Popular Instructor -->
    <VCol cols="12" sm="6" lg="4">
      <CardAdvancePopularInstructor />
    </VCol>

    <!-- 👉 Last Transaction -->
    <VCol cols="12" sm="6">
      <CardAdvanceLastTransaction />
    </VCol>

    <!-- 👉 Activity Timeline -->
    <VCol cols="12" md="6">
      <CardAdvanceActivityTimeline />
    </VCol>

    <!-- 👉 Websites Analytics -->
    <VCol cols="12" md="6">
      <CardAdvanceWebsiteAnalytics />
    </VCol>

    <!-- 👉 Congratulations John -->
    <VCol cols="12" md="6">
      <CardAdvanceCongratulationsJohn />
    </VCol>
  </VRow>
</template>
