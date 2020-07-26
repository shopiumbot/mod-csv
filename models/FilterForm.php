<?php

namespace shopium\mod\csv\models;

use Yii;
use yii\base\Model;

/**
 * Class FilterForm
 * @property integer $manufacturer_id
 * @package shopium\mod\csv\models
 */
class FilterForm extends Model
{

    protected $module = 'csv';
    public static $category = 'csv';

    public $type_id;
    public $manufacturer_id;
    public $format;

    public function rules()
    {
        return [
            [['type_id','format'], 'required'],
            [['manufacturer_id'], 'integer'],

        ];
    }

    public function attributeLabels()
    {
        return [
            'type_id' => Yii::t('shop/Product', 'TYPE_ID'),
            'manufacturer_id' => Yii::t('shop/Product', 'MANUFACTURER_ID'),
            'format' => Yii::t('csv/default', 'EXPORT_FORMAT'),
        ];
    }
}