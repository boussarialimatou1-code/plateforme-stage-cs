<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190, unique: true)]
    private ?string $settingKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settingValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): ?string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): self
    {
        $this->settingValue = $settingValue;
        return $this;
    }
}
