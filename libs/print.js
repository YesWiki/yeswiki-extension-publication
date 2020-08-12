import {Previewer, registerHandlers, Handler} from './vendor/pagedjs/paged.esm.js'

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
  done = false

  get rules () {
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
    if (!this.done && prelude.type === 'SelectorList') {
      const selectors = prelude.children.toArray().map(s => s.children.first())

      if (intersects(this.rules, selectors, this.compFn)) {
        block.children.forEach((declaration, index) => {
          block.children.remove(index)
        })

        this.done = true
      }
    }
  }
})

window.addEventListener('load', () => {
  new Previewer().preview()
})
