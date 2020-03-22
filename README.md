[![PHPUnit ](https://img.shields.io/badge/PHPUnit-96%25%20coverage-28a745.svg)]() [![PHPCS PSR12](https://img.shields.io/badge/PHPCS-PSR%2012-f09f47.svg)](https://www.php-fig.org/psr/psr-12/)

# Composer-PhpStorm

Auto-creates PhpStorm Run Configurations for PHP Unit and marks folders as excluded.

*Tested with PhpStorm 2019.3*

## Overview

* **ExcludeFolders** marks specified folders, symlinked folders and [Mozart](https://github.com/coenjacobs/mozart) managed packages as excluded from PhpStorm code navigation and completion, by adding entries to the project's `.iml` configuration file
* **PHPUnitRunConfigurations** creates a [Run Configuration](https://www.jetbrains.com/help/phpstorm/creating-run-debug-configuration-for-tests.html) for every `phpunit.xml` found in the project (ignoring `/vendor` and `/wp-content`), by adding entries to `workspace.xml`

## Installation

While still a new project, this is not yet available through Packagist. In `composer.json`:

```
"repositories": [
 {
  "url": "https://github.com/BrianHenryIE/composer-phpstorm",
  "type": "git"
 }
],

"require-dev": {
 "brianhenryie/composer-phpstorm": "dev-master"
}

"scripts": {
 "post-install-cmd": [
  "BrianHenryIE\\ComposerPhpStorm\\ExcludeFolders::update",
  "BrianHenryIE\\ComposerPhpStorm\\PHPUnitRunConfigurations::update"
 ],
 "post-update-cmd": [
  "BrianHenryIE\\ComposerPhpStorm\\ExcludeFolders::update",
  "BrianHenryIE\\ComposerPhpStorm\\PHPUnitRunConfigurations::update"
 ]
},
```

Optionally:

```
"extra": {
 "phpstorm": {
  "exclude_folders": {
  	"folders": [
   		"path/to/folder_one/from/project/base",
  		"path/to/folder_two/from/project/base"    
   ],
   "include_folders": [
  		"path/to/folder_one/from/project/base",
   ],
   'composer-symlinks': false
  }
 }
}

```

## Operation

### ExcludeFolders

Folders to exclude can be specified under `extras/phpstorm/exclude_folders/folders`. These are assumed to be relative from the project root. 

The Composer tool [coenjacobs/mozart](https://github.com/coenjacobs/mozart), for prefixing package namespaces, results in each class being copied, thus each classname::function having multiple implementations in PhpStorm's code completion. This tool reads the Mozart Composer configuration and excludes source folders of packages managed by Mozart.

The source of symlinks created by [kporras07/composer-symlinks](https://github.com/kporras07/composer-symlinks) are excluded if in the project directory. This can be disabled by setting `extras/phpstorm/exclude_folders/folders` to `false` in your `composer.json`.

Inside `/.idea/project-name.iml`'s `<component name="NewModuleRootManager"> <content url="file://$MODULE_DIR$">` adds:
 
```
<excludeFolder url="file://$MODULE_DIR$/foldertoexclude"/>
```

### PHPUnitRunConfigurations

The script searches the project directory for `phpunit.xml` and creates a PhpStorm Run Configuration for each one found (ignoring those under `/vendor/`), using the name `phpunit` when found in the project root folder and the folder name otherwise.

Inside `/.idea/workspace.xml`'s `<component name="RunManager">` adds:

```
<configuration name="tests" type="PHPUnitRunConfigurationType" factoryName="PHPUnit">
  <TestRunner configuration_file="$PROJECT_DIR$/tests/phpunit.xml" scope="XML" use_alternative_configuration_file="true"/>
  <method v="2"/>
</configuration>
```
  
## Why?

WordPress. [I write many small plugins](https://github.com/BrianHenryIE/WordPress-Plugin-Boilerplate), this will click a few buttons for me that I don't much care for. 

## TODO

* Symlinks could searched for, then checked if they were pointing inside the project directory, rather than reading from composer.json
* Set Default Interpreter (PHP Language Level/CLI Interpreter)
* Configuration to allow excluding autodiscovered `phpunit.xml`s
* Should find subpackages of those processed by Mozart 
* Set PHP language level
* Set PHPCS, CBF, WordPress path
* Allow disabling Mozart integration

## Acknowledgements

I was waiting for MacOS Catalina to download and install. I learned how to write the Composer extension from reading [kporras07/composer-symlinks](https://github.com/kporras07/composer-symlinks).