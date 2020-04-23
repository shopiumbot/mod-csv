<?php

namespace shopium\mod\csv\components;

use core\modules\shop\models\ProductType;
use Yii;
use core\modules\shop\models\Product;
use core\modules\shop\models\Manufacturer;
use panix\engine\CMS;

use yii\helpers\Url;
use yii\web\Response;

class CsvExporter
{

    /**
     * @var array
     */
    public $rows = [];

    /**
     * @var string
     */
    public $delimiter = ";";

    /**
     * @var string
     */
    public $enclosure = '"';

    /**
     * Cache category path
     * @var array
     */
    public $categoryCache = [];

    /**
     * @var array
     */
    public $manufacturerCache = [];

    /**
     * @var array
     */
    public $currencyCache = [];

    /**
     * @param array $attributes
     * @param $query \core\modules\shop\models\query\ProductQuery
     */
    public function export(array $attributes, $query)
    {
        $this->rows[0] = $attributes;

        /*foreach ($this->rows[0] as &$v) {
            if (substr($v, 0, 4) === 'eav_')
                $v = substr($v, 4);
        }*/


        /** @var Product $p */
        foreach ($query->all() as $p) {
            $row = [];

            foreach ($attributes as $attr) {
                if ($attr === 'Категория') {
                    $value = $this->getCategory($p);
                } elseif ($attr === 'Бренд') {
                    $value = $this->getManufacturer($p);

                } elseif ($attr === 'Фото') {
                    /** @var \panix\mod\images\behaviors\ImageBehavior $img */
                    $img = $p->getImage();
                    $value = ($img) ? $img->filePath : NULL;
                } elseif ($attr === 'additionalCategories') {
                    $value = $this->getAdditionalCategories($p);
                } elseif ($attr === 'Тип') {
                    $value = $p->type->name;
                } elseif ($attr === 'Наименование') {
                    $value = $p->name;
                } elseif ($attr === 'Цена') {
                    $value = $p->price;
                } elseif ($attr === 'Валюта') {
                    $value = $this->getCurrency($p);
                } elseif ($attr === 'Артикул') {
                    $value = $p->sku;
                } elseif ($attr === 'wholesale_prices') {
                    $price = [];
                    $result = NULL;
                    if (isset($p->prices)) {
                        foreach ($p->prices as $wp) {
                            $price[] = $wp->value . '=' . $wp->from;
                        }
                        $result = implode(';', $price);
                    }
                    $value = $result;

                } elseif ($attr === 'unit') {
                    if (isset($p->units)) {
                        $value = (isset($p->units[$p->$attr])) ? $p->units[$p->$attr] : NULL;
                    } else {
                        $value = NULL;
                    }
                } else {
                    $name = CMS::slug($attr);
                    $value = $p->{'eav_' . $name}['value'];

                }

                //  $row[$attr] = iconv('utf-8', 'cp1251', $value); //append iconv by panix
                $row[$attr] = $value; //append iconv by panix
            }

            array_push($this->rows, $row);
        }

        $this->proccessOutput();
    }

    /**
     * Get category path
     * @param Product $product
     * @return string
     */
    public function getCategory(Product $product)
    {

        $category = $product->mainCategory;
        if ($category) {
            if ($category && $category->id == 1)
                return '';

            if (isset($this->categoryCache[$category->id]))
                $this->categoryCache[$category->id];
            // foreach($category->excludeRoot()->ancestors()->findAll() as $test){
            //    VarDumper::dump($test->name);
            //}
            // die();
            $ancestors = $category->ancestors()->excludeRoot()->all();
            if (empty($ancestors))
                return $category->name;

            $result = array();
            foreach ($ancestors as $c)
                array_push($result, preg_replace('/\//', '\/', $c->name));
            array_push($result, preg_replace('/\//', '\/', $category->name));

            $this->categoryCache[$category->id] = implode('/', $result);

            return $this->categoryCache[$category->id];
        } else {
            return false;
        }
    }

    /**
     * @param Product $product
     * @return string
     */
    public function getAdditionalCategories(Product $product)
    {
        $mainCategory = $product->mainCategory;
        $categories = $product->categories;

        $result = [];
        foreach ($categories as $category) {
            if ($category->id !== $mainCategory->id) {
                $path = [];
                $ancestors = $category->ancestors()->excludeRoot()->all();
                foreach ($ancestors as $c)
                    $path[] = preg_replace('/\//', '\/', $c->name);
                $path[] = preg_replace('/\//', '\/', $category->name);
                $result[] = implode('/', $path);
            }
        }

        if (!empty($result))
            return implode(';', $result);
        return '';
    }

    /**
     * Get manufacturer
     *
     * @param Product $product
     * @return mixed|string
     */
    public function getManufacturer(Product $product)
    {
        if (isset($this->manufacturerCache[$product->manufacturer_id]))
            return $this->manufacturerCache[$product->manufacturer_id];

        $product->manufacturer ? $result = $product->manufacturer->name : $result = '';
        $this->manufacturerCache[$product->manufacturer_id] = $result;
        return $result;
    }

    /**
     * Get Currency
     *
     * @param Product $product
     * @return mixed|string
     */
    public function getCurrency(Product $product)
    {
        if (isset($this->currencyCache[$product->currency_id]))
            return $this->currencyCache[$product->currency_id];

        $product->currency ? $result = $product->currency->iso : $result = '';
        $this->currencyCache[$product->currency_id] = $result;
        return $result;
    }

    /**
     * Create CSV file
     */
    public function proccessOutput()
    {

        $get = Yii::$app->request->get('FilterForm');
        $filename = '';
        if (isset($get['manufacturer_id'])) {
            if ($get['manufacturer_id'] == 'all') {
                $filename .= 'all_';
            } else {
                $manufacturer = Manufacturer::findOne($get['manufacturer_id']);
                if ($manufacturer) {
                    $filename .= $manufacturer->name . '_';
                }

            }
        }


        if ($get['type_id']) {
            $type = ProductType::findOne($get['type_id']);
            if ($type) {
                $filename .= $type->name . '_';
            }

        }


        $filename .= '(' . CMS::date() . ')';


        if (Yii::$app->request->get('page')) {
            $filename .= '_page-' . Yii::$app->request->get('page');
        }
        // $response = Yii::$app->response;
        // $response->format = Response::FORMAT_RAW;
        // $response->charset = 'utf-8';
        // $response->headers->set('Content-Type', 'application/octet-stream; charset=utf-8');
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");


        // $headers = Yii::$app->response->headers;
        //  $headers->add('Pragma111', 'no-cache');


        $csvString = '';
        foreach ($this->rows as $row) {
            foreach ($row as $l) {
                // $csvString .= $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $l) . $this->enclosure . $this->delimiter;
                //$csvString .= $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, mb_convert_encoding($l, 'UTF-8', 'Windows-1251')) . $this->enclosure . $this->delimiter;
                echo $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $l) . $this->enclosure . $this->delimiter;
            }
            // $csvString .= PHP_EOL;
            echo PHP_EOL;
        }

        // echo $csvString;
        die;

        /*return $response->sendContentAsFile($csvString, $filename . '.csv', [
            'mimeType' => 'application/octet-stream',
             'inline'   => false
        ]);*/

    }

}
