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

    /**
     * @var string
     */
    private $idType = null;

    /**
     * @param int $idSite The id of a site. If you want to get settings for a not yet created site just pass an empty value ("0")
     * @param null|string $idType If no typeId is given, the type of the site will be used.
     *
     * @throws \Exception
     */
    public function __construct($idSite = 0, $idType = null)
    {
        if (empty($idSite) && empty($idType)) {
            throw new \Exception('Either a typeId or an idSite has to be given in order to create type settings');
        }

        $this->idSite  = $idSite;
        $this->idType  = $idType;
        $this->storage = new Storage($idSite);
    }

    public function addSetting(TypeSetting $setting)
    {
        $setting->setStorage($this->storage);
        $this->settings[] = $setting;
    }

    public function getSetting($name)
    {
        foreach ($this->getSettings() as $setting) {
            if ($setting->getName() === $name) {
                return $setting;
            }
        }
    }

    public function getSettings()
    {
        if (!$this->idType) {
            $this->idType = Site::getTypeFor($this->idSite);
        }

        $type = Type::getType($this->idType);

        if (empty($type)) {
            throw new \Exception(sprintf('The type %s does not exist', $this->idType)); // TODO plugin was most likely uninstalled, we need to define how to handle such cases
        }

        $type->configureSettings($this);

        Piwik::postEvent('Type.getSettings', array($this, $this->idType, $this->idSite));

        return $this->settings;
    }

    public function save()
    {
        Piwik::checkUserHasAdminAccess($this->idSite);

        $this->storage->save();
    }

}

