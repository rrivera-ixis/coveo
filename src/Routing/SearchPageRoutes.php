<?php

namespace Drupal\coveo\Routing;

use Symfony\Component\Routing\Route;

/**
 * Provides dynamic routes for Coveo search pages.
 */
class SearchPageRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];
    $database = \Drupal::database();
    $result = $database->select('config', 'c')
      ->fields('c', ['name', 'data'])
      ->condition('name', "%" . $database->escapeLike('search_api.server') . "%", 'LIKE')
      ->execute()
      ->fetchAllAssoc('name');

    foreach (array_keys($result) as $name) {
      $backend_config = \Drupal::config($name)->get('backend_config');
      if (array_key_exists('search_page', $backend_config)) {
        $routes[$name] = new Route(
          $backend_config['search_page'],
          [
            '_controller' => '\Drupal\coveo\Controller\SearchController::searchPage',
            'server' => $name,
          ],
          [
            '_permission' => 'access content',
          ]
        );
      }
    }

    return $routes;
  }

}
