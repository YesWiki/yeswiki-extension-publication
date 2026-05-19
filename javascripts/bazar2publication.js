$(document).ready(function() {
  function updateLink(target) {
    // selector for vuejs dynamic entries
    const el = $(".bazar-list-dynamic-container")[0];
    let entries = "";
    if (el && el.__vue_app__) {
      const vm = el.__vue_app__._instance.proxy;
      entries = vm.filteredEntries.map((item) => item.id_fiche).join(",");
    } else { //non-dynamic
      entries = $(".bazar-list .bazar-entry:visible")
        .map(function() {
          return $(this).data("id_fiche");
        })
        .get()
        .join(",");
    }
    const $button = $(target);
    let urlButton = new URL($button.attr("href"));
    urlButton.searchParams.set("query", "id_fiche=" + entries);
    urlButton.searchParams.set("browserPrintAfterRendered", 1);
    urlButton.search = decodeURIComponent(urlButton.search);
    const newUrl = urlButton.toString().replace("/pdf=", "/preview");
    $button.attr("href", newUrl.replace("/preview=", "/preview"));
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
)
