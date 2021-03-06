# Asset Duct for Laravel 4 - Alpha

[![Latest Stable Version](https://poser.pugx.org/torann/duct/v/stable.png)](https://packagist.org/packages/torann/duct) [![Total Downloads](https://poser.pugx.org/torann/duct/downloads.png)](https://packagist.org/packages/torann/duct)

The Torann/Duct package is meant to simplify the creation and maintenance of the essential assets of a Laravel based application.

----------

## Note About Laravel 5
With Laravel Elixir in Laravel 5 I'm not sure if this package will be updated to support Laravel 5. Time will tell.

## Features

* Out of the box supported for LESS, CSS, and JavaScript files.
* Support for custom post processors (SCSS, CoffeeScript, etc.)
* Combining and minifying of JavaScript and CSS
* Organize assets into manifest files
* Asset fingerprinting with support for images
* Asset support inside of LESS/CSS

## Installation

- [Asset Duct on Packagist](https://packagist.org/packages/torann/duct)
- [Asset Duct on GitHub](https://github.com/torann/asset-duct)

To get the latest version of Asset Duct simply require it in your `composer.json` file.

~~~
"torann/duct": "0.1.*@dev"
~~~

You'll then need to run `composer install` to download it and have the autoloader updated.

Once Asset Duct is installed you need to register the service provider with the application. Open up `app/config/app.php` and find the `providers` key.

Then register the service provider

```php
'Torann\Duct\ServiceProvider'
```

> There is no need to add the Facade, the package will add it for you.

### Create configuration file using artisan

```
$ php artisan config:publish torann/duct
```

### Add to .gitignore

The local assets directory needs to be added to the **.gitignore** file. This reflects the `asset_dir` variable in the config file.

```
public/assets/*
```

## Documentation

[View the official documentation](http://lyften.com/projects/duct/).

## Change Log

#### v0.1.0 Alpha

- First release
