<?php

namespace shopium\mod\csv\components;


use core\modules\shop\components\ExternalFinder;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use shopium\mod\csv\components\AttributesProcessor;
use shopium\mod\csv\components\Image;
use Yii;
use yii\base\Component;
use panix\engine\CMS;
use core\modules\shop\models\Manufacturer;
use core\modules\shop\models\ProductType;
use core\modules\shop\models\Attribute;
use core\modules\shop\models\Category;
use core\modules\shop\models\Product;
use core\modules\images\behaviors\ImageBehavior;
use core\modules\shop\models\Currency;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * Import products from csv format
 * Images must be located at ./uploads/importImages
 */
class Importer extends Component
{

    /**
     * @var string column delimiter
     */
    public $delimiter = ",";

    /**
     * @var string
     */
    public $enclosure = '"';

    /**
     * @var UploadedFile path to file
     */
    public $file;


    public $newfile;

    /**
     * @var string encoding.
     */
    public $encoding;

    /**
     * @var string
     */
    public $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';

    /**
     * @var bool
     */
    public $deleteDownloadedImages = false;

    /**
     * @var resource
     */
    protected $fileHandler;

    /**
     * Columns from first line. e.g array(category, price, name, etc...)
     * @var array
     */
    protected $columns = [];

    /**
     * @var null|Category
     */
    protected $rootCategory = null;

    /**
     * @var array
     */
    protected $categoriesPathCache = [];

    /**
     * @var array
     */
    protected $productTypeCache = [];

    /**
     * @var array
     */
    protected $manufacturerCache = [];

    /**
     * @var array
     */
    protected $currencyCache = [];

    /**
     * @var int
     */
    protected $line = 1;

    /**
     * @var array
     */
    protected $errors = [];
    protected $warnings = [];

    /**
     * @var array
     */
    public $stats = [
        'create' => 0,
        'update' => 0,
        'deleted' => 0
    ];
    public static $extension = ['jpg', 'jpeg'];
    public $required = ['Наименование', 'Категория', 'Цена', 'Тип'];

    public $totalProductCount = 0;

    /**
     * @var ExternalFinder
     */
    public $external;

