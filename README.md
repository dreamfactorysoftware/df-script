## DreamFactory Script v0.1

[![Total Downloads](https://poser.pugx.org/dreamfactory/df-script/d/total.svg)](https://packagist.org/packages/dreamfactory/df-script)
[![Latest Stable Version](https://poser.pugx.org/dreamfactory/df-script/v/stable.svg)](https://packagist.org/packages/dreamfactory/df-script)
[![Latest Unstable Version](https://poser.pugx.org/dreamfactory/df-script/v/unstable.svg)](https://packagist.org/packages/dreamfactory/df-script)
[![License](https://poser.pugx.org/dreamfactory/df-script/license.svg)](http://www.apache.org/licenses/LICENSE-2.0)

> **Note:** This repository contains the scripting features of the DreamFactory platform. If you want the full DreamFactory platform, visit the main [DreamFactory repository](https://github.com/dreamfactorysoftware/dreamfactory).

## Overview

DreamFactory(™) Script is a package built on top of the DreamFactory core, and as such retains the requirements of the [df-core](https://github.com/dreamfactorysoftware/df-core). 

## Documentation

Documentation for the scripting features can be found on the [DreamFactory wiki](http://wiki.dreamfactory.com/DreamFactory/Features/Scripting).

## Installation

Edit your project’s composer.json to require the following package.

	“require”:{
		"dreamfactory/df-script": "~0.1.0"
	}

You may also need to add the following…

	"minimum-stability": "dev",
	"prefer-stable": true,


Save your composer.json and do a "composer update" to install the package.
Once the package is installed edit your config/app.php file to add the DfServiceProvider in the Providers array.

	‘providers’ => [
		….,
		….,
		'DreamFactory\Script\DfServiceProvider'
	]

Next run "php artisan vendor:publish" to publish the config file df.php to config/ directory and a helpful test_rest.html file to public/ directory.

dreamfactory/df-script package also includes some helpful *-dist files inside the config directory. You can take a look at that and copy over what’s needed to the corresponding files of your app.
If you have setup your database connection right in your .env file then run the following migration.
	
	php artisan migrate --path=vendor/dreamfactory/df-script/database/migrations/

After the migration run the following seeder class.

	php artisan db:seed --class=DreamFactory\\Script\\Database\\Seeds\\DatabaseSeeder

Now if you have setup the phpunit config right in phpunit.xml (Use the supplied phpunit.xml-dist file in the package to use the right params) file then you should be able to run the unit tests.

	phpunit vendor/dreamfactory/df-script/tests/

## Feedback and Contributions

* Feedback is welcome in the form of pull requests and/or issues.
* Contributions should generally follow the strategy outlined in ["Contributing to a project"](https://help.github.com/articles/fork-a-repo#contributing-to-a-project)
* All pull requests must be in a ["git flow"](https://github.com/nvie/gitflow) feature branch and formatted as [PSR-2 compliant](http://www.php-fig.org/psr/psr-2/) to be considered.

### License

The DreamFactory scripting script repository is open-sourced software available for use under the [Apache Version 2.0 license](http://www.apache.org/licenses/LICENSE-2.0).
