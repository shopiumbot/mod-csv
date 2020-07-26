<?php

namespace shopium\mod\csv\models;

use shopium\mod\csv\components\CsvImporter;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Class ImportForm
 * @property string $filename
 * @property string $files
 * @package shopium\mod\csv\models
 */
class ImportForm extends Model
{

    const files_max_size = 1024 * 1024 * 50;
    const file_csv_max_size = 1024 * 1024 * 5;
    /**
     * @var array
     */
    public static $extension = ['csv', 'xlsx', 'xls'];
    public $filename;
    public $remove_images = true;
    public $db_backup;

    public function rules()
    {
        return [
            [['filename'], 'file', 'extensions' => self::$extension, 'maxSize' => self::file_csv_max_size],
            [['remove_images', 'db_backup'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'filename' => Yii::t('csv/default', 'FILENAME'),
            'remove_images' => Yii::t('csv/default', 'REMOVE_IMAGES'),
            'db_backup' => Yii::t('csv/default', 'DB_BACKUP'),
        ];
    }
}