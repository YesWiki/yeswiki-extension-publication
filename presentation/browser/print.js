import {Previewer, registerHandlers, Handler} from '../../libs/vendor/pagedjs/paged.esm.js'

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

window.addEventListener('load', () => {
  new Previewer().preview().then(() => {
    document.body.dataset.publication = 'ready'
  })
})
