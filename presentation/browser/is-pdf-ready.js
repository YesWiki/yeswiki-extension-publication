/**
 * Resolves when the publication is ready to be printed
 */
async function __is_yw_publication_ready () {
  const bodyElement = document.querySelector('body')
  const isReady = (el) => el.dataset.publication === 'ready'

  if (isReady(bodyElement)) {
    return 'via-body'
  }

  const observer = await new Promise((resolve) => {
    const observer = new MutationObserver((mutationList, observer) => {
      const ok = mutationList.some(mutation => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'data-publication' && isReady(mutation.target)) {
          resolve(observer)
          return true
        }
      })
    })

    observer.observe(bodyElement, { attributes: true });
  })

  observer.disconnect()

  return 'via-observer'
}
