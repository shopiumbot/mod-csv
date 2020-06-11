<?php

namespace shopium\mod\csv\migrations;

/**
 * Generation migrate by PIXELION CMS
 *
 * @author PIXELION CMS development team <dev@pixelion.com.ua>
 * @link http://pixelion.com.ua PIXELION CMS
 *
 * Class m170908_104527_csv
 */

use panix\engine\db\Migration;

class m170908_104527_csv extends Migration
{


    public function up()
    {

        $this->createTable('{{%csv}}', [
            'id' => $this->primaryKey()->unsigned(),
            'object_id' => $this->integer()->null(),
            'object_type' => $this->tinyInteger()->null(),
            'external_id' => $this->string(255)->null(),
        ], $this->tableOptions);

        $this->createIndex('object_id', '{{%csv}}', 'object_id');
        $this->createIndex('object_type', '{{%csv}}', 'object_type');
        $this->createIndex('external_id', '{{%csv}}', 'external_id');
    }

    public function down()
    {
        $this->dropTable('{{%csv}}');
    }

}
