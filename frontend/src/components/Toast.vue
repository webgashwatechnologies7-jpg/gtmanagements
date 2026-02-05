<template>
  <Transition name="toast">
    <div v-if="message" :class="['toast', type]" role="alert">
      {{ message }}
    </div>
  </Transition>
</template>

<script setup>
import { useToast } from '../stores/toast'

const { message, type } = useToast()
</script>

<style scoped>
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  max-width: 360px;
  padding: 14px 20px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  font-size: 0.95rem;
}
.toast.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
.toast.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
.toast.info {
  background: #d1ecf1;
  color: #0c5460;
  border: 1px solid #bee5eb;
}
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>
