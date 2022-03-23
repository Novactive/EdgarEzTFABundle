# EdgarEzTFABundle

## Installation

### Get the bundle using composer

Add the repository to your `composer.json` file so the required fork of the bundle can be installed:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Novactive/EdgarEzTFABundle"
        }
    ]
}
```

Add EdgarEzTFABundle by running this command from the terminal at the root of
your symfony project:

```bash
composer require edgar/ez-tfa-bundle:dev-fixed-bugs-added-autosetup-email-only
```

**Note:** This is a fork of the original edgar/ez-tfa-bundle with the changes like:
* fixed bugs
* kept email provider only
* added automatic setup 

## Enable the bundle

To start using the bundle, register the bundle in your application's kernel class:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Edgar\EzUIProfileBundle\EdgarEzUIProfileBundle(),
        new Edgar\EzTFABundle\EdgarEzTFABundle(),
        // ...
    );
}
```

## Add doctrine ORM support

in your ezplatform.yml, add

```yaml
doctrine:
    orm:
        auto_mapping: true
```

## Update your SQL schema

```
php bin/console doctrine:schema:update --force
```

## Add routing

Add to your global configuration app/config/routing.yml

```yaml
edgar.ezuiprofile:
    resource: '@EdgarEzUIProfileBundle/Resources/config/routing.yml'
    prefix:   /_profile
    defaults:
        siteaccess_group_whitelist: 'admin_group'
        
edgar.eztfa:
    resource: "@EdgarEzTFABundle/Resources/config/routing.yml"
    prefix:   /_tfa    
```

## Configure bundle

Two providers are natively available:
* email
* sms (currently not supported in this version and should be disabled in config)
* u2f (currently not supported in this version and should be disabled in config)

### Providers configuration

Don't activate TFA for all site, specially for back-office siteaccess.
 
If you want to disable a TFA provider, just add *disabled: true* parameter.
To enable the automatic setup add *auto_setup: true* parameter. Example:

```yaml
# app/config/config.yml
edgar_ez_tfa:
    system:
        admin: # TFA is activated only for this siteaccess
            providers:
                email:
                    from: no-spam@your.mail # email provider sender mail
                    auto_setup: true # whether email 2fa should be setup automatically
                sms:
                    disabled: true
                u2f:
                    disabled: true
```

To make sure the changes are applied you can clear the cache by running:

```bash
php bin/console cache:clear
```

**Note:** To set up TFA manually in the eZ back office go to the _Profile_ -> _Security Configuration_ -> _Two Factor Authentication_ from the User Menu.