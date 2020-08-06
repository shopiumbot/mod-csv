<?php

namespace shopium\mod\csv\models;

use Yii;
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
    //public $google_token;
    public $google_sheet_id;
    public $google_sheet_list;

    public function rules()
    {
        return [
            [['pagenum', 'indent_row', 'indent_column'], 'required'],
            [['indent_column', 'indent_row'], 'integer', 'min' => 1],
            [['ignore_columns', 'google_sheet_id', 'google_sheet_list'], 'string'],
            [['google_sheet_id', 'google_sheet_list'], 'trim'],

            [['google_sheet_id'], 'connectValidation'],
            //[['google_token'], 'file', 'skipOnEmpty' => true, 'extensions' => ['json']],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 300,
            'indent_row' => 1,
            'indent_column' => 1,
            'ignore_columns' => '',
            //'google_token' => '',
            'google_sheet_id' => '',
            'google_sheet_list' => ''
        ];
    }

    public function connectValidation($attr)
    {
        try {
            $service = new \Google_Service_Sheets($this->getGoogleClient());
            $get = $service->spreadsheets->get($this->google_sheet_id);
            return true;
        } catch (\Google_Service_Exception $e) {
            $error = json_decode($e->getMessage());
            if ($error) {
                $this->addError($this->$attr, $error->error->message);
            } else {
                $this->addError($this->$attr, 'unknown error');
            }

        }
    }

    /**
     * @return \Google_Client|mixed
     */
    public function getGoogleClient()
    {
        try {
            $client = new \Google_Client([
                'credentials' => Yii::getAlias('@core') . '/shopiumbot-1595272867229-5e5d50e9a483.json'
            ]);

            $client->useApplicationDefaultCredentials();
            $client->setApplicationName("Something to do with my representatives");
            $client->setScopes(['https://spreadsheets.google.com/feeds']); //'https://www.googleapis.com/auth/drive',
            if ($client->isAccessTokenExpired()) {
                $client->refreshTokenWithAssertion();
            }
            return $client;
        } catch (\Google_Service_Exception $e) {
            $error = json_decode($e->getMessage());
            // \panix\engine\CMS::dump($error->error->message);
            return $error;
        }
    }

    public function getSheetsDropDownList()
    {
        try {
            $sheets = $this->getSheets();
            if ($sheets) {
                $sheet = $sheets->getSheets();
                $sheetListDropDown = [];
                foreach ($sheet as $sh) {
                    $sheetListDropDown[$sh->getProperties()->getTitle()] = $sh->getProperties()->getTitle();
                }
                return $sheetListDropDown;
            } else {
                return [];
            }
        } catch (\Google_Service_Exception $e) {
            $error = json_decode($e->getMessage());
            // \panix\engine\CMS::dump($error->error->message);
            return [];
        }
    }

    public function getSheets()
    {
        if ($this->google_sheet_id) {
            $service = new \Google_Service_Sheets($this->getGoogleClient());
            return $service->spreadsheets->get($this->google_sheet_id);
        } else {
            return false;
        }
    }
}
