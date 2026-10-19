(function ($) {
  'use strict';

  $(document).on('click', '.eldokan-home-media', function () {
    var target = document.getElementById($(this).data('target'));
    if (!target || !window.wp || !wp.media) return;

    var frame = wp.media({
      title: eldokanHomeUI.title,
      button: { text: eldokanHomeUI.use },
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      target.value = attachment.id || '';
      target.dispatchEvent(new Event('change', { bubbles: true }));
    });
    frame.open();
  });
})(jQuery);
