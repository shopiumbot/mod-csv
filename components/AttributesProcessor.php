<?php

namespace shopium\mod\csv\components;

use core\modules\shop\models\ProductType;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use core\modules\shop\models\Attribute;
use core\modules\shop\models\AttributeOption;
use core\modules\shop\models\Product;
use core\modules\shop\models\TypeAttribute;
use panix\engine\CMS;

/**
 * Class AttributesProcessor handles Product class attributes and
 * EAV attributes.
 */
class AttributesProcessor extends Component
{

    /**
     * @var Product
     */
    public $model;

    /**
     * @var array csv row.
     */
    public $data;

    /**
     * @var array
     */
    const skipNames = [
        'Наименование',
        'Артикул',
        'Категория',
        'Тип',
        'Цена',
        'Цена закупки',
        'Бренд',
        'Валюта',
        'Фото',
        'Доп. Категории',
        'wholesale_prices',
        'Описание',
        'unit',
        'switch',
        'Количество',
        'currency',
        'custom_id',
        'Наличие'
    ];

    /**
     * @var array Attribute models.
     */
    protected $attributesCache = [];

    /**
     * @var array AttributeOption models.
     */
    protected $optionsCache = [];

    /**
     * @var array for eav attributes to be saved.
     */
    protected $eav;

    /**
     * @param Product $product
     * @param array $data
     */
    public function __construct(Product $product, array $data)
    {
        $this->model = $product;
        $this->data = $data;
        $this->process();
        parent::__construct([]);
    }

    /**
     * Process each data row. First, try to assign value to products model,
     * if attributes does not exists - handle like eav attribute.
     */
    public function process()
    {

        foreach ($this->data as $key => $val) {
            try {
                if (!in_array($key, self::skipNames) && !empty($val)) {
                    $this->model->$key = $val;

                }

            } catch (Exception $e) {
                // Process eav
                if (!in_array($key, self::skipNames) && !empty($val)) {

                    //if (substr($key, 0, 4) === 'eav_')
                    //    $key = substr($key, 4);

                    $name = $key;
                    $key = CMS::slug($key);


                    $this->eav[$key] = $this->processEavData($name,$key, $val);
                }
            }
        }

    }

    /**
     * @param $attribute_name
     * @param $attribute_value
     * @return array
     */
    public function processEavData($attribute_name, $attribute_key, $attribute_value)
    {
        $result = [];

        $attribute = $this->getAttributeByName($attribute_key,$attribute_name);

        $multipleTypes = [Attribute::TYPE_CHECKBOX_LIST, Attribute::TYPE_DROPDOWN, Attribute::TYPE_SELECT_MANY, Attribute::TYPE_COLOR];

        if (in_array($attribute->type, $multipleTypes)) {
            foreach (explode(',', $attribute_value) as $val) {
                $option = $this->getOption($attribute, $val);
                $result[] = $option->id;
            }
        } else {
            $option = $this->getOption($attribute, $attribute_value);
            $result[] = $option->value;
        }

        return $result;
    }

    /**
     * Find or create option by attribute and value.
     *
     * @param Attribute $attribute
     * @param $val
     * @return AttributeOption
     */
    public function getOption(Attribute $attribute, $val)
    {
        $val = trim($val);
        $cacheKey = sha1($attribute->id . $val);

        if (isset($this->optionsCache[$cacheKey]))
            return $this->optionsCache[$cacheKey];

        // Search for option
        $query = AttributeOption::find();


        $query->where(['attribute_id' => $attribute->id]);
        $query->andWhere(['value' => $val]);


        $option = $query->one();

        if (!$option) // Create new option
            $option = $this->addOptionToAttribute($attribute->id, $val);

        $this->optionsCache[$cacheKey] = $option;

        return $option;
    }

    /**
     * @param $attribute_id
     * @param $value
     * @return AttributeOption
     */
    public function addOptionToAttribute($attribute_id, $value)
    {
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        $option->value = $value;
        $option->save(false);

        return $option;
    }

