(function ($) {
  'use strict';
  /*  
   setTimeout(function () {
   let ex = $('.editor-post-excerpt').prev();
   let ch = $('button', ex).html();
   $('button', ex).html(ch.replace('Excerpt','Subtitle'));
   }, 1000);*/

  $('.rwmb-field .rwmb-apikey').next().click(function (e) {
    e.preventDefault();
    let a = $(this);
    $(a).css({'pointer-events': 'none', 'opacity': .5});
    let u = mlcommons_admin_options.ajaxurl;
    let d = {
      action: 'mlp_apikey_hint',
      field: $(a).data('field')
    };
    $('p.hint', $(a).parent()).remove();
    $.post(u, d, function (response) {
      var p = $('<p/>');
      $(a).parent().append(p);
      $(p).addClass('hint');
      $(p).text(response.hint);
      $(a).css({'pointer-events': 'auto', 'opacity': 1});
    }, 'json');
  });

  $('option', '#mlp_gcal_event_colors_mlp_gcal_gcolor').each(function (i, e) {
    
    $(e).css({backgroundColor: $(e).text().split('-')[0]});
  });
  
  
  $('a.gcal-event-sync').click(function (e) {
    e.preventDefault();
    let a = $(this);
    $(a).css({'pointer-events': 'none', 'opacity': .5});
    $('.sync-calendar', '#calendar-ajax-response').remove();
    let u = mlcommons_admin_options.ajaxurl;
    let d = {
      action: 'mlp_gcal_sync_event',
      cal_id: $(a).data('calid')
    };
    var t = $('<pre/>');
    $('#calendar-ajax-response').append(t);
    $(t).text('loading...');

    $.post(u, d, function (response) {

      $(t).text(response);
      $(t).addClass('sync-calendar');
      $(a).css({'pointer-events': 'auto', 'opacity': 1});
    });
  });
  
  
  $('a.copy-ml-section').click(function (e) {
    e.preventDefault();
    console.log(1);
    navigator.clipboard.writeText($(this).text());

    // Alert the copied text
    alert("Copied the text: " + $(this).text());
  });

})(jQuery);
