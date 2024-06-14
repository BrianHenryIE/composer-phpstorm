<?php

/**
 * Functions to get the `.iml` and `workspace.xml` files from `.idea` directory.
 */

namespace BrianHenryIE\ComposerPhpStorm;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use DOMDocument;
use Exception;
use Symfony\Component\Filesystem\Filesystem;

abstract class PhpStorm
{
    /**
     * @var string
     */
    protected static $phpStormWorkspaceXml;
    /**
     * @var string
     */
    protected static $rootPath;

    protected static function getWorkspaceDom(Event $event, Filesystem $filesystem): DOMDocument
    {
        /** @var PackageInterface $package */
        $package = $event->getComposer()->getPackage();
        /** @var Config $config */
        $config = $event->getComposer()->getConfig();

        $vendorPath = $config->get('vendor-dir');
        self::$rootPath = dirname($vendorPath);

        // Find workspace.xml file in .idea folder
        $phpStormProjectFolder = self::$rootPath . '/.idea/';

        if (!file_exists($phpStormProjectFolder)) {
            throw new Exception(sprintf(
                '<info>PhpStorm project folder "%s" does not exist. '
                . 'Maybe this project has not been opened in PhpStorm yet.</info>',
                $phpStormProjectFolder
            ));
        }

        self::$phpStormWorkspaceXml = $phpStormProjectFolder . 'workspace.xml';

        if (!file_exists(self::$phpStormWorkspaceXml)) {
            throw new Exception(sprintf(
                '<info>No PhpStorm workspace.xml file found in "%s".</info>',
                $phpStormProjectFolder
            ));
        }

        $dom = new DOMDocument();


        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $fileIsValid = @$dom->load(self::$phpStormWorkspaceXml);

        if (!$fileIsValid) {
            throw new Exception(sprintf(
                '<info>Could not parse XML for PhpStorm workspace.xml file at "%s".</info>',
                $phpStormProjectFolder
            ));
        }

        return $dom;
    }

    protected static function findOrCreateComponentNodeNamed(string $componentNodeName, $project)
    {

        // Find the <component name="RunManager" > node and create if absent
        $runManagerNode = null;
        foreach ($project->childNodes as $node) {

            /** @var \DOMNode $node */

            if ($node->nodeName == 'component' && $node->getAttribute('name') === $componentNodeName) {
                $runManagerNode = $node;
                break;
            }
        }
        if (is_null($runManagerNode)) {
            $newNode = new \DOMElement('component');

            $project->appendChild($newNode);

            // Can only setAttribute after adding to the document.
            $newNode->setAttribute('name', $componentNodeName);

            $runManagerNode = $newNode;
        }

        return $runManagerNode;
    }
}
