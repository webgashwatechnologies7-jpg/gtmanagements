/**
 * Sanitize HTML to prevent XSS attacks
 */
export function sanitizeHtml(html) {
  if (!html) return ''
  
  const div = document.createElement('div')
  div.textContent = html
  return div.innerHTML
}

/**
 * Sanitize user input
 */
export function sanitizeInput(input) {
  if (typeof input !== 'string') return input
  
  // Remove HTML tags
  const div = document.createElement('div')
  div.textContent = input
  return div.textContent || div.innerText || ''
}

/**
 * Escape special characters for HTML
 */
export function escapeHtml(text) {
  if (!text) return ''
  
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }
  
  return text.replace(/[&<>"']/g, m => map[m])
}
