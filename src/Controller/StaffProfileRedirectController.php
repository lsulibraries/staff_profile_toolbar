<?php

namespace Drupal\staff_profile_toolbar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaffProfileRedirectController extends ControllerBase {

  /**
   * Redirect the current user to their staff profile edit form.
   */
  public function goToProfile() {
    $current_user = $this->currentUser();

    if ($current_user->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'staff')
      ->condition('field_owner.target_id', $current_user->id())
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      throw new NotFoundHttpException();
    }

    $staff_node = Node::load(reset($nids));

    if (!$staff_node) {
      throw new NotFoundHttpException();
    }

    if (!$staff_node->access('update', $current_user)) {
      throw new AccessDeniedHttpException();
    }

    return $this->redirect('entity.node.edit_form', [
      'node' => $staff_node->id(),
    ]);
  }

}