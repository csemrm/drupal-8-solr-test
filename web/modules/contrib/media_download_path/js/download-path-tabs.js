(function ($, Drupal) {
  Drupal.behaviors.downloadPathDetailsSummaries = {
    attach: function attach(context) {
      $(context).find('.media-download-path-form').drupalSetSummary(function (context) {
        var path = $('.js-form-item-media-download-path-0-alias input').val();

        return path ? Drupal.t('Alias: @alias', { '@alias': path }) : Drupal.t('No alias');
      });
    }
  };
})(jQuery, Drupal);
