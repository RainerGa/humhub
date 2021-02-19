<?php

use humhub\modules\content\models\ContentContainerTag;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use yii\db\Expression;
use yii\db\Migration;
use humhub\modules\content\models\ContentContainerTagRelation;

/**
 * Class m210211_051243_container_tags
 */
class m210211_051243_container_tag extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('contentcontainer_tag', [
            'id' => 'pk',
            'name' => $this->string(100)->notNull(),
            'contentcontainer_class' => $this->char(60)->notNull(),
        ]);
        $this->createIndex('unique-contentcontainer-tag', 'contentcontainer_tag', ['contentcontainer_class', 'name'], true);

        $this->createTable('contentcontainer_tag_relation', [
            'contentcontainer_id' => $this->integer()->notNull(),
            'tag_id' => $this->integer()->notNull(),
        ]);
        $this->addPrimaryKey('pk-contentcontainer-tag-rel', 'contentcontainer_tag_relation', ['contentcontainer_id', 'tag_id']);
        $this->addForeignKey('fk-contentcontainer-tag-rel-contentcontainer-id', 'contentcontainer_tag_relation', 'contentcontainer_id', 'contentcontainer', 'id', 'CASCADE');
        $this->addForeignKey('fk-contentcontainer-tag-rel-tag-id', 'contentcontainer_tag_relation', 'tag_id', 'contentcontainer_tag', 'id', 'CASCADE');

        $this->moveContainerTags(Space::class);
        $this->dropColumn('space', 'tags');

        $this->moveContainerTags(User::class);
        $this->dropColumn('user', 'tags');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210211_051243_container_tag.\n";

        return false;
    }

    /**
     * Move container tags from old column `tags` to new table `contentcontainer_tag`
     *
     * @param string $contentContainerClass
     */
    protected function moveContainerTags($contentContainerClass)
    {
        $tags = [];

        /* @var $contentContainerClass Space|User */
        $contentContainers = $contentContainerClass::find()
            ->where(['!=', 'tags', ''])
            ->andWhere(['IS NOT', 'tags', new Expression('NULL')])
            ->all();

        /* @var $contentContainer Space|User */
        foreach ($contentContainers as $contentContainer) {
            $containerTags = $contentContainerClass::find()
                ->select('tags')
                ->where(['id' => $contentContainer->id])
                ->scalar();
            $containerTags = preg_split('/[;,#]\s?+/', $containerTags);
            foreach ($containerTags as $tagName) {
                if (!isset($tags[$tagName])) {
                    $contentContainerTag = new ContentContainerTag();
                    $contentContainerTag->name = $tagName;
                    $contentContainerTag->contentcontainer_class = $contentContainerClass;
                    if ($contentContainerTag->save()) {
                        $tags[$tagName] = $contentContainerTag->id;
                    }
                }
                if (!empty($tags[$tagName])) {
                    $contentContainerTagRelation = new ContentContainerTagRelation();
                    $contentContainerTagRelation->contentcontainer_id = $contentContainer->contentcontainer_id;
                    $contentContainerTagRelation->tag_id = $tags[$tagName];
                    $contentContainerTagRelation->save();
                }
            }
        }
    }
}