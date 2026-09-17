import {Previewer, registerHandlers, Handler} from '../../javascripts/vendor/pagedjs/paged.esm.js'

// give up on the paged layout after this, rather than never marking the page ready
const PAGINATION_TIMEOUT = 20000

/**
 * Returns true if the second array contains all the selectors of the first array
 *
 * @param {*} referenceArray
 * @param {*} matchingArray
 * @returns Boolean
 */
function intersects (referenceArray, matchingArray, compFn = (a, b) => b === a) {
  return referenceArray.every(referenceSelector => {
    return matchingArray.some(selector => compFn(referenceSelector, selector))
  })
}

/**
 * This Paged.js rule removes an aggressive Bootstrap rule which overloads every text color, decoration
 * It prevents to inherit from the YesWiki theme colors
 *
 * @todo maybe remove it when https://gitlab.pagedmedia.org/tools/pagedjs/issues/234 is solved
 * media@print CSS styles leak to media@screen when Paged rewrites CSS rules
 */
registerHandlers(class removeBootstrapStarRule extends Handler {
  get yeswikiCercoRules () {
    return [
      {
        type: 'TypeSelector',
        name: '*'
      }
    ]
  }

  get bootstrapRules () {
    return [
      {
        type: 'TypeSelector',
        name: '*'
      },
      {
        type: 'PseudoClassSelector',
        name: 'after'
      },
      {
        type: 'PseudoClassSelector',
        name: 'before'
      }
    ]
  }

  compFn (a, b) {
    return a.type === b.type && a.name === b.name
  }

  onRule ({ prelude, block }) {
    if (prelude.type === 'SelectorList') {
      const selectors = prelude.children.toArray().map(s => s.children.first())

      // we target '* { ...: ...!important; }' type of rules
      // they tend to defeat style inheritance for print
      if (
        intersects(this.yeswikiCercoRules, selectors, this.compFn) ||
        intersects(this.bootstrapRules, selectors, this.compFn)
      ) {
        block.children.forEach((declaration, index) => {
          block.children.remove(index)
        })
      }
    }
  }
})

/**
 * Moves background image to cover the entire page
 */
registerHandlers(class backgroundImageCover extends Handler {
  getParent (rootNode, condition) {
    let node = rootNode

    while (node) {
      if (condition(node)) {
        return node
      }

      node = node.parentElement
    }

    return null
  }

  afterParsed (parsed) {
    const node = {}

    const coverImage = parsed.querySelector('figure.attached_file.cover img')

    if (coverImage) {
      const figureElement = coverImage.parentElement
      const section = this.getParent(figureElement, (n) => n.classList.contains('publication-cover') || n.classList.contains('publication-start'))

      section.classList.add('has-background-image')
      section.dataset.backgroundImage = String(coverImage.src)
      figureElement.remove()
    }
  }

  renderNode (node) {
    if (node.classList && node.classList.contains('has-background-image')) {
      const page = this.getParent(node, (n) => n.classList.contains('pagedjs_page'))

      // we swap style attributes
      page.classList.add('has-background-image')
      page.style.backgroundImage = `url(${node.dataset.backgroundImage})`
    }
  }
})

/**
 * Resolves on the next animation frame, or after a second if the browser draws none
 */
function nextAnimationFrame () {
  return new Promise((resolve) => {
    const timeoutId = setTimeout(resolve, 1000)

    window.requestAnimationFrame(() => {
      clearTimeout(timeoutId)
      resolve()
    })
  })
}

/**
 * Resolves with the promise, or after `delay` ms whatever it is doing
 */
function atMost (promise, delay, onTimeout) {
  return Promise.race([
    promise,
    new Promise((resolve) => setTimeout(() => {
      onTimeout()
      resolve()
    }, delay))
  ])
}

window.addEventListener('load', async () => {
  // Paged.js waits for every image it lays out. A lazy image lands outside the
  // viewport once paginated, so the browser never loads it and the pagination
  // never finishes. Nothing is below the fold in a document meant to be printed.
  document.querySelectorAll('img[loading="lazy"]').forEach((image) => {
    image.loading = 'eager'
  })

  // wait two animation frames to be sure high resolution images are rendered after loaded
  // src: https://stackoverflow.com/questions/14578356/how-to-detect-when-an-image-has-finished-rendering-in-the-browser-i-e-painted
  await nextAnimationFrame()
  await nextAnimationFrame()

  // Whatever else keeps the layout pending, the export only waits for
  // data-publication, so never marking it would hang the whole export.
  try {
    await atMost(
      new Previewer().preview(),
      PAGINATION_TIMEOUT,
      () => console.error(`Pagination did not finish within ${PAGINATION_TIMEOUT}ms, printing the page as it is.`)
    )
  } catch (error) {
    console.error(error)
  }

  document.body.dataset.publication = 'ready'

  if (browserPrintAfterRendered === true) {
    // print page with browser
    window.print()
  }
})
