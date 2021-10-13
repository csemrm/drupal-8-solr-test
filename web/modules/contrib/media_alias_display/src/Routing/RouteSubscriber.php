<?php

namespace Drupal\media_alias_display\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.media.canonical')) {
      $route->setDefaults([
        '_controller' => '\Drupal\media_alias_display\Controller\DisplayController::view',
      ]);
    }
  }

}
