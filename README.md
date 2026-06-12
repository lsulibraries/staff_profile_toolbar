# Staff Profile Toolbar

A small Drupal 11 module that adds a `/my-profile` route for staff editors and redirects them to the edit form for their associated **Staff** node. It is designed to work especially well with **Gin Toolbar Custom Menu**, where you can add a `Profile` item in a custom Gin sidebar menu and point it at `/my-profile`.[web:79][web:138]

The intended content model is:

- A `staff` content type.[web:41]
- A user reference field on that content type named `field_owner`.[web:94][web:102]
- A Gin Toolbar Custom Menu entry titled `Profile` that links to `/my-profile`.[web:79][web:59]

When a logged-in user visits `/my-profile`, the module looks for the first `staff` node whose `field_owner` references that user and redirects them to the node edit form via Drupal's route system.[web:41][web:138]

## Features

- Adds a custom `/my-profile` route in Drupal 11.[web:151]
- Redirects users to `entity.node.edit_form` for their matching Staff node.[web:138]
- Supports hiding the menu item automatically by protecting the route with a custom access check, since Drupal menu links are hidden when the target route is inaccessible.[web:160][web:162]
- Works cleanly with Gin Toolbar Custom Menu for role-based sidebar configuration.[web:79][web:4]

## Requirements

- Drupal 11.[web:151]
- A content type with machine name `staff`.[web:41]
- A user entity reference field on that content type with machine name `field_owner`.[web:94][web:102]
- Gin Admin Theme, if you want the Gin sidebar experience.[web:8]
- Gin Toolbar Custom Menu, if you want to place the `Profile` item in a custom Gin sidebar menu by role.[web:79]

## Recommended setup

This module is best used with **Gin Toolbar Custom Menu** instead of trying to make a GUI-created menu item dynamic by itself. Gin Toolbar Custom Menu is meant to display a selected Drupal menu for selected roles, while the custom module handles the actual dynamic destination logic.[web:79][web:4]

Recommended pattern:

1. Create a Drupal menu such as `Staff Tools`.[web:4][web:114]
2. Add a menu item titled `Profile` with the link `/my-profile`.[web:59]
3. In `/admin/config/system/gin-toolbar-custom-menu`, select that menu in a rule and assign the appropriate roles.[web:79][web:4]
4. Keep the administration menu enabled if you want the normal admin menu to remain visible alongside the custom one.[web:111][web:4]

## Installation

Place the module in your custom modules directory:[web:151]

```text
web/modules/custom/staff_profile_toolbar
```

Enable it with Drush:[web:151]

```bash
drush en staff_profile_toolbar -y
drush cr
```

Or enable it through **Extend** in the Drupal admin UI.[web:151]

## File structure

A minimal implementation looks like this:[web:151][web:160]

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

This route exposes `/my-profile` and uses a custom access checker so the menu item can be hidden for users who do not have an editable Staff profile.[web:160][web:162]

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

The route access check returns allowed only when the current account has a related `staff` node through `field_owner` and can update that node.[web:160][web:148]

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

The controller redirects the user to the edit form for the matching Staff node using Drupal's route-based redirect helper.[web:138]

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

After the module is enabled, configure Gin Toolbar Custom Menu like this:[web:79][web:4]

- Create or choose a Drupal menu for your staff editors.[web:4][web:114]
- Add a menu item with:
  - **Title:** `Profile`
  - **Link:** `/my-profile`[web:59]
- Go to `/admin/config/system/gin-toolbar-custom-menu`.[web:79]
- Create or edit a rule and select your custom menu.[web:79][web:4]
- Assign only the roles that should see the menu.[web:111]
- Do not put the same roles in both **Assigned roles** and **Excluded roles**, because that can cancel the rule out.[web:111]

Because the route uses a custom access check, users without an editable staff profile should not see the `Profile` menu item when Drupal applies route access to menu links.[web:160][web:162]

## How it works

1. A user clicks `Profile` in the Gin sidebar.[web:79]
2. The menu item points to `/my-profile`.[web:59]
3. The route access checker confirms that the current user has a related editable Staff node.[web:160][web:148]
4. The controller queries for the first `staff` node where `field_owner` references the current user.[web:41]
5. The controller redirects the user to that node's edit form using `entity.node.edit_form`.[web:138]

## Assumptions

This example assumes all of the following are true:[web:41][web:102]

- The Staff content type machine name is `staff`.
- The user reference field machine name is `field_owner`.
- Each user should edit at most one Staff node.

If one user can be linked to multiple Staff nodes, a better pattern is to send `/my-profile` to a chooser page or listing page instead of taking the first query result.[web:41]

## Customization

### Different content type name

If your content type machine name is not `staff`, change this line in both the access checker and controller:[web:41]

```php
->condition('type', 'staff')
```

### Different field name

If your user reference field is not `field_owner`, change this line in both places:[web:94][web:102]

```php
->condition('field_owner.target_id', $account->id())
```

or in the controller:

```php
->condition('field_owner.target_id', $current_user->id())
```

### Friendlier fallback

The example uses a 404 when no editable profile is found.[web:138] A more editor-friendly alternative is to redirect the user to `/admin/content` and display a status or warning message instead.[web:138]

## Troubleshooting

### The Profile link does not appear in Gin

Check the following:[web:79][web:111]

- The custom menu is selected in Gin Toolbar Custom Menu.
- The intended roles are in **Assigned roles**.
- Those same roles are not also in **Excluded roles**.[web:111]
- The menu item points to `/my-profile`.[web:59]
- The user has the `Use toolbar` permission if required by your setup.[web:79]
- Caches were rebuilt after code changes.[web:151]

### The route works for admins but not editors

That usually means the editor does not have update access to the Staff node, or the query does not match any `staff` node for that user.[web:148][web:160]

### The menu item shows for everyone

That usually means the Gin Toolbar Custom Menu rule is role-based but the `/my-profile` route is not using the custom access checker yet.[web:79][web:160]

## Development notes

This example keeps the code simple by using `\Drupal::entityQuery()` and `Node::load()` directly. For a production-hardened version, dependency injection would be the next improvement, especially if the module grows beyond a single redirect route and a single access checker.[web:151][web:139]

## License

GPL-2.0-or-later, consistent with Drupal projects.
