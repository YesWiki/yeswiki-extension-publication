ywInitEach('.publication-generator', (generator) => {
  const selection = generator.querySelector('#publication-selection-container')
  const available = generator.querySelector('.publication-generator__available')
  const filter = generator.querySelector('#publication-filter')
  const filterCount = generator.querySelector('#publication-filter-count')

  const show = (element, visible) => { if (element) element.hidden = !visible }

  const setSelected = (item, selected) => {
    show(item.querySelector('.movable'), selected)
    show(item.querySelector('.remove-page-item'), selected)
    show(item.querySelector('.select-page-item'), !selected)
    const input = item.querySelector('input[type="hidden"]')
    if (input) input.disabled = !selected
  }

  const select = (item) => {
    setSelected(item, true)
    item.hidden = false
    selection.append(item)
  }

  const unselect = (item) => {
    if (item.dataset.group === 'blank' || item.dataset.group === 'selected') {
      item.remove()
      return
    }
    const list = available.querySelector(`.page-groups[data-group="${item.dataset.group}"] .list-entries-to-export`)
    if (!list) {
      item.remove()
      return
    }
    setSelected(item, false)
    list.prepend(item)
    applyFilter()
  }

  const applyFilter = () => {
    if (!filter) return
    const needle = filter.value.trim().toLowerCase()
    let count = 0
    available.querySelectorAll('.publication-item').forEach((item) => {
      const visible = needle === '' || item.textContent.toLowerCase().includes(needle)
      item.hidden = !visible
      if (visible) count++
    })
    if (filterCount) filterCount.textContent = needle === '' ? '' : `${filterCount.dataset.label} : ${count}`
  }

  generator.addEventListener('click', (event) => {
    const button = event.target.closest('button')
    if (!button || !generator.contains(button)) return
    const item = button.closest('.publication-item')

    if (button.classList.contains('select-page-item') && item) {
      event.preventDefault()
      select(item)
    } else if (button.classList.contains('remove-page-item') && item) {
      event.preventDefault()
      unselect(item)
    } else if (button.classList.contains('select-all')) {
      event.preventDefault()
      button.closest('.page-groups').querySelectorAll('.publication-item:not([hidden])').forEach(select)
    } else if (button.classList.contains('page-break')) {
      event.preventDefault()
      const template = document.createElement('template')
      template.innerHTML = `<li class="yw-list-group__item publication-item blank-page" data-group="blank">
        <span class="publication-item__handle movable"></span>
        <input type="hidden" name="page[]" value="{{blankpage}}">
        <span class="page-label"></span>
        <button type="button" class="yw-btn yw-btn--sm yw-btn--danger remove-page-item"></button>
      </li>`
      const blank = template.content.firstElementChild
      blank.querySelector('.page-label').textContent = button.dataset.label
      blank.querySelector('.movable').innerHTML = available.querySelector('.movable')?.innerHTML ?? '↕'
      blank.querySelector('.remove-page-item').innerHTML = available.querySelector('.remove-page-item')?.innerHTML ?? '×'
      blank.querySelector('.remove-page-item').title = button.dataset.label
      selection.append(blank)
    }
  })

  if (filter) filter.addEventListener('input', applyFilter)

  if (typeof Sortable !== 'undefined') {
    Sortable.create(selection, { handle: '.movable', animation: 150 })
  }

  const form = generator.querySelector('.export-table-form')
  form.addEventListener('submit', () => {
    if (!form.querySelector('input[name="antispam"]')) {
      const antispam = document.createElement('input')
      antispam.type = 'hidden'
      antispam.name = 'antispam'
      antispam.value = '1'
      form.append(antispam)
    }
  })

  form.querySelectorAll('[name="publication-mode"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      form.querySelectorAll('details.publication-options').forEach((details) => {
        details.hidden = !details.classList.contains(`options-${radio.value}`)
      })
    })
  })
})
