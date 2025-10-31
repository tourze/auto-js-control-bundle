<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Repository\DeviceGroupRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceGroupRepository::class)]
#[RunTestsInSeparateProcesses]
final class DeviceGroupRepositoryTest extends AbstractRepositoryTestCase
{
    private DeviceGroupRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(DeviceGroup::class);
        $this->assertInstanceOf(DeviceGroupRepository::class, $repository);
        $this->repository = $repository;
    }

    #[Test]
    public function findActiveGroupsReturnsOnlyValidGroups(): void
    {
        // Clear existing data to ensure clean test environment
        $em = self::getEntityManager();
        $allGroups = $this->repository->findAll();
        foreach ($allGroups as $group) {
            $em->remove($group);
        }
        $em->flush();

        // Arrange
        $activeGroup1 = new DeviceGroup();
        $activeGroup1->setName('Active Group 1');
        $activeGroup1->setValid(true);
        $activeGroup1->setSortOrder(2);

        $activeGroup2 = new DeviceGroup();
        $activeGroup2->setName('Active Group 2');
        $activeGroup2->setValid(true);
        $activeGroup2->setSortOrder(1);

        $inactiveGroup = new DeviceGroup();
        $inactiveGroup->setName('Inactive Group');
        $inactiveGroup->setValid(false);
        $inactiveGroup->setSortOrder(3);

        $em->persist($activeGroup1);
        $em->persist($activeGroup2);
        $em->persist($inactiveGroup);
        $em->flush();

        // Act
        $groups = $this->repository->findActiveGroups();

        // Assert
        $this->assertCount(2, $groups);
        $groupNames = array_map(fn ($g) => $g->getName(), $groups);
        $this->assertContains('Active Group 1', $groupNames);
        $this->assertContains('Active Group 2', $groupNames);
        $this->assertNotContains('Inactive Group', $groupNames);

        // Check sorting order
        $this->assertEquals('Active Group 2', $groups[0]->getName()); // sortOrder = 1
        $this->assertEquals('Active Group 1', $groups[1]->getName()); // sortOrder = 2
    }

    #[Test]
    public function findActiveGroupsReturnsEmptyWhenNoActiveGroups(): void
    {
        // Clear existing data to ensure clean test environment
        $em = self::getEntityManager();
        $allGroups = $this->repository->findAll();
        foreach ($allGroups as $group) {
            $em->remove($group);
        }
        $em->flush();

        // Arrange
        $inactiveGroup = new DeviceGroup();
        $inactiveGroup->setName('Only Inactive Group');
        $inactiveGroup->setValid(false);

        $em->persist($inactiveGroup);
        $em->flush();

        // Act
        $groups = $this->repository->findActiveGroups();

        // Assert
        $this->assertEmpty($groups);
    }

    #[Test]
    public function testFindActiveGroupsShouldReturnValidGroupsOnly(): void
    {
        // Clear existing data to ensure clean test environment
        $em = self::getEntityManager();
        $allGroups = $this->repository->findAll();
        foreach ($allGroups as $group) {
            $em->remove($group);
        }
        $em->flush();

        // Arrange
        $activeGroup = new DeviceGroup();
        $activeGroup->setName('Active Test Group');
        $activeGroup->setValid(true);
        $activeGroup->setSortOrder(1);

        $inactiveGroup = new DeviceGroup();
        $inactiveGroup->setName('Inactive Test Group');
        $inactiveGroup->setValid(false);
        $inactiveGroup->setSortOrder(2);

        $em->persist($activeGroup);
        $em->persist($inactiveGroup);
        $em->flush();

        // Act
        $activeGroups = $this->repository->findActiveGroups();

        // Assert
        $this->assertIsArray($activeGroups);
        $this->assertCount(1, $activeGroups);
        $groupNames = array_map(fn ($g) => $g->getName(), $activeGroups);
        $this->assertContains('Active Test Group', $groupNames);
        $this->assertNotContains('Inactive Test Group', $groupNames);
    }

    #[Test]
    public function testSaveShouldPersistEntity(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Save Test Group');
        $group->setValid(true);
        $group->setSortOrder(100);

        // Act
        $this->repository->save($group);

        // Assert - Verify it's actually persisted
        $found = $this->repository->find($group->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Save Test Group', $found->getName());
        $this->assertTrue($found->isValid());
        $this->assertEquals(100, $found->getSortOrder());
    }

    #[Test]
    public function testRemoveShouldDeleteEntity(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Remove Test Group');
        $group->setValid(true);

        $em = self::getEntityManager();
        $em->persist($group);
        $em->flush();

        $groupId = $group->getId();
        $this->assertNotNull($groupId);

        // Act
        $this->repository->remove($group);

        // Assert
        $found = $this->repository->find($groupId);
        $this->assertNull($found);
    }

    #[Test]
    public function testFindByNullFieldShouldReturnEntitiesWithNullValues(): void
    {
        // Clear existing data to ensure clean test environment
        $em = self::getEntityManager();
        $allGroups = $this->repository->findAll();
        foreach ($allGroups as $group) {
            $em->remove($group);
        }
        $em->flush();

        // Arrange
        $groupWithDescription = new DeviceGroup();
        $groupWithDescription->setName('Group With Description');
        $groupWithDescription->setDescription('This group has description');
        $groupWithDescription->setValid(true);

        $groupWithoutDescription = new DeviceGroup();
        $groupWithoutDescription->setName('Group Without Description');
        $groupWithoutDescription->setValid(true);
        // description is null by default

        $em->persist($groupWithDescription);
        $em->persist($groupWithoutDescription);
        $em->flush();

        // Act
        $groupsWithoutDescription = $this->repository->findBy(['description' => null]);

        // Assert
        $this->assertIsArray($groupsWithoutDescription);
        $this->assertCount(1, $groupsWithoutDescription);
        $groupNames = array_map(fn ($g) => $g->getName(), $groupsWithoutDescription);
        $this->assertContains('Group Without Description', $groupNames);
        $this->assertNotContains('Group With Description', $groupNames);
    }

    #[Test]
    public function testCountByNullFieldShouldReturnCorrectCount(): void
    {
        // Arrange
        $groupWithDescription = new DeviceGroup();
        $groupWithDescription->setName('Count Null Group With Description');
        $groupWithDescription->setDescription('Has description');
        $groupWithDescription->setValid(true);

        $groupWithoutDescription1 = new DeviceGroup();
        $groupWithoutDescription1->setName('Count Null Group 1');
        $groupWithoutDescription1->setValid(true);

        $groupWithoutDescription2 = new DeviceGroup();
        $groupWithoutDescription2->setName('Count Null Group 2');
        $groupWithoutDescription2->setValid(true);

        $em = self::getEntityManager();
        $em->persist($groupWithDescription);
        $em->persist($groupWithoutDescription1);
        $em->persist($groupWithoutDescription2);
        $em->flush();

        // Act
        $countWithoutDescription = $this->repository->count(['description' => null]);

        // Assert - 至少包含我们创建的2个无描述的组
        $this->assertGreaterThanOrEqual(2, $countWithoutDescription);
    }

    protected function createNewEntity(): object
    {
        $entity = new DeviceGroup();
        $entity->setName('Test Device Group ' . uniqid());
        $entity->setValid(true);
        $entity->setSortOrder(1);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<DeviceGroup>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
