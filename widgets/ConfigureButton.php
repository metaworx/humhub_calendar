<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2019 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\calendar\widgets;


use humhub\components\Widget;
use humhub\modules\calendar\helpers\Url;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\helpers\ContentContainerHelper;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\widgets\Button;
use humhub\widgets\ModalButton;
use Yii;

class ConfigureButton extends Widget
{
    /**
     * @var ContentContainerActiveRecord
     */
    public $container;

    public function init()
    {
        $this->container = ContentContainerHelper::getCurrent();

        if(!$this->container) {
            $this->container = Yii::$app->user->getIdentity();
        }

        parent::init(); // TODO: Change the autogenerated stub
    }

    public function run()
    {
        if(Yii::$app->user->isGuest) {
            return '';
        }

        if($this->container instanceof User && !Yii::$app->user->getIdentity()->moduleManager->isEnabled('calendar')) {
            return ModalButton::defaultType()->load(Url::toEnableModuleOnProfileConfig())->icon('fa-cog')->visible($this->canConfigure());
        }

        return Button::defaultType()->link($this->getConfigUrl())->icon('fa-cog')->visible($this->canConfigure());
    }

    private function getConfigUrl()
    {
        $menu = new ContainerConfigMenu();
        $first = $menu->getFirstVisibleItem();
        if(!$first) {
            return '';
        }

        return  $first['url'];
    }

    public function canConfigure()
    {
        if($this->container instanceof Space) {
            $menu = new ContainerConfigMenu();
            return !empty($menu->getFirstVisibleItem());
        } else {
            return $this->container->isCurrentUser();
        }
    }


}
