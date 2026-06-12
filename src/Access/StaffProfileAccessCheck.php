<?php

namespace Drupal\staff_profile_toolbar\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

class StaffProfileAccessCheck {

  /**
   * Custom access callback for /my-profile.
   */
  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'staff')
      ->condition('field_owner.target_id', $account->id())
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $staff_node = Node::load(reset($nids));

    if (!$staff_node) {
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::allowedIf($staff_node->access('update', $account))
      ->cachePerUser();
  }

}