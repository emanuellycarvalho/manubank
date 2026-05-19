<script setup>
defineProps({
  title:       { type: String, required: true },
  message:     { type: String, default: '' },
  detail:      { type: String, default: '' },
  confirmLabel:{ type: String, default: 'Confirmar' },
  cancelLabel: { type: String, default: 'Cancelar' },
  loading:     { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'close'])

function onOverlay(e) {
  if (e.target === e.currentTarget) emit('close')
}
</script>

<template>
  <Transition name="modal" appear>
    <div class="overlay" @click="onOverlay" role="dialog" aria-modal="true" :aria-label="title">
      <div class="modal">
        <header class="modal-header">
          <h3 class="modal-title">
            <unicon name="exclamation-triangle" width="20" height="20" />
            {{ title }}
          </h3>
          <button
            type="button"
            class="btn-close"
            aria-label="Fechar"
            :disabled="loading"
            @click="emit('close')"
          >
            <unicon name="times" width="18" height="18" />
          </button>
        </header>

        <div class="modal-body">
          <p v-if="message" class="modal-message">{{ message }}</p>
          <p v-if="detail" class="modal-detail">{{ detail }}</p>
        </div>

        <footer class="modal-footer">
          <button
            type="button"
            class="btn btn--muted"
            :disabled="loading"
            @click="emit('close')"
          >
            {{ cancelLabel }}
          </button>
          <button
            type="button"
            class="btn btn--danger-solid"
            :disabled="loading"
            @click="emit('confirm')"
          >
            <span v-if="loading" class="spinner-ui spinner-ui--sm" aria-hidden="true"></span>
            {{ confirmLabel }}
          </button>
        </footer>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 600;
  padding: 20px;
  backdrop-filter: blur(3px);
}

.modal {
  background: var(--color-bg-secondary);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px 12px;
  border-bottom: 1px solid var(--color-border-subtle-dark);
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.btn-close {
  background: var(--color-bg-elevated);
  border: none;
  color: var(--color-text);
  cursor: pointer;
  padding: 6px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  transition: filter 0.15s;
}

.btn-close:hover:not(:disabled) { filter: brightness(1.12); }
.btn-close:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-body {
  padding: 16px 22px 20px;
}

.modal-message {
  font-size: 0.92rem;
  color: var(--color-text-muted);
  margin: 0 0 12px;
  line-height: 1.5;
}

.modal-detail {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
  padding: 12px 14px;
  background: var(--color-bg-elevated);
  border-radius: var(--radius-sm);
  line-height: 1.45;
  word-break: break-word;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px 18px;
  border-top: 1px solid var(--color-border-subtle-dark);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  font-size: 0.88rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: filter 0.15s;
}

.btn:hover:not(:disabled) { filter: brightness(1.08); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.btn--muted {
  background: var(--color-bg-elevated);
  color: var(--color-text);
}

.btn--danger-solid {
  background: var(--color-error);
  color: var(--color-text);
}

.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-active .modal,
.modal-leave-active .modal { transition: transform 0.2s ease, opacity 0.2s ease; }
.modal-enter-from,
.modal-leave-to { opacity: 0; }
.modal-enter-from .modal,
.modal-leave-to .modal { transform: scale(0.96) translateY(8px); opacity: 0; }
</style>
