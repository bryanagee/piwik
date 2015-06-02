<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Type;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\Manager as PluginManager;

class Type
{
    const ID = '';
    protected $name = '';

    public function getId()
    {
        $id = static::ID;

        if (empty($id)) {
            $message = 'Type %s does not define an ID. Set the ID constant to fix this issue';;
            throw new \Exception(sprintf($message, get_called_class()));
        }

        return $id;
    }

    public function configureSettings(TypeSettings $settings)
    {
    }

    /**
     * @return Type[]
     */
    public static function getAllTypes()
    {
        $types = array();

        $classes = PluginManager::getInstance()->findMultipleComponents('Types', '\\Piwik\\Type\\Type');
        foreach ($classes as $classname) {
            $types[] = StaticContainer::get($classname);
        }

        return $types;
    }

    /**
     * @param string $typeId
     * @return Type|null
     */
    public static function getType($typeId)
    {
        foreach (self::getAllTypes() as $type) {
            if ($type->getId() === $typeId) {
                return $type;
            }
        }
    }
}

