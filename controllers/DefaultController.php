<?php

namespace shopium\mod\csv\controllers;


use Yii;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use panix\engine\Html;
use panix\engine\CMS;
use core\modules\shop\models\Product;
use core\components\controllers\AdminController;

use shopium\mod\csv\components\CsvExporter;
use shopium\mod\csv\components\CsvImporter;
use shopium\mod\csv\models\UploadForm;
use shopium\mod\csv\models\FilterForm;
use shopium\mod\csv\models\ImportForm;

ignore_user_abort(1);
set_time_limit(0);

class DefaultController extends AdminController
{

    public function actions()
    {
        return [
            'delete-file' => [
                'class' => 'panix\engine\actions\RemoveFileAction',
                'path' => Yii::$app->getModule('csv')->uploadPath,
                'redirect' => ['/csv/default/import']
            ],
        ];
    }

    public function beforeAction($action)
    {
        $path = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath);
        if (!file_exists($path)) {
            FileHelper::createDirectory($path);
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $this->pageName = Yii::t('csv/default', 'IMPORT_PRODUCTS');


        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;

        return $this->render('index');
    }

    /**
     * Import products
     */
    public function actionImport()
    {

        $this->pageName = Yii::t('csv/default', 'IMPORT_PRODUCTS');
        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'EXPORT'),
            'url' => ['/admin/csv/default/export'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;


        $files = \yii\helpers\FileHelper::findFiles(Yii::getAlias(Yii::$app->getModule('csv')->uploadPath));

        $data = [];
        foreach ($files as $f) {
            $name = basename($f);
            $data[] = [
                'file' => $f,
                'name' => $name,
                'img' => Html::img('/uploads/csv_import_image/'.Yii::$app->user->id.'/' . $name, ['width' => 100])
            ];
        }


        $provider = new ArrayDataProvider([
            'allModels' => $data,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => ['id', 'name'],
            ],
        ]);

        $importer = new CsvImporter;


        $model = new ImportForm();
        $uploadModel = new UploadForm();
        if ($uploadModel->load(Yii::$app->request->post()) && $uploadModel->validate()) {
            $uploadModel->files = UploadedFile::getInstance($uploadModel, 'files');
            if ($uploadModel->files) {
                $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $uploadModel->files->name;
                if ($uploadModel->files->extension == 'zip') {
                    $uploadFiles = $uploadModel->files->saveAs($filePath);
                    if ($uploadFiles) {
                        if (file_exists($filePath)) {
                            $zipFile = new \PhpZip\ZipFile();
                            $zipFile->openFile($filePath);
                            $extract = $zipFile->extractTo(Yii::getAlias(Yii::$app->getModule('csv')->uploadPath));
                            if ($extract)
                                unlink($filePath);

                            Yii::$app->session->addFlash('success', Yii::t('csv/default', 'SUCCESS_UPLOAD_IMAGES'));

                        } else {
                            die('error 01');
                        }
                    }
                } elseif (in_array($uploadModel->files->extension, $importer::$extension)) {
                    //$filePath = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath) . DIRECTORY_SEPARATOR . CMS::gen(10) . '.' . $uploadModel->files->extension;
                    $filePath = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath) . DIRECTORY_SEPARATOR . $uploadModel->files->name;

                    $uploadModel->files->saveAs($filePath);
                    Yii::$app->session->addFlash('success', Yii::t('csv/default', 'SUCCESS_UPLOAD_IMAGES'));
                }
                return $this->redirect(['import']);
            }
        }
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $importer->deleteDownloadedImages = $model->remove_images;


            $model->file_csv = UploadedFile::getInstance($model, 'file_csv');
            if ($model->file_csv) {

                $importer->file = $model->file_csv->tempName;


                if ($importer->validate() && !$importer->hasErrors()) {
                    Yii::$app->session->addFlash('success', Yii::t('csv/default', 'SUCCESS_IMPORT'));
                    $importer->import();
                }
            }

        }

        return $this->render('import', [
            'importer' => $importer,
            'model' => $model,
            'uploadModel' => $uploadModel,
            'filesData' => $provider
        ]);
    }

    /**
     * Export products
     */
    public function actionExport()
    {
        $this->pageName = Yii::t('csv/default', 'EXPORT_PRODUCTS');
        $exporter = new CsvExporter;

        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'IMPORT'),
            'url' => ['/admin/csv/default/import'],
            'options' => ['class' => 'btn btn-success']
        ];

        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;


        $get = Yii::$app->request->get();
        $model = new FilterForm();
        $query = Product::find();
        $count = 0;
        $pages = false;
        if ($model->load(Yii::$app->request->get()) && $model->validate()) {

            //if (Yii::$app->request->get('manufacturer_id')) {

            if ($get['FilterForm']['manufacturer_id'] !== 'all') {

                $manufacturers = explode(',', $model->manufacturer_id);
                $query->applyManufacturers($manufacturers);
            }

            $query->where(['type_id' => $model->type_id]);
            $count = $query->count();
            $pages = new Pagination([
                'totalCount' => $count,
                'pageSize' => Yii::$app->settings->get('csv', 'pagenum')
            ]);
            $query->offset($pages->offset);
            $query->limit($pages->limit);
        }


        if (Yii::$app->request->get('attributes')) {
            $exporter->export(
                Yii::$app->request->get('attributes'), $query
            );
        }

        return $this->render('export', [
            'exporter' => $exporter,
            'pages' => $pages,
            'query' => $query,
            'count' => $count,
            'model' => $model,
            'importer' => new CsvImporter,
        ]);
    }


    /**
     * Sample csv file
     */
    public function actionSample()
    {
        // $response = Yii::$app->response;
        //$response->format = Response::FORMAT_RAW;
        //$response->getHeaders()->add('Content-type', 'application/octet-stream');
        // $response->getHeaders()->add('Content-Disposition', 'attachment; filename=sample.csv');

        $content = '"name";"category";"price";"type"' . PHP_EOL;
        $content .= '"Product Name";"Category/Subcategory";"10.99";"Product name"' . PHP_EOL;


        return \Yii::$app->response->sendContentAsFile($content, 'sample.csv', [
            'mimeType' => 'application/octet-stream',
            //  'inline'   => false
        ]);

        //  return $content;
    }

    public function getAddonsMenu()
    {
        return [
            [
                'label' => Yii::t('app/default', 'SETTINGS'),
                'url' => ['/admin/csv/settings/index'],
                'icon' => Html::icon('settings'),
            ],
        ];
    }

}
