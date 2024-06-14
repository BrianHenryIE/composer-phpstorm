<?php

//

// search for phpunit.xml and add them to configurations
// use phpunit as the name for / folder
// use the folder name for other .xml files
// allow ["name":"path/to/phpunit.xml"] for naming

// bootstrap?

/*

workspace.xml

<?xml version="1.0" encoding="UTF-8"?>
<project version="4">
  <component name="RunManager" selected="PHPUnit.phpunit.xml">
    <configuration name="wordpress-develop" type="PHPUnitRunConfigurationType" factoryName="PHPUnit">
      <TestRunner configuration_file="$PROJECT_DIR$/tests/wordpress-develop/phpunit.xml"
        scope="XML" use_alternative_configuration_file="true" />
      <method v="2" />
    </configuration>


*/


namespace BrianHenryIE\ComposerPhpStorm;

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;

class PHPUnitRunConfigurations extends PhpStorm
{
    public static function update(Event $event, Filesystem $filesystem = null)
    {
        $filesystem = $filesystem ?: new Filesystem();

        try {
            $dom = self::getWorkspaceDom($event, $filesystem);
        } catch (\Exception $e) {
            $event->getIO()->write($e->getMessage());
            return;
        }

        $domWasModified = false;

        /** @var \DOMElement $root */
        $project = $dom->documentElement;

        $discoveredPhpUnitFiles = array();

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$rootPath)) as $file) {
            if ($file->getFilename() === 'phpunit.xml') {
                $discoveredPhpUnitFiles[] = $file->getPath() . '/' . $file->getFilename();
            }
        }

        $runManagerNode = self::findOrCreateComponentNodeNamed('RunManager', $project);

        // Remove any Run Configurations for missing phpunit.xml files.
        foreach ($runManagerNode->childNodes as $configurationNode) {
            foreach ($configurationNode->childNodes as $grandchildNode) {
                // Then this is a TestRunner node
                if ($grandchildNode->hasAttribute('configuration_file')) {
                    $nodePhpUnitFileProjectDir = $grandchildNode->getAttribute('configuration_file');
                    $nodePhpUnitFileFilesystem = str_replace(
                        '$PROJECT_DIR$',
                        self::$rootPath,
                        $nodePhpUnitFileProjectDir
                    );
                    if (!file_exists($nodePhpUnitFileFilesystem)) {
                        $nodeName = $configurationNode->getAttribute('name');

                        $runManagerNode->removeChild($configurationNode);
                        $domWasModified = true;

                        $event->getIO()->write(sprintf(
                            '<info>Removed PHPUnit Run Configuration "%s" at `%s` from "%s".</info>',
                            $nodeName,
                            $nodePhpUnitFileProjectDir,
                            self::$phpStormWorkspaceXml
                        ));
                    }
                }
            }
        }

        foreach ($discoveredPhpUnitFiles as $phpUnitFile) {
            // Replace the full path with the project relative path.

            $phpUnitFile = str_replace(self::$rootPath, '$PROJECT_DIR$', $phpUnitFile);
            $phpUnitFileRegistered = false;

            // Ignore vendor subfolder.
            if (preg_match('/^\$PROJECT_DIR\$\/vendor/', $phpUnitFile)) {
                continue;
            }

            // Ignore wp-content subfolder.
            if (preg_match('/^\$PROJECT_DIR\$\/wp-content/', $phpUnitFile)) {
                continue;
            }

            // Check the RunManager's children to see if we've already added this PHPUnit.xml.
            foreach ($runManagerNode->childNodes as $node) {

                /** @var \DOMNode $node */

                // Check all child nodes for node of type <configuration type="PHPUnitRunConfigurationType">
                if (
                    $node->nodeName == 'configuration'
                    && $node->getAttribute('type') === "PHPUnitRunConfigurationType"
                ) {
                    foreach ($node->childNodes as $grandchildNode) {
                        if ($grandchildNode->nodeName == 'TestRunner') {
                            if ($grandchildNode->getAttribute('configuration_file') === $phpUnitFile) {
                                $phpUnitFileRegistered = true;
                            }
                        }

                        break;
                    }
                }
            }


            $configurationName = '$PROJECT_DIR$' === dirname($phpUnitFile)
                ? 'PHPUnit' : basename(dirname($phpUnitFile));

            if ($phpUnitFileRegistered) {
                $event->getIO()->write(sprintf(
                    '<info>PhpStorm PHPUnit Run Configuration "%s" for "%s" already present in "%s".</info>',
                    $configurationName,
                    str_replace('$PROJECT_DIR$', '', $phpUnitFile),
                    self::$phpStormWorkspaceXml
                ));
            } else {
                $newConfigurationNode = new \DOMElement('configuration');
                $newTestRunnerNode = new \DOMElement('TestRunner');
                $newMethodNode = new \DOMElement('method');

                $runManagerNode->appendChild($newConfigurationNode);
                $domWasModified = true;

                $newConfigurationNode->appendChild($newTestRunnerNode);
                $newConfigurationNode->appendChild($newMethodNode);

                // Can only setAttribute after adding to the document.
                $newConfigurationNode->setAttribute('name', $configurationName);
                $newConfigurationNode->setAttribute('type', 'PHPUnitRunConfigurationType');
                $newConfigurationNode->setAttribute('factoryName', 'PHPUnit');

                $newTestRunnerNode->setAttribute('configuration_file', $phpUnitFile);
                $newTestRunnerNode->setAttribute('scope', 'XML');
                $newTestRunnerNode->setAttribute('use_alternative_configuration_file', 'true');

                $newMethodNode->setAttribute('v', '2');

                $event->getIO()->write(sprintf(
                    '<info>Added PHPStorm Run Configuration "%s" for `%s` to "%s".</info>',
                    $configurationName,
                    str_replace('$PROJECT_DIR$', '', $phpUnitFile),
                    self::$phpStormWorkspaceXml
                ));
            }
        }

        // Save the file.
        if ($domWasModified) {
            if ($runManagerNode->childNodes->length === 0) {
                $project->removeChild($runManagerNode);
            }

            $filesystem->dumpFile(self::$phpStormWorkspaceXml, $dom->saveXML());
        }
    }
}
