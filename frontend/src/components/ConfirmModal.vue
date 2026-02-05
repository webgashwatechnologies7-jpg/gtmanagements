<template>
  <Transition name="modal">
    <div v-if="visible" class="modal-overlay" @click.self="onCancel">
      <div class="modal-box confirm-modal">
        <h3 class="modal-title">{{ title }}</h3>
        <p class="modal-message">{{ message }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="onCancel">Cancel</button>
          <button type="button" class="btn-confirm" @click="onConfirm">OK</button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { useConfirm } from '../stores/confirm'

const { visible, title, message, onConfirm, onCancel } = useConfirm()
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9998;
}
.confirm-modal {
  background: #fff;
  border-radius: 10px;
  padding: 24px;
  max-width: 400px;
  width: 90%;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}
.modal-title {
  margin: 0 0 12px 0;
  font-size: 1.125rem;
  color: #333;
}
.modal-message {
  margin: 0 0 20px 0;
  font-size: 0.95rem;
  color: #555;
}
.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}
.btn-cancel {
  padding: 10px 20px;
  background: #6c757d;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.95rem;
}
.btn-confirm {
  padding: 10px 20px;
  background: #265b99;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.95rem;
}
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
