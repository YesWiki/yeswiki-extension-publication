$(document).ready(function(){
  $(document).on('click', 'a.bazar2publication-action', function(event) {
    event.preventDefault()

    const $button = $(this)
    const url = new URL($button.attr('href'))
    const params = url.searchParams
    const pageParams = new URLSearchParams(window.location.search)

    // rename 'facette' as 'query'
    if (pageParams.has('facette')) {
      params.set('query', pageParams.get('facette'))
    }

    if (pageParams.has('debug')) {
      console.debug('Redirecting to %s', url)
    }

    window.location = url
  })
});
