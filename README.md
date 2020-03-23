This project is not maintained anymore

This bundle helps your application to migrate from sf3 architecture to sf4 architecture, it will parse all the files to check the kind and move it to the right place. It will update the services according. This bundle is an helper it does not ensure the application to work after and it is probable you'll have to fix some very custom thins.

Feature:

- Move the files to an architecture in sf4
- Update the namespaces
- Update the Services called in files
- Update the services.yaml files

Installation
============

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require kbunel/migration-helper-sf4
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require kbunel/migration-helper-sf4
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new kbunel\MigrationHelperSF4(),
        );

        // ...
    }

    // ...
}
```

Command
============

```console
$ php bin/console kbunel:migrate:sf4
```

