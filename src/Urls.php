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
    const REMOTE_FILE = 'https://github.com/bolt/bolt/releases/download/v%s/bolt-%s';
    const REMOTE_DEMO_FILE = 'https://bolt.cm/distribution/demo?v=';

    const GIT_IGNORE = 'https://raw.githubusercontent.com/bolt/composer-install/v%s/.gitignore';

    const INSTALLER_LATEST_VER = 'https://get.bolt.cm/installer.version';
    const INSTALLER_FILE = 'http://bolt.cm/installer';

    /**
     * Constructor.
     */
    private function __construct()
    {
    }
}
