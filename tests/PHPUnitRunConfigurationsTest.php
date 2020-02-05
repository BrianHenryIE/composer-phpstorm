<?php

namespace BrianHenryIE\ComposerPhpStorm\Tests;

use BrianHenryIE\ComposerPhpStorm\PHPUnitRunConfigurations;
use Composer\Composer;
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PHPUnitRunConfigurationsTest extends TestCase
{
    /** @var Event */
    private $event;

    /** @var RootPackage */
    private $package;

    private $composer;

    /** @var NullIO|MockObject */
    private $io;

    /** @var Filesystem|MockObject */
    private $filesystem;

    protected function setUp(): void
    {
        $this->package = new RootPackage('package', 'version', 'prettyVersion');
        $composer = new Composer();
        $composer->setPackage($this->package);
        $this->composer = $composer;

        $this->io = $this->getMockBuilder(NullIO::class)->enableProxyingToOriginalMethods()->getMock();
        $this->event = new Event('event', $composer, $this->io);
        $this->filesystem = $this->createMock(Filesystem::class);
    }


    /**
     * If there is no PhpStorm project, print a message.
     */
    public function testNoPhpStorm()
    {
        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/NoPhpStorm '));

        $expected = '<info>PhpStorm project folder "' . getcwd()
            . '/tests/PHPUnitRunConfigurations/NoPhpStorm /.idea/" does not exist. '
            . 'Maybe this project has not been opened in PhpStorm yet.</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }

    public function testInProjectRoot()
    {
        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/InProjectRoot'));

        $expectedTerminalOutput = '<info>Added PHPStorm Run Configuration "PHPUnit" for `/phpunit.xml` to "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/InProjectRoot/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $fileToWrite = getcwd() . '/tests/PHPUnitRunConfigurations/InProjectRoot/.idea/workspace.xml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/PHPUnitRunConfigurations/InProjectRoot/expected-workspace.xml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }

    public function testInProjectSubfolder()
    {
        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/InProjectSubfolder'));

        $expectedTerminalOutput = '<info>Added PHPStorm Run Configuration "wordpress-develop" for `/tests/wordpress-develop/phpunit.xml` to "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/InProjectSubfolder/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $fileToWrite = getcwd() . '/tests/PHPUnitRunConfigurations/InProjectSubfolder/.idea/workspace.xml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/PHPUnitRunConfigurations/InProjectSubfolder/expected-workspace.xml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }

    /**
     * When there is an entry in PhpStorm's config but the phpunit.xml is missing in the filesystem.
     */
    public function testMissingFromFilesystem()
    {
        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/MissingFromFilesystem'));

        $expectedTerminalOutput = '<info>Removed PHPUnit Run Configuration "wordpress-develop" at `$PROJECT_DIR$/tests/wordpress-develop/phpunit.xml` from "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/MissingFromFilesystem/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $fileToWrite = getcwd() . '/tests/PHPUnitRunConfigurations/MissingFromFilesystem/.idea/workspace.xml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/PHPUnitRunConfigurations/MissingFromFilesystem/expected-workspace.xml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }

    /**
     * If it has already been configured, do not add it again.
     */
    public function testAlreadyPresent()
    {
        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/AlreadyPresent'));

        $expectedTerminalOutput = '<info>PhpStorm PHPUnit Run Configuration "wordpress-develop" for "/tests/wordpress-develop/phpunit.xml" already present in "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/AlreadyPresent/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $this->filesystem->expects($this->never())
            ->method('dumpFile');

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }

    /**
     * If no phpunit.xml files are found, exit gracefully.
     */
    public function testNone()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/None'));

        $this->io
            ->expects($this->never())
            ->method('write');

        $this->filesystem->expects($this->never())
            ->method('dumpFile');

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }


    /**
     * Don't add test configurations for packages in vendor folder.
     *
     * The IgnoreVendorFolder has a phpunit.xml in a vendor subfolder.
     */
    public function testIgnoreVendorFolder() {

        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/IgnoreVendorFolder'));

        $expectedTerminalOutput = '<info>Added PHPStorm Run Configuration "PHPUnit" for `/phpunit.xml` to "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreVendorFolder/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $fileToWrite = getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreVendorFolder/.idea/workspace.xml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreVendorFolder/expected-workspace.xml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }


    /**
     * Don't add test configurations for packages in wp-content folder.
     *
     * The IgnoreVendorFolder has a phpunit.xml in a vendor subfolder.
     */
    public function testIgnoreWpContentFolder() {

        $this->composer->setConfig(new Config(false, __DIR__ . '/PHPUnitRunConfigurations/IgnoreWpContentFolder'));

        $expectedTerminalOutput = '<info>Added PHPStorm Run Configuration "PHPUnit" for `/phpunit.xml` to "'
            . getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreWpContentFolder/.idea/workspace.xml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $fileToWrite = getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreWpContentFolder/.idea/workspace.xml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/PHPUnitRunConfigurations/IgnoreWpContentFolder/expected-workspace.xml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        PHPUnitRunConfigurations::update($this->event, $this->filesystem);
    }
}
