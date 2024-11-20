# GSOC_simpleNews_module_issue3031919
issue - Bugs if user has blank email address

**Motivation and problem summary**
1. Page that has newsletter attached #2892047: User without email leads to Query condition 'simplenews_subscriber.mail IN ()' cannot be empty => exception as below.
2. Newsletters page on user account => same exception as below.
3. Subscription forms - they are shown with the text box to enter an email address as if for an anonymous user. Any subscription created is not linked to the account.
4. "Newsletters" extra field on user entity => exception as below.

**Proposed resolution**
The "obvious" solution that people started to work towards is to prevent users without email from subscribing. However a better approach is to allow subscribing but of course the subscription won't activate until an email address is set.

1. It solves the problem of losing hidden&forced subscriptions if the user is initially created without an email.
2. It avoids a lot of code if ($account->getEmail()) - there are places than in the initial IS

For details : https://www.drupal.org/project/simplenews/issues/3031919
