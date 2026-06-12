# Staff Profile Toolbar

A small Drupal 11 module that adds a `/my-profile` route for staff editors and redirects them to the edit form for their associated **Staff** node. It is designed to work especially well with **Gin Toolbar Custom Menu**, where you can add a `Profile` item in a custom Gin sidebar menu and point it at `/my-profile`.

The intended content model is:

- A `staff` content type.
- A user reference field on that content type named `field_owner`.
- A Gin Toolbar Custom Menu entry titled `Profile` that links to `/my-profile`.

When a logged-in user visits `/my-profile`, the module looks for the first `staff` node whose `field_owner` references that user and redirects them to the node edit form via Drupal's route system.

## Features

- Adds a custom `/my-profile` route in Drupal 11.
- Redirects users to `entity.node.edit_form` for their matching Staff node.
- Supports hiding the menu item automatically by protecting the route with a custom access check, since Drupal menu links are hidden when the target route is inaccessible.
- Works cleanly with Gin Toolbar Custom Menu for role-based sidebar configuration.

## Requirements

- Drupal 11.
- A content type with machine name `staff`.
- A user entity reference field on that content type with machine name `field_owner`.
- Gin Admin Theme, if you want the Gin sidebar experience.
- Gin Toolbar Custom Menu, if you want to place the `Profile` item in a custom Gin sidebar menu by role.

## Recommended setup

This module is best used with **Gin Toolbar Custom Menu** instead of trying to make a GUI-created menu item dynamic by itself. Gin Toolbar Custom Menu is meant to display a selected Drupal menu for selected roles, while the custom module handles the actual dynamic destination logic.

Recommended pattern:

1. Create a Drupal menu such as `Staff Tools`.
2. Add a menu item titled `Profile` with the link `/my-profile`.
3. In `/admin/config/system/gin-toolbar-custom-menu`, select that menu in a rule and assign the appropriate roles.
4. Keep the administration menu enabled if you want the normal admin menu to remain visible alongside the custom one.

## Installation

Place the module in your custom modules directory:

```text
web/modules/custom/staff_profile_toolbar
```

Enable it with Drush:

```bash
drush en staff_profile_toolbar -y
drush cr
```

Or enable it through **Extend** in the Drupal admin UI.

## File structure

A minimal implementation looks like this:

```text
staff_profile_toolbar/
├── staff_profile_toolbar.info.yml
├── staff_profile_toolbar.routing.yml
├── staff_profile_toolbar.services.yml
└── src/
    ├── Access/
    │   └── StaffProfileAccessCheck.php
    └── Controller/
        └── StaffProfileRedirectController.php
```

## Example implementation

### `staff_profile_toolbar.info.yml`

```yml
name: Staff Profile Toolbar
type: module
description: Redirects users to their editable staff profile.
core_version_requirement: ^11
package: Custom
dependencies:
  - drupal:node
  - drupal:user
```

### `staff_profile_toolbar.routing.yml`

This route exposes `/my-profile` and uses a custom access checker so the menu item can be hidden for users who do not have an editable Staff profile.

```yml
staff_profile_toolbar.my_profile:
  path: '/my-profile'
  defaults:
    _controller: '\Drupal\staff_profile_toolbar\Controller\StaffProfileRedirectController::goToProfile'
    _title: 'My Profile'
  requirements:
    _custom_access: '\Drupal\staff_profile_toolbar\Access\StaffProfileAccessCheck::access'
```

### `staff_profile_toolbar.services.yml`

```yml
services:
  staff_profile_toolbar.access_check:
    class: Drupal\staff_profile_toolbar\Access\StaffProfileAccessCheck
```

### `src/Access/StaffProfileAccessCheck.php`

The route access check returns allowed only when the current account has a related `staff` node through `field_owner` and can update that node.

```php
<?php

namespace Drupal\staff_profile_toolbar\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

class StaffProfileAccessCheck {

  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
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
```

### `src/Controller/StaffProfileRedirectController.php`

The controller redirects the user to the edit form for the matching Staff node using Drupal's route-based redirect helper.

```php
<?php

namespace Drupal\staff_profile_toolbar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaffProfileRedirectController extends ControllerBase {

  public function goToProfile() {
    $current_user = $this->currentUser();

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
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

    return $this->redirect('entity.node.edit_form', [
      'node' => $staff_node->id(),
    ]);
  }

}
```

## Gin Toolbar Custom Menu configuration

After the module is enabled, configure Gin Toolbar Custom Menu like this:

- Create or choose a Drupal menu for your staff editors.
- Add a menu item with:
  - **Title:** `Profile`
  - **Link:** `/my-profile`
- Go to `/admin/config/system/gin-toolbar-custom-menu`.
- Create or edit a rule and select your custom menu.
- Assign only the roles that should see the menu.
- Do not put the same roles in both **Assigned roles** and **Excluded roles**, because that can cancel the rule out.

Because the route uses a custom access check, users without an editable staff profile should not see the `Profile` menu item when Drupal applies route access to menu links.

## How it works

1. A user clicks `Profile` in the Gin sidebar.
2. The menu item points to `/my-profile`.
3. The route access checker confirms that the current user has a related editable Staff node.
4. The controller queries for the first `staff` node where `field_owner` references the current user.
5. The controller redirects the user to that node's edit form using `entity.node.edit_form`.

## Assumptions

This example assumes all of the following are true:

- The Staff content type machine name is `staff`.
- The user reference field machine name is `field_owner`.
- Each user should edit at most one Staff node.

If one user can be linked to multiple Staff nodes, a better pattern is to send `/my-profile` to a chooser page or listing page instead of taking the first query result.

## Customization

### Different content type name

If your content type machine name is not `staff`, change this line in both the access checker and controller:

```php
->condition('type', 'staff')
```

### Different field name

If your user reference field is not `field_owner`, change this line in both places:

```php
->condition('field_owner.target_id', $account->id())
```

or in the controller:

```php
->condition('field_owner.target_id', $current_user->id())
```

### Friendlier fallback

The example uses a 404 when no editable profile is found. A more editor-friendly alternative is to redirect the user to `/admin/content` and display a status or warning message instead.

## Troubleshooting

### The Profile link does not appear in Gin

Check the following:

- The custom menu is selected in Gin Toolbar Custom Menu.
- The intended roles are in **Assigned roles**.
- Those same roles are not also in **Excluded roles**.
- The menu item points to `/my-profile`.
- The user has the `Use toolbar` permission if required by your setup.
- Caches were rebuilt after code changes.

### The route works for admins but not editors

That usually means the editor does not have update access to the Staff node, or the query does not match any `staff` node for that user.

### The menu item shows for everyone

That usually means the Gin Toolbar Custom Menu rule is role-based but the `/my-profile` route is not using the custom access checker yet.

## Development notes

This example keeps the code simple by using `\Drupal::entityQuery()` and `Node::load()` directly. For a production-hardened version, dependency injection would be the next improvement, especially if the module grows beyond a single redirect route and a single access checker.

## License

GPL-2.0-or-later, consistent with Drupal projects.
