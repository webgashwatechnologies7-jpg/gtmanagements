/**
 * Standardised API response handling.
 * Use getResponseData(res) for list/detail responses; pagination meta in res.data.meta.
 */

/**
 * Get data from API response. Handles both { data: { data: [...] } } and { data: [...] }.
 * @param {object} res - axios response (res.data.success, res.data.data)
 * @returns {any} res.data.data or res.data (for backward compatibility)
 */
export function getResponseData(res) {
  if (!res?.data) return null
  const d = res.data
  if (Object.prototype.hasOwnProperty.call(d, 'data')) return d.data
  return d
}

/**
 * Get pagination meta from response.
 * @param {object} res - axios response
 * @returns {object|null} { current_page, last_page, per_page, total } or null
 */
export function getPaginationMeta(res) {
  return res?.data?.meta ?? null
}
