<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\CoreHome\Types;

use Piwik\Type\Type;
use Piwik\Type\TypeSetting;
use Piwik\Type\TypeSettings;

class Website extends Type
{
    const ID = 'website';
    protected $name = 'General_Website';
    protected $description = 'General_WebsiteDescription';

    public function configureSettings(TypeSettings $settings)
    {
        $settings->addSetting(new TypeSetting('URLs', 'URLs'));
    }
}

