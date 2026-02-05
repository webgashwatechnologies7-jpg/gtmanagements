import { ref } from 'vue'

const message = ref('')
const type = ref('error') // 'error' | 'success' | 'info'
let timeoutId = null

export function useToast() {
  function show(msg, msgType = 'error') {
    if (timeoutId) clearTimeout(timeoutId)
    message.value = msg
    type.value = msgType
    timeoutId = setTimeout(() => {
      message.value = ''
      timeoutId = null
    }, 4000)
  }

  function showError(msg) {
    show(msg, 'error')
  }

  function showSuccess(msg) {
    show(msg, 'success')
  }

  return {
    message,
    type,
    show,
    showError,
    showSuccess,
  }
}