    /**
     * @param $key
     * @param $name
     * @return Attribute
     */
    public function getAttributeByName($key,$name)
    {


        if (isset($this->attributesCache[$key]))
            return $this->attributesCache[$key];


        $attribute = Attribute::find()->where(['name' => $key])->one();

        if (!$attribute) {

            // Create new attribute
            $attribute = new Attribute;
            $attribute->title = $name;
            $attribute->name = $key;
            $attribute->type = Attribute::TYPE_DROPDOWN;
            $attribute->save(false);

            // Add to type
            $typeAttribute = new TypeAttribute;
            $typeAttribute->type_id = $this->model->type_id;
            $typeAttribute->attribute_id = $attribute->id;
            $typeAttribute->save(false);
        }

        $this->attributesCache[$key] = $attribute;

        return $attribute;
    }

    /**
     * Append and save product attributes.
     */
    public function save()
    {
        if (!empty($this->eav))
            $this->model->setEavAttributes($this->eav, true);
    }


    public static function getImportExportData($eav_prefix = '', $type_id=null)
    {
        $attributes = [];
        $units = '';
        foreach ((new Product)->getUnits() as $id => $unit) {
            $units .= '<code>' . $unit . '</code><br/>';
        }

        $shop_config = Yii::$app->settings->get('shop');

        $attributes['custom_id'] = 'Пользовательский идентификатор';
        $attributes['Наименование'] = Yii::t('shop/Product', 'NAME');
        $attributes['Тип'] = Yii::t('shop/Product', 'TYPE_ID');
        $attributes['Категория'] = Yii::t('csv/default', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['Доп. Категории'] = Yii::t('csv/default', 'Доп. категории разделяются точкой с запятой <code>;</code>. На пример <code>MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['Бренд'] = Yii::t('csv/default', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['Артикул'] = Yii::t('shop/Product', 'SKU');
        $attributes['Валюта'] = Yii::t('shop/Product', 'CURRENCY_ID');
        $attributes['Цена'] = Yii::t('shop/Product', 'PRICE');
        //$attributes['wholesale_prices'] = Yii::t('csv/default', 'WHOLESALE_PRICE');
        $attributes['unit'] = Yii::t('shop/Product', 'UNIT') . '<br/>' . $units;
        $attributes['switch'] = Yii::t('csv/default', 'Скрыть или показать. Принимает значение <code>1</code> &mdash; показать <code>0</code> - скрыть.');
        $attributes['Фото'] = Yii::t('csv/default', 'Изображение (можно указать несколько изображений). Пример: <code>pic1.jpg;pic2.jpg</code> разделяя название изображений символом "<code>;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="alert alert-danger"><i class="icon-warning"></i> Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['Описание'] = Yii::t('csv/default', 'Полное описание HTML');
        $attributes['Количество'] = Yii::t('csv/default', 'Количество на складе.<br/>По умолчанию <code>1</code>, от 0 до 99999');
        $attributes['Наличие'] = Yii::t('csv/default', 'Доступность.<br/><code>1</code> &mdash; есть в наличие <strong>(по умолчанию)</strong><br/><code>2</code> &mdash; под заказ<br/><code>3</code> &mdash; нет в наличие.');
        //$attributes['created_at'] = Yii::t('app/default', 'Дата создания');
        // $attributes['updated_at'] = Yii::t('app/default', 'Дата обновления');
        /*foreach (Attribute::find()->asArray()->all() as $attr) {
            $attributes[$eav_prefix . $attr['title']] = $attr['title'];
        }*/

        if ($type_id) {
            $type = ProductType::findOne($type_id);
            foreach ($type->shopAttributes as $attr) {
                $attributes[$attr->title] = $attr->title;
            }
        } else {
            foreach (Attribute::find()->asArray()->all() as $attr) {
                $attributes[$attr['title']] = $attr['title'];
            }
        }

        return $attributes;
    }

}

