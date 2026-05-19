$(document).ready(function() {
  function updateLink(target) {
    // selector for vuejs dynamic entries
    const el = $(".bazar-list-dynamic-container")[0];
    let entries = "";
    if (el) {
      let vm = null;
      if (el.__vue_app__ && el.__vue_app__._instance) {
        vm = el.__vue_app__._instance.proxy;
      }
      else if (el.firstElementChild && el.firstElementChild.__vnode && el.firstElementChild.__vnode.component) {
        vm = el.firstElementChild.__vnode.component.proxy;
      }
      else if (el.__vue_app__ && el.__vue_app__._container && el.__vue_app__._container._vnode) {
        vm = el.__vue_app__._container._vnode.component.proxy;
      }
      if (vm && vm.filteredEntries) {
        entries = vm.filteredEntries.map(item => item.id_fiche).join(",");
      }
    }

    // fallback for non dynamic templates
    if (!entries) {
      entries = $(".bazar-list .bazar-entry:visible")
        .map(function() {
          return $(this).attr("data-id_fiche");
        })
        .get()
        .join(",");
    }

    const $button = $(target);
    if (entries) {
      let urlButton = new URL($button.attr("href"));
      urlButton.searchParams.set("query", "id_fiche=" + entries);
      urlButton.searchParams.set("browserPrintAfterRendered", 1);
      urlButton.search = decodeURIComponent(urlButton.search);
      const newUrl = urlButton.toString().replace("/pdf=", "/preview");
      $button.attr("href", newUrl.replace("/preview=", "/preview"));
    } else {
      console.log('No entries found')
      $button.attr("href", "#");
    }
  }

  $(document).on(
    "mousedown click",
    "a.bazar2publication-action",
    function(event) {
      updateLink($(this));

      toastMessage(
        _t("PUBLICATION_PDF_GENERATION_LANCHED"),
        7000,
        "alert alert-primary",
      );
      event.preventDefault();

      const $button = $(this);
      const url = new URL($button.attr("href"));

      if (wiki.isDebugEnabled) {
        console.debug("Redirecting to %s", url);
      }

      window.location = url;
    },
  );
});
