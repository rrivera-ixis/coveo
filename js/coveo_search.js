/**
 * @file
 * Helpers for Newsroom view filters.
 */

(function ($, Drupal, drupalSettings) {
  "use strict";

  document.addEventListener('DOMContentLoaded', function () {
    Coveo.SearchEndpoint.endpoints['default'] = new Coveo.SearchEndpoint({
      restUri: 'https://platform.cloud.coveo.com/rest/search',
      accessToken: drupalSettings.coveo.api_key_readonly,
      queryStringArguments: {
        organizationId: drupalSettings.coveo.application_id,
      }
    });

    var root = $('.CoveoSearchInterface')[0];

    Coveo.$$(root).on('buildingQuery', function (e, args) {
      // Lock to the source id from the server config.
      var queryBuilder = args.queryBuilder;
      queryBuilder.advancedExpression.add('@syssource=="' + drupalSettings.coveo.source_name + '"');
    });

    var onSelectTitleFieldSuggestion = function (selectedValue, args) {
      Coveo.state(root, "f:@title", [selectedValue]);
      args.clear();
      Coveo.executeQuery(root);
    };
    Coveo.init(root, {
      titleFieldSuggestions : {
        onSelect : onSelectTitleFieldSuggestion
      }
    });
  });

})(jQuery, Drupal, drupalSettings);
