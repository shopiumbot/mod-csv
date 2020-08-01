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
    public $indent_row;
    public $indent_column;
    public $ignore_columns;
    public $google_token;
    public $google_sheet_id;

    public function rules()
    {
        return [
            [['pagenum', 'indent_row', 'indent_column'], 'required'],
            [['indent_column', 'indent_row'], 'integer', 'min' => 1],
            [['ignore_columns','google_sheet_id'], 'string'],
            [['google_token'], 'file', 'skipOnEmpty' => true, 'extensions' => ['json']],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 300,
            'indent_row' => 1,
            'indent_column' => 1,
            'ignore_columns' => '',
            'google_token' => '',
            'google_sheet_id'=>''
        ];
    }
}
