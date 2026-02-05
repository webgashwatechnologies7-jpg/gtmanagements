import { ref } from 'vue'

const visible = ref(false)
const title = ref('Confirm')
const message = ref('')
let resolveFn = null

export function useConfirm() {
  function confirm(confirmTitle = 'Confirm', confirmMessage = 'Are you sure?') {
    title.value = confirmTitle
    message.value = confirmMessage
    visible.value = true
    return new Promise((resolve) => {
      resolveFn = resolve
    })
  }

  function onConfirm() {
    visible.value = false
    if (resolveFn) resolveFn(true)
    resolveFn = null
  }

  function onCancel() {
    visible.value = false
    if (resolveFn) resolveFn(false)
    resolveFn = null
  }

  return {
    visible,
    title,
    message,
    confirm,
    onConfirm,
    onCancel,
  }
}
