document.addEventListener('click', (event) => {
  const button = event.target.closest('a.entries2publication-action')
  if (!button) return

  const url = new URL(button.href, window.location.href)
  for (const key of [...url.searchParams.keys()]) {
    if (key.startsWith('facet')) url.searchParams.delete(key)
  }
  new URLSearchParams(window.location.search).forEach((value, key) => {
    if (key.startsWith('facet')) url.searchParams.append(key, value)
  })
  button.href = url.toString()

  if (typeof toastMessage === 'function' && button.dataset.message) {
    toastMessage(button.dataset.message, 7000, 'yw-alert yw-alert--info')
  }
})
