<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import Sidebar from './components/Sidebar.vue'
import Topbar from './components/Topbar.vue'
import ToastNotification from './components/ToastNotification.vue'
import { useToast } from './composables/useToast'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const isInitialized = ref(false)
const isMobileMenuOpen = ref(false)
const isSidebarCollapsed = ref(false)
const toast = useToast()
const isAcknowledgingWarning = ref(false)
const warningMessage = computed(() => authStore.pendingWarning?.message || 'You have received a formal warning regarding your recent post. Further violations may lead to permanent account suspension.')
const hasPendingWarning = computed(() => !!authStore.pendingWarning)

// Global error handler for Vue
import { getCurrentInstance } from 'vue'
const app = getCurrentInstance()

if (app) {
  app.appContext.config.errorHandler = (err, instance, info) => {
    console.error('Vue Error:', err)
    console.error('Error Info:', info)
    toast.error('An unexpected error occurred. Please try again.', 'Error')
  }

  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled Promise Rejection:', event.reason)
    toast.error('An unexpected error occurred. Please try again.', 'Error')
    event.preventDefault()
  })
}

onMounted(async () => {
  // Initialize authentication
  await authStore.initAuth()
  isInitialized.value = true
  
  // Redirect to login if not authenticated and trying to access protected routes
  if (!authStore.isAuthenticated && route.meta.requiresAuth) {
    router.push('/login')
  }
})

watch(
  () => authStore.isAuthenticated,
  async (isAuthenticated) => {
    if (isAuthenticated) {
      await authStore.fetchPendingWarning()
    }
  },
  { immediate: true }
)

const acknowledgeWarning = async () => {
  if (!authStore.pendingWarning || isAcknowledgingWarning.value) return
  isAcknowledgingWarning.value = true
  try {
    await authStore.acknowledgeWarning(authStore.pendingWarning.id)
    toast.success('Warning acknowledged.', 'Success')
  } catch (error) {
    toast.error('Failed to acknowledge warning: ' + (error.response?.data?.message || error.message), 'Error')
  } finally {
    isAcknowledgingWarning.value = false
  }
}

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false
}

const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// Toast notifications
const { toasts, remove } = toast
</script>

<template>
  <!-- Skip to main content link for accessibility -->
  <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[9999] focus:bg-blue-600 focus:text-white focus:px-4 focus:py-2 focus:rounded">
    Skip to main content
  </a>

  <div v-if="!isInitialized" class="flex h-screen bg-blue-700 items-center justify-center" role="status" aria-live="polite">
    <div class="text-center text-white">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-4" aria-hidden="true"></div>
      <p>Loading...</p>
    </div>
  </div>
  
  <div v-else-if="authStore.isAuthenticated && !route.meta.hideNavigation" class="flex flex-col h-screen">
    <!-- Topbar -->
    <header role="banner">
      <Topbar 
        :isMobileMenuOpen="isMobileMenuOpen"
        :isSidebarCollapsed="isSidebarCollapsed"
        @toggle-mobile-menu="toggleMobileMenu"
        @toggle-sidebar="toggleSidebar"
      />
    </header>
    
    <!-- Main Layout: Sidebar + Content -->
    <div class="flex flex-1 relative overflow-hidden">
      <!-- Mobile Menu Overlay (Transparent) -->
      <div 
        v-if="isMobileMenuOpen"
        @click="closeMobileMenu"
        class="md:hidden fixed inset-0 bg-transparent z-40"
      ></div>

      <!-- Sidebar (Desktop + Mobile Slide-in) -->
      <Sidebar 
        :isMobileMenuOpen="isMobileMenuOpen" 
        :isCollapsed="isSidebarCollapsed"
        @close="closeMobileMenu" 
      />
      
      <!-- Main Content -->
      <main id="main-content" role="main" class="flex-1 bg-blue-100 w-full min-w-0 relative z-10 flex flex-col overflow-hidden" aria-label="Main content">
        <div class="flex-1 overflow-y-auto overflow-x-hidden">
          <router-view />
        </div>
      </main>
    </div>
  </div>
  
  <div v-else class="h-screen bg-blue-700">
    <router-view />
  </div>

  <!-- Toast Notifications Container -->
  <ToastNotification
    v-for="toast in toasts"
    :key="toast.id"
    v-bind="toast"
    @close="remove"
  />

  <div v-if="authStore.isAuthenticated && hasPendingWarning" class="fixed inset-0 z-[90] flex items-start md:items-center justify-center bg-black/40 backdrop-blur-[1px] px-4 pb-4 pt-20 md:pt-24 md:pl-64">
    <div class="bg-white rounded-lg shadow-xl border-2 border-black w-full max-w-md p-6 max-h-[calc(100vh-6rem)] md:max-h-[calc(100vh-8rem)] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-900">Mandatory Warning</h2>
      </div>
      <p class="text-gray-700 mb-6">{{ warningMessage }}</p>
      <div class="flex justify-end">
        <button
          @click="acknowledgeWarning"
          :disabled="isAcknowledgingWarning"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isAcknowledgingWarning ? 'Processing...' : 'I Understand' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style>
body {
  margin: 0;
}

/* Accessibility - Screen reader only class */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}

.sr-only:focus,
.sr-only:active {
  position: static;
  width: auto;
  height: auto;
  overflow: visible;
  clip: auto;
  white-space: normal;
}

.focus\:not-sr-only:focus {
  position: static !important;
  width: auto !important;
  height: auto !important;
  overflow: visible !important;
  clip: auto !important;
  white-space: normal !important;
}
</style>
