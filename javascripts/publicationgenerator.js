/**
 * +------------------------------------------------------------------------------------------------------+
 * | Copyright (C) 2013 Outils-Reseaux (accueil@outils-reseaux.org)                                       |
 * +------------------------------------------------------------------------------------------------------+
 * | This library is free software; you can redistribute it and/or                                        |
 * | modify it under the terms of the GNU Lesser General Public                                           |
 * | License as published by the Free Software Foundation; either                                         |
 * | version 2.1 of the License, or (at your option) any later version.                                   |
 * |                                                                                                      |
 * | This library is distributed in the hope that it will be useful,                                      |
 * | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
 * | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
 * | Lesser General Public License for more details.                                                      |
 * |                                                                                                      |
 * | You should have received a copy of the GNU Lesser General Public                                     |
 * | License along with this library; if not, write to the Free Software                                  |
 * | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
 * +------------------------------------------------------------------------------------------------------+
 *
 * javascript for pages export
 *
 *
 * @package 	publication
 * @author		Florian Schmitt <florian@outils-reseaux.org>
 *
 *
 **/

$(document).ready(function () {
    $('.export-table-form').on('submit', function() {
        $(this).append('<input type="hidden" name="antispam" value="1" />')
    })

    $("#publication-selection-container").sortable();

	$('.btn-erase-filter').on('click', function() {
        $("#filter").val('').keyup();
    });

	$('#publication-selection-container').on('click', '.remove-page-break', function() {
        $(this).parent().remove();
        return false;
    });

    $('.page-break').on('click', function() {
        const label = this.dataset.label
        $("#publication-selection-container").append(`<li class="list-group-item blank-page">
            <button class="pull-right btn btn-sm btn-danger remove-page-break" title="Enlever le saut de page" href="#">
                <i class="fas fa-trash"></i>
            </button>
            <span class="movable">
                <i class="fas fa-arrows-alt-v"></i>
            </span>
            <input type="hidden" name="page[]" value="{{blankpage}}">
            <span class="page-label">${label}</span>
        </li>`);
        return false;
    });

    $('.select-all').on('click', function() {
        $(this).parent().next('.list-entries-to-export').find('.select-page-item:visible').click();
        return false;
    });

	$('.select-page-item').on('click', function() {
      var $this = $(this);
		  $this.siblings().filter('.remove-page-item').removeClass('hide');
      $this.siblings().filter(".movable").removeClass('hide');
		  $this.addClass('hide');
		  var listitem = $this.parent();
		  listitem.fadeOut("fast", function() {
			  listitem.appendTo("#publication-selection-container").fadeIn("fast");
      });

      return false;
	});

  $('.remove-page-item').on('click', function() {
      var $this = $(this);
      $this.siblings().filter('.select-page-item').removeClass('hide');
      $this.siblings().filter(".movable").addClass('hide');
      $this.addClass('hide');
      var listitem = $this.parent();
      listitem.fadeOut("fast", function() {
        listitem.prependTo(".list-entries-to-export.group-"+$this.data('group')).fadeIn("fast");
      });

      return false;
  });

  var listpages = $(".export-table-container .list-group-item");
  var filter = $("#filter");
  var filtercount = $("#filter-count");

	filter.keyup(function(){
        // Retrieve the input field text and reset the count to zero
        var count = 0;

        // Loop through the comment list
        listpages.each(function(){
            // If the list item does not contain the text phrase fade it out
            if ($(this).text().search(new RegExp(filter.val(), "i")) < 0) {
                $(this).hide();

            // Show the list item if the phrase matches and increase the count by 1
            } else {
                $(this).show();
                count++;
            }
        });

        // Update the count
        filtercount.text(`Nombre de pages : ${count}`);
    });

    /* Display ebook publication options (book, fanzine) */
    $('[name="publication-mode"]').on('change', function (event) {
      var mode = event.target.value
      var $options = $('details.publication-options')

      $options.not(`.options-${mode}`).attr('hidden', true)
      $options.filter(`.options-${mode}`).removeAttr('hidden')
    })

    var mode = $('[name="publication-mode"][checked]').val()
    $('details.publication-options').filter(`.options-${mode}`).removeAttr('hidden')
});
