<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Repository\DeviceGroupRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: DeviceGroupRepository::class)]
#[ORM\Table(name: 'auto_js_device_group', options: ['comment' => '设备分组表'])]
#[UniqueEntity(fields: ['name'], message: '分组名称已存在')]
class DeviceGroup implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '分组名称'])]
    #[Assert\NotBlank(message: '分组名称不能为空')]
    #[Assert\Length(max: 100, maxMessage: '分组名称长度不能超过100个字符')]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '分组描述'])]
    #[Assert\Length(max: 500, maxMessage: '分组描述长度不能超过 {{ limit }} 个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '排序值', 'default' => 0])]
    #[IndexColumn]
    #[Assert\Type(type: 'integer', message: '排序值必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '排序值不能小于0')]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool', message: '是否启用必须是布尔值')]
    private bool $valid = true;

    /**
     * @var Collection<int, AutoJsDevice>
     */
    #[ORM\OneToMany(targetEntity: AutoJsDevice::class, mappedBy: 'deviceGroup', fetch: 'EXTRA_LAZY')]
    private Collection $autoJsDevices;

    public function __construct()
    {
        $this->autoJsDevices = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '未命名分组';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * @return Collection<int, AutoJsDevice>
     */
    public function getAutoJsDevices(): Collection
    {
        return $this->autoJsDevices;
    }

    public function addAutoJsDevice(AutoJsDevice $autoJsDevice): void
    {
        if (!$this->autoJsDevices->contains($autoJsDevice)) {
            $this->autoJsDevices->add($autoJsDevice);
            $autoJsDevice->setDeviceGroup($this);
        }
    }

    public function removeAutoJsDevice(AutoJsDevice $autoJsDevice): void
    {
        if ($this->autoJsDevices->removeElement($autoJsDevice)) {
            if ($autoJsDevice->getDeviceGroup() === $this) {
                $autoJsDevice->setDeviceGroup(null);
            }
        }
    }

    public function getDeviceCount(): int
    {
        return $this->autoJsDevices->count();
    }
}
