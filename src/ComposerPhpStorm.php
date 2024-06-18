<?php

namespace BrianHenryIE\ComposerPhpStorm;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

class ComposerPhpStorm implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => array('onInstallUpdate', 0),
            ScriptEvents::POST_UPDATE_CMD => array('onInstallUpdate', 0),
        );
    }

    public static function onInstallUpdate(\Composer\Script\Event $event)
    {
        ExcludeFolders::update($event);
        PHPUnitRunConfigurations::update($event);
        WordPress::update($event);
    }
}
