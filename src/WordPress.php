<?php

/**
 * If WordPress is installed, enable WordPress support in PhpStorm.
 *
 * Checks `extra`.`wordpress-install-dir` for custom `johnpbloch/wordpress-core-installer` location.
 * Checks for a `wordpress` directory in the project root.
 * Checks for a `wp` directory in the project root.
 * Checks for `vendor/wordpress/wordpress/src`.
 * Checks for `../wordpress` from the project root.
 * Checks for `../wp` from the project root.
 *
 * @see https://github.com/johnpbloch/wordpress-core-installer
 *
 * @package brianhenryie/composer-phpstorm
 */

namespace BrianHenryIE\ComposerPhpStorm;

use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * <?xml version="1.0" encoding="UTF-8"?>
 * <project version="4">
 *   <component name="WordPressConfiguration" enabled="true">
 *     <wordpressPath>$PROJECT_DIR$/../wp-module-data/wordpress</wordpressPath>
 *   </component>
 * </project>
 */
class WordPress extends PhpStorm
{
    public static function update(Event $event, ?Filesystem $filesystem = null)
    {

        $filesystem = $filesystem ?: new Filesystem();

        try {
            $dom = self::getWorkspaceDom($event, $filesystem);
        } catch (\Exception $e) {
            $event->getIO()->write($e->getMessage());
            return;
        }

        /** @var \DOMElement $root */
        $project = $dom->documentElement;

        $wordPressConfigurationNode = self::findOrCreateComponentNodeNamed('WordPressConfiguration', $project);

        if ($wordPressConfigurationNode->childNodes->length !== 0) {
            $event->getIO()->debug('Already configured.');
            // Already configured.
            return;
        }

        $wordPressLocation = self::findWordPress($event);

        if (is_null($wordPressLocation)) {
            $event->getIO()->debug('WordPress not found in project.');
            return;
        }

        $wordPressPathNode = new \DOMElement('wordpressPath');
        $wordPressPathNode->textContent = '$PROJECT_DIR$/' . $wordPressLocation;

        $wordPressConfigurationNode->appendChild($wordPressPathNode);

        $event->getIO()->debug('Added <wordpressPath>$PROJECT_DIR$/' . $wordPressLocation . '</wordpressPath>');

        $wordPressConfigurationNode->setAttribute('enabled', "true");

        $filesystem->dumpFile(self::$phpStormWorkspaceXml, $dom->saveXML());

        $event->getIO()->debug('Saved to ' . self::$phpStormWorkspaceXml);

        $event->getIO()->debug($dom->saveXML());
    }

    protected static function findWordPress(Event $event): ?string
    {
        /** @var PackageInterface $package */
        $package = $event->getComposer()->getPackage();

        $locations = [
             'wordpress',
             'wp',
             'vendor/wordpress/wordpress/src',
             '../wordpress',
             '../wp'
        ];

        if (isset($package->getExtra()['wordpress-install-dir'])) {
            foreach (
                array_reverse(
                    (array) $package->getExtra()['wordpress-install-dir']
                ) as $johnpblochWordPressCoreInstaller
            ) {
                array_unshift($locations, $johnpblochWordPressCoreInstaller);
            }
        }

        foreach ($locations as $location) {
            $event->getIO()->debug('Checking: ' . self::$rootPath . '/' . $location . '/wp-load.php');
            if (file_exists(self::$rootPath . '/' . $location . '/wp-load.php')) {
                return $location;
            }
        }

        return null;
    }
}
