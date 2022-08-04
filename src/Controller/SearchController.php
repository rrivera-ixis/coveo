<?php

namespace Drupal\coveo\Controller;

/**
 * @file
 * Contains \Drupal\coveo\Controller\SearchController.
 */

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Search page controller.
 */
class SearchController extends ControllerBase {

  /**
   * Search page.
   *
   * @return array
   *   Markup.
   */
  public function searchPage(Request $request) {
    $build = [];
    $server_config = $request->attributes->get('server');
    $coveo_config = $this->configFactory->get($server_config)->get('backend_config');

    $drupalSettings = [
      'coveo' => [
        'source_id' => $coveo_config['source_id'],
        'source_name' => $coveo_config['source_name'],
        'application_id' => $coveo_config['application_id'],
        'api_key_readonly' => $coveo_config['api_key'],
      ],
    ];
    $build['search'] = [
      '#theme' => 'coveo-search-page',
      '#attached' => [
        'drupalSettings' => $drupalSettings,
        'library' => [
          'coveo/coveo.search_page',
        ],
      ],
    ];

    return $build;
  }

}
