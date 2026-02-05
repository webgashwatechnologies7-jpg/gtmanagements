/**
 * Get user-friendly error message from API or generic error.
 * Use in catch blocks instead of repeating err.response?.data?.message.
 * @param {Error} err - Caught error (e.g. from axios)
 * @param {string} fallback - Default message if nothing found
 * @returns {string}
 */
export function getErrorMessage(err, fallback = 'Something went wrong. Please try again.') {
  if (!err) return fallback
  const msg = err.response?.data?.message
  if (typeof msg === 'string') return msg
  const errors = err.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors)[0]
    if (Array.isArray(first)) return first[0] || fallback
    if (typeof first === 'string') return first
  }
  return err.message && err.message !== 'Network Error' ? err.message : fallback
}
