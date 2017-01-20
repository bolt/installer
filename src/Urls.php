<?php

namespace Bolt\Installer;

/**
 * URLs in use.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Urls
{
    const REMOTE_VERSIONS = 'https://get.bolt.cm/versions.json';
    const REMOTE_LATEST = 'https://get.bolt.cm/latest';
    const REMOTE_FILE = 'https://get.bolt.cm/download/%s/%s?php=%s';
    const REMOTE_DEMO_FILE = 'https://get.bolt.cm/demo?v=';

    const GIT_IGNORE = 'https://raw.githubusercontent.com/bolt/composer-install/v%s/.gitignore';

    const INSTALLER_LATEST_VER = 'https://get.bolt.cm/installer.version';
    const INSTALLER_FILE = 'https://get.bolt.cm/installer';

    /**
     * Constructor.
     */
    private function __construct()
    {
    }
}
