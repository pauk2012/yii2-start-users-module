<?php

/**
 * Signup page view.
 *
 * @var \yii\web\View $this View
 * @var \yii\widgets\ActiveForm $form Form
 * @var \vova07\users\models\frontend\User $user Model
 * @var \vova07\users\models\Profile $profile Profile
 */


use vova07\users\Module;

$this->title = Module::t('users', 'FRONTEND_SIGNUP_TITLE');
$this->params['breadcrumbs'] = [
    $this->title
]; ?>
<div>
    <p>Регистрация через:</p>
<?= yii\authclient\widgets\AuthChoice::widget([
    'baseAuthUrl' => ['guest/auth']
]) ?>
    <p>Или</p>
<?=vova07\users\widgets\SignUpForm::widget(['user' => $user, 'profile' => $profile])?>
</div>