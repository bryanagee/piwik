<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Type;

use Piwik\Piwik;
use Piwik\Site;
use Piwik\Type\TypeSettings\Storage;

class TypeSettings
{
    /**
     * @var TypeSetting[]
     */
    private $settings = array();

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var int
     */
    private $idSite = null;

    public function __construct($idSite)
    {
        $this->idSite  = $idSite;
        $this->storage = new Storage($idSite);
    }

    public function addSetting(TypeSetting $setting)
    {
        $setting->setStorage($this->storage);
        $this->settings[] = $setting;
    }

    public function getSettings()
    {
        $typeId = Site::getTypeFor($this->idSite);
        $type   = Type::getType($typeId);

        if (empty($type)) {
            throw new \Exception(sprintf('The type %s does not exist', $typeId)); // TODO plugin was most likely uninstalled, we need to define how to handle such cases
        }

        $type->configureSettings($this);

        Piwik::postEvent('Type.getSettings', array($this, $typeId, $this->idSite));

        return $this->settings;
    }

    public function save()
    {
        Piwik::checkUserHasAdminAccess($this->idSite);

        $this->storage->save();
    }

}

