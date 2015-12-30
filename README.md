# Import Bundle

[![Join the chat at https://gitter.im/delirehberi/importbundle](https://badges.gitter.im/delirehberi/importbundle.svg)](https://gitter.im/delirehberi/importbundle?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Installation
============


Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require delirehberi/import ">=1"
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

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

            new Delirehberi\ImportBundle\DelirehberiImportBundle(),
        );

        // ...
    }

    // ...
}
```

Step 3: Configure your mapping

Open your config file (`app/config/config.yml`) and edit like this:

```yaml
parameters:
  delirehberi_import:
    connection_key:
      database:
        driver: pdo_mysql
        user: root
        password: null
        dbname: old_database_name
        host: localhost
        port: ~
        charset: 'utf8'
      maps:
        news:
          entity: Acme\ContentBundle\Entity\Content
          old_data:
            service_id: my_project.data_service
            method: getOldData
          fields:
            old_id:
              type: integer
              name: ID
          ...
```

Documentation
=============

See the [summary](https://github.com/delirehberi/importbundle/blob/master/Resources/doc/summary.md).

