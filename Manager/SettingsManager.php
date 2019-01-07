<?php

namespace Harmony\Bundle\AdminBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;
use Harmony\Bundle\CoreBundle\Entity\Settings;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class SettingsManager
 *
 * @package Harmony\Bundle\AdminBundle\Manager
 */
class SettingsManager implements SettingsManagerInterface
{

    /** @var array $globalSettings */
    private $globalSettings;

    /** @var array $userSettings */
    private $userSettings;

    /** @var ObjectManager $em */
    private $em;

    /** @var EntityRepository $repository */
    private $repository;

    /**
     * @var array
     */
    private $settingsConfiguration;

    /**
     * @param ObjectManager $em
     * @param array         $settingsConfiguration
     */
    public function __construct(ObjectManager $em, array $settingsConfiguration = [])
    {
        $this->em                    = $em;
        $this->repository            = $em->getRepository(Settings::class);
        $this->settingsConfiguration = $settingsConfiguration;
    }

    /**
     * Returns setting value by its name.
     *
     * @param string             $name
     * @param UserInterface|null $user
     * @param mixed|null         $default value to return if the setting is not set
     *
     * @return mixed
     * @throws \Exception
     */
    public function get($name, UserInterface $user = null, $default = null)
    {
        $this->validateSetting($name, $user);
        $this->loadSettings($user);

        $value = null;

        switch ($this->settingsConfiguration[$name]['scope']) {
            case SettingsManagerInterface::SCOPE_GLOBAL:
                $value = $this->globalSettings[$name];
                break;
            case SettingsManagerInterface::SCOPE_ALL:
                $value = $this->globalSettings[$name];
            //Do not break here. Try to fetch the users settings
            case SettingsManagerInterface::SCOPE_USER:
                if (null !== $user) {
                    if ($this->userSettings[$user->getSettingIdentifier()][$name] !== null) {
                        $value = $this->userSettings[$user->getSettingIdentifier()][$name];
                    }
                }
                break;
        }

        return $value === null ? $default : $value;
    }

    /**
     * Returns all settings as associative name-value array.
     *
     * @param UserInterface|null $user
     *
     * @return array
     */
    public function all(UserInterface $user = null)
    {
        $this->loadSettings($user);

        if (null === $user) {
            return $this->globalSettings;
        }

        $settings = $this->userSettings[$user->getSettingIdentifier()];

        // If some user setting is not defined, please use the value from global
        foreach ($settings as $key => $value) {
            if ($value === null && isset($this->globalSettings[$key])) {
                $settings[$key] = $this->globalSettings[$key];
            }
        }

        return $settings;
    }

    /**
     * Sets setting value by its name.
     *
     * @param string             $name
     * @param mixed              $value
     * @param UserInterface|null $user
     *
     * @return SettingsManagerInterface
     * @throws \Exception
     */
    public function set($name, $value, UserInterface $user = null)
    {
        $this->setWithoutFlush($name, $value, $user);

        return $this->flush($name, $user);
    }

    /**
     * Sets settings' values from associative name-value array.
     *
     * @param array              $settings
     * @param UserInterface|null $user
     *
     * @return SettingsManagerInterface
     * @throws \Exception
     */
    public function setMany(array $settings, UserInterface $user = null)
    {
        foreach ($settings as $name => $value) {
            $this->setWithoutFlush($name, $value, $user);
        }

        return $this->flush(array_keys($settings), $user);
    }

    /**
     * Clears setting value.
     *
     * @param string             $name
     * @param UserInterface|null $user
     *
     * @return SettingsManagerInterface
     * @throws \Exception
     */
    public function clear($name, UserInterface $user = null)
    {
        return $this->set($name, null, $user);
    }

