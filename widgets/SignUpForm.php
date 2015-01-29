<?php
/**
 * Created by JetBrains PhpStorm.
 * User: pauk
 * Date: 13.01.15
 * Time: 19:41
 * To change this template use File | Settings | File Templates.
 */

namespace vova07\users\widgets;


use yii\base\Widget;

class SignUpForm extends Widget
{
    const TYPE_SIGNUP = 'signup';
    const TYPE_SIGNUP_AFTER_OAUTH = 'signup_after_oauth';
    public $type = self::TYPE_SIGNUP;
    public $user;
    public $profile;
        public function run()
        {
            echo $this->render($this->type,['profile' => $this->profile, 'user' => $this->user]);
        }
}