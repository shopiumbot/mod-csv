<?php

namespace shopium\mod\csv\models;

use panix\engine\SettingsModel;

/**
 * Class SettingsForm
 * @package shopium\mod\csv\models
 */
class SettingsForm extends SettingsModel
{

    protected $module = 'csv';
    public static $category = 'csv';

    public $pagenum;

    public function rules()
    {
        return [
            ['pagenum', 'required'],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 300,
        ];
    }
}