    /**
     * Sets setting value to private array. Used for settings' batch saving.
     *
     * @param string             $name
     * @param mixed              $value
     * @param UserInterface|null $user
     *
     * @return SettingsManager
     * @throws \Exception
     */
    private function setWithoutFlush($name, $value, UserInterface $user = null)
    {
        $this->validateSetting($name, $user);
        $this->loadSettings($user);

        if ($user === null) {
            $this->globalSettings[$name] = $value;
        } else {
            $this->userSettings[$user->getSettingIdentifier()][$name] = $value;
        }

        return $this;
    }

    /**
     * Flushes settings defined by $names to database.
     *
     * @param string|array       $names
     * @param UserInterface|null $user
     *
     * @return SettingsManager
     */
    private function flush($names, UserInterface $user = null)
    {
        $names = (array)$names;

        $settings = $this->repository->findBy([
            'name'    => $names,
            'ownerId' => $user === null ? null : $user->getSettingIdentifier(),
        ]);

        // Assert: $settings might be a smaller set than $names

        // For each settings that you are trying to save
        foreach ($names as $name) {
            try {
                $value = $this->get($name, $user);
            }
            catch (\Exception $e) {
                continue;
            }

            /** @var Settings $setting */
            $setting = $this->findSettingByName($settings, $name);

            if (!$setting) {
                // if the setting does not exist in DB, create it
                $setting = new Settings();
                $setting->setName($name);
                if ($user !== null) {
                    $setting->setOwnerId($user->getSettingIdentifier());
                }
                $this->em->persist($setting);
            }

            $setting->setValue(serialize($value));
        }

        $this->em->flush();

        return $this;
    }

    /**
     * Find a setting by name form an array of settings.
     *
     * @param Settings[] $haystack
     * @param string     $needle
     *
     * @return Settings|null
     */
    protected function findSettingByName($haystack, $needle)
    {
        foreach ($haystack as $setting) {
            if ($setting->getName() === $needle) {
                return $setting;
            }
        }
    }

    /**
     * Checks that $name is valid setting and it's scope is also valid.
     *
     * @param string        $name
     * @param UserInterface $user
     *
     * @return SettingsManager
     * @throws \Exception
     */
    private function validateSetting($name, UserInterface $user = null)
    {
        // Name validation
        if (!is_string($name) || !array_key_exists($name, $this->settingsConfiguration)) {
            throw new \Exception($name);
        }

        // Scope validation
        $scope = $this->settingsConfiguration[$name]['scope'];
        if ($scope !== SettingsManagerInterface::SCOPE_ALL) {
            if ($scope === SettingsManagerInterface::SCOPE_GLOBAL && $user !== null ||
                $scope === SettingsManagerInterface::SCOPE_USER && $user === null) {
                throw new \Exception($scope, $name);
            }
        }

        return $this;
    }

    /**
     * Settings lazy loading.
     *
     * @param UserInterface|null $user
     *
     * @return SettingsManager
     */
    private function loadSettings(UserInterface $user = null)
    {
        // Global settings
        if ($this->globalSettings === null) {
            $this->globalSettings = $this->getSettingsFromRepository();
        }

        // User settings
        if ($user !== null &&
            ($this->userSettings === null || !array_key_exists($user->getSettingIdentifier(), $this->userSettings))) {
            $this->userSettings[$user->getSettingIdentifier()] = $this->getSettingsFromRepository($user);
        }

        return $this;
    }

    /**
     * Retreives settings from repository.
     *
     * @param UserInterface|null $user
     *
     * @return array
     */
    private function getSettingsFromRepository(UserInterface $user = null)
    {
        $settings = [];

        foreach (array_keys($this->settingsConfiguration) as $name) {
            try {
                $this->validateSetting($name, $user);
                $settings[$name] = null;
            }
            catch (\Exception $e) {
                continue;
            }
        }

        /** @var Settings $setting */
        foreach ($this->repository->findBy([
            'ownerId' => $user === null ? null : $user->getSettingIdentifier()
        ]) as $setting) {
            if (array_key_exists($setting->getName(), $settings)) {
                $settings[$setting->getName()] = unserialize($setting->getValue());
            }
        }

        return $settings;
    }
}