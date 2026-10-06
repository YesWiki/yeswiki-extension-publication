ywInitEach('.pdf-handler-container', (container) => {
  const WAITING = 0
  const RUNNING = 1
  const DONE = 2
  const FAILED = 3
  const SILENT = 'silent'

  const parse = (value, fallback) => {
    try {
      const parsed = JSON.parse(value)
      return parsed !== null && typeof parsed === 'object' ? parsed : fallback
    } catch (error) {
      return fallback
    }
  }

  const isAdmin = container.dataset.isAdmin === 'true'
  const urls = parse(container.dataset.urls, { local: '', external: '' })
  const translations = parse(container.dataset.translations, {})
  const { sourceUrl = '', hash = '', pageTag = '', helpUrl = '' } = container.dataset
  const refresh = container.dataset.refresh === 'true'
  const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`
  const message = container.querySelector('.pdf-message')
  const actionButton = container.querySelector('.pdf-action')
  const downloadLink = container.querySelector('.pdf-download')
  let finished = false
  let statusTimer = null
  let buttonAction = null

  const t = (key, replacements = {}) => {
    let text = translations[key] ?? key
    for (const [name, value] of Object.entries(replacements)) {
      text = text.split(`{${name}}`).join(value)
    }
    return text
  }

  const step = (name, state) => {
    const element = container.querySelector(`[data-step="${name}"]`)
    if (element) element.dataset.state = String(state)
  }

  const stateOf = (name) => Number(container.querySelector(`[data-step="${name}"]`)?.dataset.state ?? WAITING)

  const say = (html, type = 'info') => {
    message.className = `pdf-message yw-alert yw-alert--${type}`
    message.innerHTML = html
    message.hidden = html === ''
  }

  const offer = (label, action, type = 'info') => {
    actionButton.className = `yw-btn yw-btn--${type} pdf-action`
    actionButton.textContent = label
    actionButton.hidden = false
    buttonAction = action
  }

  const helpLink = () => `<a href="${helpUrl}" target="_blank" rel="noopener">${helpUrl}</a>`

  const withParameters = (serviceUrl) => {
    const query = new URLSearchParams({ urlPageTag: pageTag, url: sourceUrl, hash, uuid, forceNewFormat: '1' })
    if (refresh) query.set('refresh', '1')
    return serviceUrl + (serviceUrl.includes('?') ? '&' : '?') + query.toString()
  }

  const openWindow = (url) => {
    if (window.open(url) === null) {
      say(`${message.innerHTML}<br><br><strong>${t('popuptovalidate')}</strong>`, 'warning')
    }
  }

  const printViaPreview = () => openWindow(sourceUrl + (sourceUrl.includes('?') ? '&' : '?') + 'browserPrintAfterRendered=1')

  const follow = (status) => {
    const progress = (name, value, expected) => {
      if (stateOf(name) === DONE) return
      if (value === expected) step(name, DONE)
      else if (value === 0) step(name, FAILED)
      else step(name, RUNNING)
    }
    progress('browserLoaded', status[3], 1)
    progress('pageLoadedByBrowser', status[4], 7)
    progress('creatingPdf', status[5], 1)
  }

  const pollStatus = (statusUrl) => {
    statusTimer = setTimeout(async () => {
      try {
        const response = await fetch(statusUrl)
        if (response.ok) {
          const status = await response.json()
          if (status && Number(status[0]) > 0) {
            step('contactService', DONE)
            follow(status)
          }
        }
      } catch {
        statusTimer = null
      }
      if (!finished) pollStatus(statusUrl)
    }, 400)
  }

  const failure = async (response, serviceUrl) => {
    const type = response.headers.get('Content-Type') || ''
    if (type.startsWith('application/json')) {
      const body = await response.json().catch(() => ({}))
      const cause = (body && body.cause) || {}
      if (cause.canExecChromium === false) throw new Error(t('errorforexternalwithoutchromium', { url: serviceUrl, helpLink: helpLink() }))
      if (cause.domainAuthorized === false) throw new Error(t('errorforexternaleurldomainnotauthorized', { extUrl: serviceUrl, helpLink: helpLink() }))
      if (cause.pdfCreationError === true) {
        if (isAdmin) console.log({ htmlDuringError: cause.pdfCreationErrorHTML || '' })
        throw new Error(t('errorforexternalwhilecreatingpdf', { url: serviceUrl, error: cause.pdfCreationErrorMessage || 'unknown' }))
      }
    }
    if (type.startsWith('text/html')) {
      const html = await response.text()
      if (html.includes('htmltopdf_path') && html.includes('htmltopdf_service_url')) {
        throw new Error(t('errorforexternaleurlnotconfigured', { extUrl: serviceUrl, helpLink: helpLink() }))
      }
    }
    throw new Error(`${response.status} ${response.statusText}`)
  }

  const getPdf = async () => {
    const serviceUrl = urls.local || urls.external
    const url = withParameters(serviceUrl)
    step('contactService', RUNNING)
    say(t('creatingpdf'))
    if (isAdmin) pollStatus(serviceUrl.replace(/api\/pdf\/getPdf.*$/, `api/pdf/getStatus/${uuid}`))

    let response
    try {
      response = await fetch(url, { credentials: urls.local ? 'same-origin' : 'omit' })
    } catch (error) {
      if (!urls.local && urls.external) {
        offer(t('opendefaultlink'), () => { window.location = url }, 'primary')
        if (isAdmin) {
          say(t('errorforexternaleurlcheck', { extUrl: serviceUrl, helpLink: helpLink(), error: String(error) }), 'warning')
        } else {
          say(t('errorreloading'))
          window.location = url
        }
        throw new Error(SILENT)
      }
      throw error
    }
    if (!response.ok) await failure(response, serviceUrl)
    const type = response.headers.get('Content-Type') || ''
    if (!/^(application\/(octet-stream|download|pdf|force-download))/.test(type)) await failure(response, serviceUrl)

    step('contactService', DONE)
    step('getPdf', RUNNING)
    const blob = await response.blob()
    step('getPdf', DONE)
    follow([1, 1, 1, 1, 7, 1])
    return blob
  }

  actionButton.addEventListener('click', (event) => {
    event.preventDefault()
    if (typeof buttonAction === 'function') buttonAction()
  })

  offer(t('preview'), () => openWindow(sourceUrl))

  step('getPdfServiceUrl', DONE)
  if (!urls.local && !urls.external) {
    step('checkUrls', FAILED)
    say(t('errorforurls', { link: helpLink() }), 'danger')
    return
  }
  step('checkUrls', DONE)

  getPdf()
    .then((blob) => {
      say('')
      const fileName = `${pageTag}-${hash}.pdf`
      downloadLink.href = URL.createObjectURL(new File([blob], fileName, { type: 'application/pdf' }))
      downloadLink.download = fileName
      downloadLink.hidden = false
      downloadLink.click()
    })
    .catch((error) => {
      if (error.message === SILENT) return
      for (const name of ['contactService', 'getPdf', 'browserLoaded', 'pageLoadedByBrowser', 'creatingPdf']) {
        if (stateOf(name) === RUNNING) step(name, FAILED)
      }
      if (isAdmin) {
        say(t('errorforadmin', { error: error.message }), 'danger')
      } else {
        say(t('errorforuser'))
        setTimeout(printViaPreview, 2000)
      }
      offer(t('printviapreview'), printViaPreview)
    })
    .finally(() => {
      finished = true
      clearTimeout(statusTimer)
    })
})
