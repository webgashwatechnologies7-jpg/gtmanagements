/**
 * Logger that no-ops in production to avoid leaking info and cluttering console.
 * Use instead of console.error in catch blocks.
 */
const isDev = import.meta.env.DEV

export const logger = {
  error(message, err = null) {
    if (isDev) {
      console.error(message, err != null ? err : '')
    }
  },
  warn(message, data = null) {
    if (isDev) {
      console.warn(message, data != null ? data : '')
    }
  },
  log(...args) {
    if (isDev) {
      console.log(...args)
    }
  },
}
