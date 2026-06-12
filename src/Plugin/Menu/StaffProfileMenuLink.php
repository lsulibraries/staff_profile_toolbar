<?php

namespace Drupal\staff_profile_toolbar\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\node\Entity\Node;

/**
 * Provides a dynamic menu link for the current user's staff profile.
 */
class StaffProfileMenuLink extends MenuLinkDefault {

  /**
   * Get the current user's related staff node.
   *
   * @return \Drupal\node\Entity\Node|null
   *   The staff node, or NULL if none exists.
   */
  protected function getStaffNode() {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      return NULL;
    }

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'staff')
      ->condition('field_owner.target_id', $current_user->id())
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      return NULL;
    }

    return Node::load(reset($nids));
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    $parameters = parent::getRouteParameters();
    $staff_node = $this->getStaffNode();

    if ($staff_node) {
      $parameters['node'] = $staff_node->id();
    }

    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    $staff_node = $this->getStaffNode();

    if (!$staff_node) {
      return FALSE;
    }

    return $staff_node->access('update', \Drupal::currentUser());
  }

}