    public function getFileHandler()
    {
        $config = Yii::$app->settings->get('csv');
        $indentRow = (isset($config->indent_row)) ? $config->indent_row : 1;
        $indentColumn = (isset($config->indent_column)) ? $config->indent_column : 1;
        $ignoreColumns = (isset($config->ignore_columns)) ? explode(',', $config->ignore_columns) : [];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->newfile);
        $worksheet = $spreadsheet->getActiveSheet();
        //$props = $spreadsheet->getProperties();
        $rows = [];
        $cellsHeaders = [];
        foreach ($worksheet->getRowIterator($indentRow, 1) as $k => $row) {
            $cellIterator2 = $row->getCellIterator(Helper::num2alpha($indentColumn));
            $cellIterator2->setIterateOnlyExistingCells(false); // This loops through all cells,
            foreach ($cellIterator2 as $column => $cell2) {
                $value = trim($cell2->getValue());
                if (!in_array(mb_strtolower($column), $ignoreColumns)) {
                    if (!empty($value)) {
                        $cellsHeaders[$column] = $value;
                    }
                }
            }

        }
        foreach ($worksheet->getRowIterator($indentRow + 1) as $k2 => $row) {

            $cellIterator = $row->getCellIterator(Helper::num2alpha($indentColumn));
            $cellIterator->setIterateOnlyExistingCells(false); // This loops through all cells,
            $cells = [];
            foreach ($cellIterator as $column2 => $cell) {
                $value = trim($cell->getValue());
                if (isset($cellsHeaders[$column2])) {
                    if (!in_array(mb_strtolower($column2), $ignoreColumns)) {

                        if ($cell->getDataType() == 'f') {
                            //todo: need Re-pattern...
                            //string: =IMAGE("https://sun9-22.userapi.com/c855128/v855128088/114004/x7FdunGhaWc.jpg",2)
                            preg_match('/(IMAGE).*(https?:\/\/?[-\w]+\.[-\w\.]+\w(:\d+)?[-\w\/_\.]*(\?\S+)?)/iu', $cell->getValue(), $match);
                            if (isset($match[1]) && isset($match[2])) {
                                if (mb_strtolower($match[1]) == 'image') {

                                    $cells[$cellsHeaders[$column2]] = trim($match[2]);
                                }
                            }
                        } else {
                            $cells[$cellsHeaders[$column2]] = $value;
                        }
                    }
                }
            }

            $rows[$k2] = $cells;
        }
        return [$cellsHeaders, $rows];

    }

    /**
     * @return bool validate csv file
     */
    public function validate()
    {

        $this->totalProductCount = Product::find()->count();
        // Check file exists and readable
        if (is_uploaded_file($this->file->tempName)) {

            $newDir = Yii::getAlias('@runtime') . '/tmp.' . $this->file->extension;
            move_uploaded_file($this->file->tempName, $newDir);
            $this->newfile = $newDir;
        } elseif (file_exists($this->file->tempName)) {
            // ok. file exists.
        } else {
            $this->errors[] = ['line' => 0, 'error' => Yii::t('csv/default', 'ERROR_FILE')];
            return false;
        }

        $this->columns = $this->getFileHandler();

        // CMS::dump($this->columns[1]);
        //Проверка чтобы небыло атрибутов с таким же названием как и системные параметры
        $i = 1;

        foreach (AttributesProcessor::getImportExportData('eav_') as $key => $value) {
            if (mb_strpos($key, 'eav_') !== false) {
                $attributeName = str_replace('eav_', '', $key);
                if (in_array($attributeName, AttributesProcessor::skipNames)) {
                    $this->errors[] = [
                        'line' => 0,
                        'error' => Yii::t('csv/default', 'ERROR_COLUMN_ATTRIBUTE', [
                            'attribute' => $attributeName
                        ])
                    ];
                    return false;
                }
            }
            $i++;
        }
        //CMS::dump($this->columns[0]);
        //CMS::dump($this->columns[1]);
        //die;

        foreach ($this->required as $column) {
            if (!in_array($column, $this->columns[0]))
                $this->errors[] = [
                    'line' => 0,
                    'error' => Yii::t('csv/default', 'REQUIRE_COLUMN', ['column' => $column])
                ];
        }

        return !$this->hasErrors();
    }


    /**
     * Here we go
     */
    public function import()
    {
        // $file = $this->getFileHandler();
        //fgets($file); // Skip first
        // Process lines
        $this->line = 1;
        $this->external = new ExternalFinder('{{%csv}}');
        $counter = 0;
        foreach ($this->columns[1] as $columnIndex => $row) {

            // if ($counter >= 100) {
            if (isset($row['Наименование'], $row['Цена'], $row['Категория'], $row['Тип'])) {
                if (!empty($row['Наименование']) && !empty($row['Цена']) && !empty($row['Тип'])) {
                    $row = $this->prepareRow($row);
                    $this->line = $columnIndex;
                    $this->importRow($row);
                }
            }
            // }
            $counter++;
        }
    }

    /**
     * Create/update product from key=>value array
     * @param $data array of product attributes
     */
    protected function importRow($data)
    {

        if (!isset($data['Категория']) || empty($data['Категория']))
            $data['Категория'] = 'root';

        $newProduct = false;

        $category_id = $this->getCategoryByPath($data['Категория']);
        // $query = Product::find();

        // Search product by name, category
        // or create new one
        //if (isset($data['sku']) && !empty($data['sku']) && $data['sku'] != '') {
        //   $query->where([Product::tableName() . '.sku' => $data['sku']]);
        // } else {
        //$query->where(['name' => $data['Наименование']]);
        // }


        $model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $data['Наименование']);


        $limitFlag = true;
        // $model = $query->one();
        $hasDeleted = false;


        if (!$model) {

            $newProduct = true;
            $model = new Product;


            if ($this->totalProductCount >= Yii::$app->params['plan'][Yii::$app->user->planId]['product_limit']) {
                $this->warnings[] = [
                    'line' => $this->line,
                    'error' => Yii::t('shop/default', 'PRODUCT_LIMIT', Yii::$app->params['plan'][Yii::$app->user->planId]['product_limit'])
                ];
                $limitFlag = false;
            }

            $this->totalProductCount++;

            if ($this->totalProductCount <= Yii::$app->params['plan'][Yii::$app->user->planId]['product_limit']) {
                $this->stats['create']++;
            }
        } else {
            $this->stats['update']++;
            if (isset($data['deleted']) && $data['deleted']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $model->delete();
            }

        }

        if (!$hasDeleted && $limitFlag) {
            // Process product type
            $config = Yii::$app->settings->get('csv');

            $model->type_id = $this->getTypeIdByName($data['Тип']);

            $model->main_category_id = $category_id;

            if (isset($data['switch']) && !empty($data['switch'])) {
                $model->switch = $data['switch'];
            } else {
                $model->switch = 1;
            }

            $model->price = $data['Цена'];
            $model->name = $data['Наименование'];

            if (isset($data['unit']) && !empty($data['unit']) && array_search(trim($data['unit']), $model->getUnits())) {
                $model->unit = array_search(trim($data['unit']), $model->getUnits());
            } else {
                $model->unit = 1;
            }

            // $model->price = $pricesList[0];

            // Manufacturer
            if (isset($data['Бренд']) && !empty($data['Бренд']))
                $model->manufacturer_id = $this->getManufacturerIdByName($data['Бренд']);


            if (isset($data['Артикул']) && !empty($data['Артикул']))
                $model->sku = $data['Артикул'];

            if (isset($data['custom_id']) && !empty($data['custom_id']))
                $model->custom_id = $data['custom_id'];

            if (isset($data['Описание']) && !empty($data['Описание']))
                $model->description = $data['Описание'];

            // Currency
            if (isset($data['Валюта']) && !empty($data['Валюта']))
                $model->currency_id = $this->getCurrencyIdByName($data['Валюта']);


            // Update product variables and eav attributes.
            $attributes = new AttributesProcessor($model, $data);

            if ($model->validate()) {

                $categories = [$category_id];

                if (isset($data['Доп. Категории']) && !empty($data['Доп. Категории']))
                    $categories = array_merge($categories, $this->getAdditionalCategories($data['Доп. Категории']));

                //if (!$newProduct) {
                //foreach ($model->categorization as $c)
                //    $categories[] = $c->category;
                $categories = array_unique($categories);
                //}


                // Save product
                $model->save();
                // Create product external id
                if ($newProduct === true) {


                    $this->external->createExternalId(ExternalFinder::OBJECT_PRODUCT, $model->id, $data['Наименование']);
                }


                // Update EAV data
                $attributes->save();


                $category = Category::findOne($category_id);

                if ($category) {
                    $tes = $category->ancestors()->excludeRoot()->all();
                    foreach ($tes as $cat) {
                        $categories[] = $cat->id;
                    }

                }

                // Update categories
                $model->setCategories($categories, $category_id);

                if (isset($data['Фото']) && !empty($data['Фото'])) {
                    if ($this->validateImage($data['Фото'])) {
                        /** @var ImageBehavior $model */
                        $imagesArray = explode(';', $data['Фото']);
                        $limit = Yii::$app->params['plan'][Yii::$app->user->planId]['product_upload_files'];
                        if ((count($imagesArray) > $limit) || $model->imagesCount > $limit) {
                            $this->errors[] = [
                                'line' => $this->line,
                                'error' => Yii::t('shop/default', 'PRODUCT_LIMIT', count($imagesArray))
                            ];
                        } else {
                            foreach ($imagesArray as $n => $im) {
                                $imageName = $model->id . '_' . basename($im);
                                $externalFinderImage = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName);

                                if (!$externalFinderImage) {
                                    $images = $model->getImages();
                                    if ($images) {
                                        foreach ($images as $image) {
                                            //$mi = $model->removeImage($image);
                                            // if ($mi) {
                                            $externalFinderImage2 = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName, true, false, true);
                                            if ($externalFinderImage2) {
                                                $mi = $model->removeImage($image);
                                                $externalFinderImage2->delete();
                                                $this->external->removeByPk(ExternalFinder::OBJECT_IMAGE, $image->id);
                                            }
                                            // }
                                        }
                                    }

                                    $image = Image::create($im);
                                    if ($image) {
                                        $result = $model->attachImage($image);

                                        if ($this->deleteDownloadedImages) {
                                            $image->deleteTempFile();
                                        }
                                        if ($result) {
                                            /*$this->warnings[] = [
                                                'line' => $this->line,
                                                'error' => $imageName . ' ' . $result->id
                                            ];*/
                                            $this->external->createExternalId(ExternalFinder::OBJECT_IMAGE, $result->id, $imageName);
                                        } else {
                                            $this->errors[] = [
                                                'line' => $this->line,
                                                'error' => 'Ошибка изображения #0001'
                                            ];
                                        }
                                    } else {
                                        $this->warnings[] = [
                                            'line' => $this->line,
                                            'error' => 'Ошибка изображения'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }

            } else {
                $errors = $model->getErrors();

                $error = array_shift($errors);
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => $error[0]
                ];
            }
        }
    }

    /**
     * Get additional categories array from string separated by ";"
     * E.g. Video/cat1;Video/cat2
     * @param $str
     * @return array
     */
    public function getAdditionalCategories($str)
    {
        $result = [];
        $parts = explode(';', $str);
        foreach ($parts as $path) {
            $result[] = $this->getCategoryByPath(trim($path), true);
        }


        return $result;
    }

    private function validateImage($image)
    {
        $imagesList = explode(';', $image);
        foreach ($imagesList as $i => $im) {

            $checkFile = mb_strtolower(pathinfo($im, PATHINFO_EXTENSION));

            if (!in_array($checkFile, self::$extension)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => Yii::t('csv/default', 'ERROR_IMAGE_EXTENSION', implode(', ', self::$extension))
                ];
                return false;
            }

            if (empty($im)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => Yii::t('csv/default', 'ERROR_IMAGE')
                ];
                return false;
            }
        }
        return true;
    }

    /**
     * Find or create manufacturer
     * @param $name
     * @return integer
     */
    public function getManufacturerIdByName($name)
    {
        if (isset($this->manufacturerCache[$name]))
            return $this->manufacturerCache[$name];


        $model = $this->external->getObject(ExternalFinder::OBJECT_MANUFACTURER, trim($name), true);

        // $query = Manufacturer::find()
        //    ->where(['name' => trim($name)]);

        // ->where(['name' => $name]);

        // $model = $query->one();
        if (!$model) {
            $model = new Manufacturer();
            $model->name = trim($name);
            if ($model->save()) {
                $this->external->createExternalId(ExternalFinder::OBJECT_MANUFACTURER, $model->id, $model->name);
            }
        }

        $this->manufacturerCache[$name] = $model->id;
        return $model->id;
    }

    /**
     * Find Currency
     * @param string $name
     * @return integer
     * @throws Exception
     */
    public function getCurrencyIdByName($name)
    {
        if (isset($this->currencyCache[$name]))
            return $this->currencyCache[$name];

        $query = Currency::find()->where(['iso' => trim($name)]);
        /** @var Currency $model */
        $model = $query->one();

        if (!$model)
            throw new Exception(Yii::t('csv/default', 'NO_FIND_CURRENCY', $name));

        $this->currencyCache[$name] = $model->id;
        return $model->id;
    }


    /**
     * Get product type by name. If type not exists - create new one.
     * @param $name
     * @return int
     */
    public function getTypeIdByName($name)
    {
        if (isset($this->productTypeCache[$name]))
            return $this->productTypeCache[$name];

        $model = ProductType::find()->where(['name' => $name])->one();

        if (!$model) {
            $model = new ProductType;
            $model->name = $name;
            $model->save();
        }

        $this->productTypeCache[$name] = $model->id;

        return $model->id;
    }

    /**
     * Get category id by path. If category not exits it will new one.
     * @param $path string Catalog/Shoes/Nike
     * @return integer category id
     */
    protected function getCategoryByPath($path)
    {
        if (isset($this->categoriesPathCache[$path]))
            return $this->categoriesPathCache[$path];

        if ($this->rootCategory === null)
            $this->rootCategory = Category::findOne(1);

        $result = preg_split($this->subCategoryPattern, $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = array_map('stripcslashes', $result);


        // $test = $result;
        // krsort($test);

        $parent = $this->rootCategory;
        $level = 2; // Level 1 is only root
        /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $model */

        /*$leaf = array_pop($result);
        $tree = [];
        $branch = &$tree;
        foreach ($result as $name) {
            $branch[$name] = [];
            $branch = &$branch[$name];
        }
        $branch = $leaf;*/


        $pathName = '';
        $tree = [];
        foreach ($result as $key => $name) {
            $pathName .= '/' . $name;
            $tree[] = substr($pathName, 1);
        }


        foreach ($tree as $key => $name) {
            $object = explode('/', $name);
            $model = Category::find()->where(['path_hash' => md5($name)])->one();

            if (!$model) {
                $model = new Category;
                $model->name = end($object);
                $model->appendTo($parent);
            }

            $parent = $model;
            $level++;

        }
        // Cache category id
        $this->categoriesPathCache[$path] = $model->id;

        if (isset($model)) {
            return $model->id;
        }

        return 1; // root category
    }

    private function test2($tree)
    {
        $data = [];
        $test = '';
        if (is_array($tree)) {
            foreach ($tree as $key => $name) {
                $data[$key] = $this->test2($name);
            }
        } else {
            $data[] = $tree;
        }

        return $data;
    }


    /**
     * Apply column key to csv row.
     * @param $row array
     * @return array e.g array(key=>value)
     */
    protected function prepareRow($row)
    {
        $row = array_map('trim', $row);
        // $row = array_combine($this->csv_columns[1], $row);

        $row['created_at'] = time();
        $row['updated_at'] = time();//date('Y-m-d H:i:s');

        return array_filter($row); // Remove empty keys and return result
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }


    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }


    /**
     * @return bool
     */
    public function hasWarnings()
    {
        return !empty($this->warnings);
    }


    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Close file handler
     */
    public function __destruct()
    {
        if ($this->fileHandler !== null)
            fclose($this->fileHandler);
    }

}
