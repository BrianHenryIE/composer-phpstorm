<?php

namespace BrianHenryIE\ComposerPhpStorm\Tests;

use BrianHenryIE\ComposerPhpStorm\ExcludeFolders;
use Composer\Composer;
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ExcludeFoldersTest extends TestCase
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

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/NoPhpStorm '));

        $expected = '<info>PhpStorm project folder "'
            . getcwd() . '/tests/ExcludeFolders/NoPhpStorm /.idea/" does not exist. '
            . 'Maybe this project has not been opened in PhpStorm yet.</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * If the PhpStorm project folder does not contain the expected .iml file, notify the user.
     */
    public function testNoPhpStormIml()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/NoPhpStormIML'));

        $expected = '<info>No PhpStorm .iml file found in "'
            . getcwd() . '/tests/ExcludeFolders/NoPhpStormIML/.idea/".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * If there is more than one .iml file found, this tool doesn't know what to do.
     */
    public function testTwoImlFiles()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/TwoIMLFiles'));

        $expected = '<info>Unexpectedly found more than one .iml file. '
            . 'Please open an issue at GitHub.com/BrianHenryIE/composer-phpstorm</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * If the .iml file is not valid XML, fail gracefully, with a notice.
     */
    public function testInvalidImlFile()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/InvalidIMLFile'));

        $expected = '<info>Could not parse XML for PhpStorm .iml file at "'
            . getcwd() . '/tests/ExcludeFolders/InvalidIMLFile/.idea/invalid.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * A simple addition to a simple .iml file.
     */
    public function testValidIml()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/ValidIMLFile'));

        $expectedTerminalOutput = '<info>Added "foldertoexclude" to PhpStorm config at "'
            . getcwd() . '/tests/ExcludeFolders/ValidIMLFile/.idea/valid.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/ValidIMLFile/.idea/valid.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/ValidIMLFile/expected.iml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * Fail gently when the Composer config is not set (Mozart and symlinks will still apply).
     */
    public function testNoComposerExtrasConfig()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/ValidIMLFile'));

        $this->io
            ->expects($this->never())
            ->method('write');

        $this->filesystem->expects($this->never())
            ->method('dumpFile');

        ExcludeFolders::update($this->event, $this->filesystem);
    }


    /**
     * If the folder does not exist, do not modify the PhpStorm .iml file with dirty data.
     */
    public function testFolderDoesNotExist()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/ValidIMLFile'));

        $expectedTerminalOutput
            = '<info>Folder "thisfolderdoesnotexist" not found – not processed for PhpStorm excludeFolder.</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "thisfolderdoesnotexist" ]
                ]
            ]
        ]);

        $this->filesystem->expects($this->never())
            ->method('dumpFile');

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * Users might begin folder names with `/`, `./`.
     *
     * Strip leading `.` and leading and trailing `/`.
     */
    public function testSanitizeInput()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/SanitizeInput'));

        $this->io
            ->expects($this->exactly(4))
            ->method('write');

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [
                        'foldertoexclude',
                        './foldertoexclude2',
                        '/foldertoexclude3',
                        'foldertoexclude4/'
                    ]
                ]
            ]
        ]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/SanitizeInput/.idea/valid.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/SanitizeInput/expected.iml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * If the folder is already in PhpStorm 's excludedFolder list, do not add it.
     */
    public function testAlreadyExcluded()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/FolderAlreadyExcluded'));

        $expected = '<info>PhpStorm config already excludes "foldertoexclude".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expected]
            );

        $this->package->setExtra([
            "phpstorm" => [
                "exclude_folders" => [
                    "folders" => [ "foldertoexclude" ]
                ]
            ]
        ]);


        $this->filesystem->expects($this->never())
            ->method('dumpFile');


        ExcludeFolders::update($this->event, $this->filesystem);
    }


    /**
     * If Mozart is installed, exclude packages managed by it.
     *
     * Mozart is a Composer tool to prefix packages' namespaces, => all classes are duplicated.
     *
     * @see https://github.com/coenjacobs/mozart
     */
    public function testMozartWithDefinedPackages()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/MozartWithPackages'));

        $expectedTerminalOutput = '<info>Added "vendor/pimple/pimple" to PhpStorm config at "'
            . getcwd() . '/tests/ExcludeFolders/MozartWithPackages/.idea/mozart.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $this->package->setExtra([
            "mozart" => [
                "dep_namespace" => "CoenJacobs\\TestProject\\Dependencies\\",
                "dep_directory" => "/src/Dependencies/",
                "classmap_directory" => "/classes/dependencies/",
                "classmap_prefix" => "CJTP_",
                "packages" => [
                    "pimple/pimple"
                ]
            ]
        ]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/MozartWithPackages/.idea/mozart.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/MozartWithPackages/expected.iml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * A PR is open to allow using Mozart without explicitly listing the packages, instead
     * to use the full list of composer's required packages.
     *
     * @see https://github.com/coenjacobs/mozart/pull/34
     */
    public function testMozartWithoutDefinedPackages()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/MozartWithoutPackages'));

        $expectedTerminalOutput = '<info>Added "vendor/example/require" to PhpStorm config at "'
            . getcwd() . '/tests/ExcludeFolders/MozartWithoutPackages/.idea/mozart.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        // Note 'packages' absent. Other values aren't relevant.
        $this->package->setExtra([
            "mozart" => [
                "dep_namespace" => "CoenJacobs\\TestProject\\Dependencies\\",
                "dep_directory" => "/src/Dependencies/",
                "classmap_directory" => "/classes/dependencies/",
                "classmap_prefix" => "CJTP_"
            ]
        ]);

        $this->package->setRequires(["example/require" => "~1.0"]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/MozartWithoutPackages/.idea/mozart.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/MozartWithoutPackages/expected.iml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * Check for symlinks made by kporras07/composer-symlinks, i.e. to exclude any folders that
     * then occur twice in the project.
     *
     * @see https://github.com/kporras07/composer-symlinks
     */
    public function testSymlinks()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/Symlinks'));

        $expectedTerminalOutput = '<info>Added "vendor/sample/package" to PhpStorm config at "'
            . getcwd() . '/tests/ExcludeFolders/Symlinks/.idea/valid.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        $this->package->setExtra([
            "symlinks" => [
                "vendor/sample/package" => "symlink"
            ]
        ]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/Symlinks/.idea/valid.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/Symlinks/expected.iml');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * If a symlink begins with ../ then do not exclude its folder.
     */
    public function testSymlinkOutsideProject()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/Symlinks'));

        $this->io
            ->expects($this->never())
            ->method('write');

        $this->package->setExtra([
            "symlinks" => [
                "../path/outside/project" => "should/ignore"
            ]
        ]);

        $this->filesystem->expects($this->never())
            ->method('dumpFile');

        ExcludeFolders::update($this->event, $this->filesystem);
    }

    /**
     * When a folder is autodetected as a symlink destination, don't exclude it if it is in the root of the project.
     * Instead, exclude the symlink.
     */
    public function testRootFolderInclude()
    {

        $this->composer->setConfig(new Config(false, __DIR__ . '/ExcludeFolders/RootFolderInclude'));

        $expectedTerminalOutput = '<info>Added "subdir/project-name" to PhpStorm config at "'
                                  . getcwd() . '/tests/ExcludeFolders/RootFolderInclude/.idea/valid.iml".</info>';

        $this->io
            ->expects($this->exactly(1))
            ->method('write')
            ->withConsecutive(
                [$expectedTerminalOutput]
            );

        // Create a symlink to the src directory inside a subdir.
        // e.g. in WordPress development, create inside wp-content/plugins.
        $this->package->setExtra([
            "symlinks" => [
                "src" => "subdir/project-name"
            ]
        ]);

        $fileToWrite = getcwd() . '/tests/ExcludeFolders/RootFolderInclude/.idea/valid.iml';
        $expectedFileOutput = file_get_contents(getcwd() . '/tests/ExcludeFolders/RootFolderInclude/expected.iml');

        $this->filesystem->expects($this->once())
                         ->method('dumpFile')
                         ->with($fileToWrite, $expectedFileOutput);

        ExcludeFolders::update($this->event, $this->filesystem);
    }
}
