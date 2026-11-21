<script setup lang="ts">
import { useRoute } from 'vue-router'
import AppShell from './components/layout/AppShell.vue'

const route = useRoute()
</script>

<template>
  <RouterView v-if="route.meta.public || route.meta.standalone" />
  <AppShell v-else />
</template>
