<?php

namespace BrianHenryIE\ComposerPhpStorm;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class ExcludeFolders
{
    public static function update(Event $event, Filesystem $filesystem = null)
    {

        /** @var PackageInterface $package */
        $package = $event->getComposer()->getPackage();
        /** @var Config $config */
        $config = $event->getComposer()->getConfig();

        $foldersToExclude = array();
        if (
            array_key_exists('phpstorm', $package->getExtra())
            && array_key_exists('exclude_folders', $package->getExtra()['phpstorm'])
            && array_key_exists('folders', $package->getExtra()['phpstorm']['exclude_folders'])
        ) {
            foreach ($package->getExtra()['phpstorm']['exclude_folders']['folders'] as $folderToExclude) {
                $folderToExclude = ltrim($folderToExclude, '.');
                $folderToExclude = trim($folderToExclude, '/');
                $foldersToExclude[] = $folderToExclude;
            }
        }
        
        $vendorPath = $config->get('vendor-dir');
        $rootPath = dirname($vendorPath);
        $filesystem = $filesystem ?: new Filesystem();

        // Find .iml file in .idea folder
        $phpStormProjectFolder = $rootPath . '/.idea/';

        if (!file_exists($phpStormProjectFolder)) {
            $event->getIO()->write(sprintf(
                '<info>PhpStorm project folder "%s" does not exist. '
                . 'Maybe this project has not been opened in PhpStorm yet.</info>',
                $phpStormProjectFolder
            ));
            return;
        }


        $imlFiles = glob($phpStormProjectFolder . '*.iml');

        if (count($imlFiles) == 0) {
            $event->getIO()->write(sprintf(
                '<info>No PhpStorm .iml file found in "%s".</info>',
                $phpStormProjectFolder
            ));
            return;
        }

        // Could there be more than one?
        if (count($imlFiles) > 1) {
            $event->getIO()->write(
                '<info>Unexpectedly found more than one .iml file. '
                . 'Please open an issue at GitHub.com/BrianHenryIE/composer-phpstorm</info>'
            );
            return;
        }

        $phpStormProjectConfig = $imlFiles[0];

        $dom = new \DOMDocument();
        $domWasModified = false;

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $fileIsValid = @$dom->load($phpStormProjectConfig);

        if (!$fileIsValid) {
            $event->getIO()->write(sprintf(
                '<info>Could not parse XML for PhpStorm .iml file at "%s".</info>',
                $phpStormProjectConfig
            ));
            return;
        }

        /** @var \DOMElement $root */
        $root = $dom->documentElement;

        /**
         * <content url="file://$MODULE_DIR$" />
         * <content url="file://$MODULE_DIR$" > ... </content>
         *
         * @var \DOMNode $content
         */
        $content = $root->getElementsByTagName('content')->item(0);


        // Check is symlinks installed, exclude any symlinks inside the
        // project that would cause double entries in PhpStorm.

        $processComposerSymlinks = isset($package->getExtra()['phpstorm']['exclude_folders']['composer-symlinks'])
            ? $package->getExtra()['phpstorm']['exclude_folders']['composer-symlinks'] : true;

        if ($processComposerSymlinks && array_key_exists('symlinks', $package->getExtra())) {
            $symlinks = $package->getExtra()['symlinks'];

            foreach ($symlinks as $fileLocation => $symlinkLocation) {
                // Do not process folders outside the project directory.
                if (substr($fileLocation, 0, 3) === "../") {
                    continue;
                }

                // Sanitize folder path.
                $fileLocation = ltrim($fileLocation, '.');
                $fileLocation = trim($fileLocation, '/');

                $symlinkLocation = ltrim($symlinkLocation, '.');
                $symlinkLocation = trim($symlinkLocation, '/');

                // If the symlink location has already been excluded, don't exclude the files.
                if (in_array($symlinkLocation, $foldersToExclude)) {
                    $event->getIO()->write(sprintf(
                        '<info>Skipping excluding "%s" because symlink "%s" is already excluded.</info>',
                        $fileLocation,
                        $symlinkLocation
                    ));
                    continue;
                }

                // If the folder suggested to exclude is a root folder, swap the entries.
                if (strpos($fileLocation, '/') === false) {
                    $foldersToExclude[] = $symlinkLocation;
                } else {
                    $foldersToExclude[] = $fileLocation;
                }
            }
        }

        // Check is Mozart installed, add its entries to $foldersToExclude

        if (array_key_exists('mozart', $package->getExtra())) {
            $mozart = $package->getExtra()['mozart'];

            if (isset($mozart['packages']) && is_array($mozart['packages'])) {
                foreach ($mozart['packages'] as $mozartPackage) {
                    $foldersToExclude[] = "vendor/$mozartPackage";
                }
            } else {
                foreach ($package->getRequires() as $packageRequires => $version) {
                    $foldersToExclude[] = "vendor/$packageRequires" ;
                }
            }
        }


        foreach ($foldersToExclude as $folderToExclude) {
            // Sanitize folder path.
            $folderToExclude = ltrim($folderToExclude, '.');
            $folderToExclude = trim($folderToExclude, '/');

            // Check folder exists before excluding it
            // TODO: If the folder is not found, check the case sensitivity.
            if (!file_exists("$rootPath/$folderToExclude")) {
                $event->getIO()->write(sprintf(
                    '<info>Folder "%s" not found – not processed for PhpStorm excludeFolder.</info>',
                    $folderToExclude
                ));
                continue;
            }

            $newNodeUrl = "file://\$MODULE_DIR$/$folderToExclude";

            $alreadyExcluded = false;

            // Check is it already excluded.
            foreach ($content->childNodes as $node) {

                /** @var \DOMNode $node */

                if ($node->nodeName == 'excludeFolder' && $node->getAttribute('url') === $newNodeUrl) {
                    $alreadyExcluded = true;
                    break;
                }
            }

            if ($alreadyExcluded) {
                $event->getIO()->write(sprintf(
                    '<info>PhpStorm config already excludes "%s".</info>',
                    $folderToExclude
                ));
            } else {
                $newNode = new \DOMElement('excludeFolder');

                $content->appendChild($newNode);
                $domWasModified = true;

                // Can only setAttribute after adding to the document.
                $newNode->setAttribute('url', $newNodeUrl);

                $event->getIO()->write(sprintf(
                    '<info>Added "%s" to PhpStorm config at "%s".</info>',
                    $folderToExclude,
                    $phpStormProjectConfig
                ));
            }
        }

        // Save the file.
        if ($domWasModified) {
            $filesystem->dumpFile($phpStormProjectConfig, $dom->saveXML());
        }
    }
}
