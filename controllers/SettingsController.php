<?php

namespace shopium\mod\csv\controllers;

use shopium\mod\user\controllers\ClientController;
use Yii;
use shopium\mod\csv\models\SettingsForm;
use panix\engine\controllers\AdminController;

class SettingsController extends ClientController
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
        $model = new SettingsForm;

        if ($model->load(Yii::$app->request->post())) {
            if($model->validate()){
                $model->save();
                Yii::$app->session->setFlash("success", Yii::t('app/default', 'SUCCESS_UPDATE'));
            }
            return $this->refresh();
        }

        return $this->render('index', ['model' => $model]);
    }

}
