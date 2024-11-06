(function ($) {
  'use strict';

  if ($('body').is('.logged-in')) {
    var toolbar;
    if ($('a.post-edit-link').length) {
      $('a.post-edit-link').wrap('<div id="mlcommons-tools"></div>');
      $('a.post-edit-link').html('&raquo; Edit');
    } else {
      $('body').append('<div id="mlcommons-tools"></div>')
    }
    toolbar = $('#mlcommons-tools');
    let settings_link = $('<a/>');
    $(settings_link).addClass('settings-link').html('&raquo; Settings').attr({'href': mlcommons_settings.settings_link});
    $(toolbar).append(settings_link);
  }

  if ($('select.filter-lang').length) {
    var auto_trigger = false;
    var select = $('.filter-lang').first();
    var table_w = $(select).parent().next();
    if ($(table_w).is('.wp-block-flexible-table-block-table')) {
      $(select).data('table', table_w);
      let langs = [];
      $('table tbody tr', table_w).each(function (ix, row) {
        var l = $('td:first', row).text();
        $(row).prop('lang', l);
        langs.push(l);
      });
      langs.sort();
      $(langs).each(function (ixl, lang) {
        var o = $('<option/>');
        $(o).val(lang).text(lang);
        $(select).append(o);
      });
    }

    $('.filter-lang').on('change', function (e) {
      const table_flex = $(this).data('table');
      const table = $('table', table_flex);
      const lang = $(this).val();


      var offset = $('#mlcommons-header').outerHeight() + 100;
      if ('*' === $(this).val()) {
        $('tbody tr', table).show();
      } else {
        $('tbody tr', table).hide();
        $('tbody tr[lang="' + lang + '"').show();

      }
      if (auto_trigger) {
        const endPosition = $(table_flex).offset().top - offset;
        window.scrollTo(0, endPosition);
      }
      auto_trigger = true;
    });

    $('.filter-lang').val('English').trigger('change');
  }

})(jQuery);
