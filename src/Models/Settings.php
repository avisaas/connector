<?php

namespace AviSaaS\Connector\Models;

use Igniter\Flame\Database\Model;
use Igniter\System\Actions\SettingsModel;

class Settings extends Model
{
    public array $implement = [SettingsModel::class];

    public string $settingsCode = 'avisaas_connector_settings';

    public $settingsFieldsConfig = 'settings';
}
