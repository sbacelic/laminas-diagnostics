<?php

/**
 * @see       https://github.com/laminas/laminas-diagnostics for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diagnostics/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diagnostics/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Diagnostics;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\AvailableMigrationsSet;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\ExecutedMigrationsList;
use Doctrine\Migrations\Metadata\Storage\MetadataStorage;
use Doctrine\Migrations\MigrationsRepository;
use Doctrine\Migrations\Version\Version;
use Generator;
use Laminas\Diagnostics\Check\DoctrineMigration;
use Laminas\Diagnostics\Result\FailureInterface;
use Laminas\Diagnostics\Result\SuccessInterface;
use PHPUnit\Framework\TestCase;

class DoctrineMigrationTest extends TestCase
{
    /**
     * @dataProvider provideMigrationTestCases
     */
    public function testDoctrineMigrationsVersion3(
        array $availableVersions,
        array $migratedVersions,
        string $expectedResult
    ) {
        if (! $this->isDoctrineVersion3Installed()) {
            self::markTestSkipped('Doctrine Version 3 is not installed, skipping test.');
        }

        $migrationRepository = $this->getMockBuilder(MigrationsRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $migrationMock = $this->createMock(AbstractMigration::class);
        $availableMigrations = array_map(static function ($version) use ($migrationMock) {
            return new AvailableMigration(new Version($version), $migrationMock);
        }, $availableVersions);

        $migrationRepository
            ->expects(self::once())
            ->method('getMigrations')
            ->willReturn(new AvailableMigrationsSet($availableMigrations));

        $metadataStorage = $this->getMockBuilder(MetadataStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $executedMigrations = array_map(static function ($version) {
            return new ExecutedMigration(new Version($version));
        }, $migratedVersions);

        $metadataStorage
            ->expects(self::once())
            ->method('getExecutedMigrations')
            ->willReturn(new ExecutedMigrationsList($executedMigrations));

        $dependencyFactory = $this->getMockBuilder(DependencyFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dependencyFactory
            ->expects(self::once())
            ->method('getMigrationRepository')
            ->willReturn($migrationRepository);

        $dependencyFactory
            ->expects(self::once())
            ->method('getMetadataStorage')
            ->willReturn($metadataStorage);

        $check = new DoctrineMigration($dependencyFactory);
        $result = $check->check();

        self::assertInstanceof($expectedResult, $result);
    }

    /**
     * @dataProvider provideMigrationTestCases
     */
    public function testDoctrineMigrationsVersion2(
        array $availableVersions,
        array $migratedVersions,
        string $expectedResult
    ) {
        if (! $this->isDoctrineVersion2Installed()) {
            self::markTestSkipped('Doctrine Version 2 is not installed, skipping test.');
        }

        $configuration = $this->getMockBuilder(\Doctrine\Migrations\Configuration\Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configuration
            ->expects(self::once())
            ->method('getAvailableVersions')
            ->willReturn($availableVersions);

        $configuration
            ->expects(self::once())
            ->method('getMigratedVersions')
            ->willReturn($migratedVersions);

        $check = new DoctrineMigration($configuration);
        $result = $check->check();

        self::assertInstanceof($expectedResult, $result);
    }

    /**
     * @dataProvider provideMigrationTestCases
     */
    public function testDoctrineMigrationsVersion1(
        array $availableVersions,
        array $migratedVersions,
        string $expectedResult
    ) {
        if (! $this->isDoctrineVersion1Installed()) {
            self::markTestSkipped('Doctrine Version 1 is not installed, skipping test.');
        }

        $configuration = $this->getMockBuilder(\Doctrine\DBAL\Migrations\Configuration\Configuration::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configuration
            ->expects(self::once())
            ->method('getAvailableVersions')
            ->willReturn($availableVersions);

        $configuration
            ->expects(self::once())
            ->method('getMigratedVersions')
            ->willReturn($migratedVersions);

        $check = new DoctrineMigration($configuration);
        $result = $check->check();

        self::assertInstanceof($expectedResult, $result);
    }

    public function testThrowsExceptionForInvalidInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Argument for DoctrineMigration check.');

        new DoctrineMigration(new \stdClass());
    }

    public function provideMigrationTestCases()
    {
        yield 'everything migrated' => [
            ['Version1', 'Version2'],
            ['Version1', 'Version2'],
            SuccessInterface::class
        ];
        yield 'not all migration migrated' => [
            ['Version1', 'Version2'],
            ['Version1'],
            FailureInterface::class
        ];
        yield 'not existing migration migrated' => [
            ['Version1'],
            ['Version1', 'Version2'],
            FailureInterface::class
        ];
    }

    private function isDoctrineVersion1Installed()
    {
        return class_exists('\Doctrine\DBAL\Migrations\Configuration\Configuration');
    }

    private function isDoctrineVersion2Installed()
    {
        return class_exists('\Doctrine\Migrations\Configuration\Configuration') &&
            ! class_exists('\Doctrine\DBAL\Migrations\Configuration\Configuration') &&
            ! interface_exists(MigrationsRepository::class);
    }

    private function isDoctrineVersion3Installed()
    {
        return interface_exists(MigrationsRepository::class);
    }
}
