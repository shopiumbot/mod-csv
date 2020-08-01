<?php

namespace shopium\mod\csv\controllers;


use Yii;
use shopium\mod\csv\models\SettingsForm;
use core\components\controllers\AdminController;
use yii\web\UploadedFile;

class SettingsController extends AdminController
{

    public $icon = 'settings';

    public function actionIndex()
    {
        $this->pageName = Yii::t('app/default', 'SETTINGS');
        $this->breadcrumbs[] = [
            'label' => Yii::t('csv/default', 'MODULE_NAME'),
            'url' => ['/admin/csv']
        ];
        $this->breadcrumbs[] = $this->pageName;


        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'EXPORT'),
            'url' => ['/csv/default/export'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'IMPORT'),
            'url' => ['/csv/default/import'],
            'options' => ['class' => 'btn btn-success']
        ];
        $model = new SettingsForm;
        $oldGoogleTokenFile = $model->google_token;
        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {


                $attachment_wm_path = UploadedFile::getInstance($model, 'google_token');
                if ($attachment_wm_path) {
                    $attachment_wm_path->saveAs(Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'google_secret.' . $attachment_wm_path->extension);
                    $model->google_token = 'google_secret.' . $attachment_wm_path->extension;
                } else {
                    $model->google_token = $oldGoogleTokenFile;
                }

                $model->save();
                Yii::$app->session->setFlash("success", Yii::t('app/default', 'SUCCESS_UPDATE'));
            }
            return $this->refresh();
        }

        return $this->render('index', ['model' => $model]);
    }

}
