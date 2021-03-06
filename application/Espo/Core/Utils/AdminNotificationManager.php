<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2017 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Utils;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Util;

class AdminNotificationManager
{
    private $container;

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    protected function getLanaguage()
    {
        return $this->getContainer()->get('language');
    }

    public function getNotificationList()
    {
        $notificationList = [];

        if ($this->getConfig()->get('adminNotificationsDisabled')) {
            return [];
        }

        if (!$this->getConfig()->get('adminNotificationCronIsNotConfiguredDisabled')) {
            if (!$this->isCronConfigured()) {
                $notificationList[] = array(
                    'id' => 'cronIsNotConfigured',
                    'type' => 'cronIsNotConfigured',
                    'message' => $this->getLanaguage()->translate('cronIsNotConfigured', 'messages', 'Admin')
                );
            }
        }

        if (!$this->getConfig()->get('adminNotificationNewVersionIsAvailableDisabled')) {
            $instanceNeedingUpgrade = $this->getInstanceNeedingUpgrade();
            if (!empty($instanceNeedingUpgrade)) {
                $message = $this->getLanaguage()->translate('newVersionIsAvailable', 'messages', 'Admin');
                $notificationList[] = array(
                    'id' => 'newVersionIsAvailable',
                    'type' => 'newVersionIsAvailable',
                    'message' => $this->prepareMessage($message, $instanceNeedingUpgrade)
                );
            }
        }

        if (!$this->getConfig()->get('adminNotificationNewExtensionVersionIsAvailableDisabled')) {
            $extensionsNeedingUpgrade = $this->getExtensionsNeedingUpgrade();
            if (!empty($extensionsNeedingUpgrade)) {
                foreach ($extensionsNeedingUpgrade as $extensionName => $extensionDetails) {
                    $message = $this->getLanaguage()->translate('newExtensionVersionIsAvailable', 'messages', 'Admin');
                    $notificationList[] = array(
                        'id' => 'newExtensionVersionIsAvailable' . Util::toCamelCase($extensionName, ' ', true),
                        'type' => 'newExtensionVersionIsAvailable',
                        'message' => $this->prepareMessage($message, $extensionDetails)
                    );
                }
            }
        }

        return $notificationList;
    }

    protected function isCronConfigured()
    {
        return $this->getContainer()->get('scheduledJob')->isCronConfigured();
    }

    protected function getInstanceNeedingUpgrade()
    {
        $config = $this->getConfig();

        $latestVersion = $config->get('latestAvailableVersion');
        if (isset($latestVersion)) {
            $currentVersion = $config->get('version');
            if (version_compare($latestVersion, $currentVersion, '>')) {
                return array(
                    'currentVersion' => $currentVersion,
                    'latestVersion' => $latestVersion
                );
            }
        }
    }

    protected function getExtensionsNeedingUpgrade()
    {
        $config = $this->getConfig();

        $extensions = [];

        $latestAvailableExtensionsVersions = $config->get('latestAvailableExtensionsVersions');
        if (!empty($latestAvailableExtensionsVersions) && is_array($latestAvailableExtensionsVersions)) {
            foreach ($latestAvailableExtensionsVersions as $extensionName => $extensionLatestVersion) {
                $currentVersion = $this->getExtensionLatestInstalledVersion($extensionName);
                if (isset($currentVersion) && version_compare($extensionLatestVersion, $currentVersion, '>')) {
                    $extensions[$extensionName] = array(
                        'currentVersion' => $currentVersion,
                        'latestVersion' => $extensionLatestVersion,
                        'extensionName' => $extensionName,
                    );
                }
            }
        }

        return $extensions;
    }

    protected function getExtensionLatestInstalledVersion($extensionName)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $query = "
            SELECT version FROM extension
            WHERE name='". $extensionName ."'
            AND deleted=0
            AND is_installed = 1
            ORDER BY created_at DESC
        ";
        $sth = $pdo->prepare($query);
        $sth->execute();

        $row = $sth->fetch(\PDO::FETCH_ASSOC);
        if (isset($row['version'])) {
            return $row['version'];
        }
    }

    /**
     * Create EspoCRM notification for a user
     *
     * @param  string $message
     * @param  string $userId
     *
     * @return void
     */
    public function createNotification($message, $userId = '1')
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set(array(
            'type' => 'message',
            'data' => array(
                'userId' => $this->getUser()->id,
                'userName' => $this->getUser()->get('name')
            ),
            'userId' => $user->id,
            'message' => $actionData['messageTemplate']
        ));
        $this->getEntityManager()->saveEntity($notification);
    }

    protected function prepareMessage($message, array $data = array())
    {
        foreach ($data as $name => $value) {
            $message = str_replace('{'.$name.'}', $value, $message);
        }

        return $message;
    }
}
