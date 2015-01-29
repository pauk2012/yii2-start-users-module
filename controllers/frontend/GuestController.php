<?php

namespace vova07\users\controllers\frontend;

use vova07\fileapi\actions\UploadAction as FileAPIUpload;
use pauko\social\models\SocialProfile;
use vova07\users\models\frontend\ActivationForm;
use vova07\users\models\frontend\RecoveryConfirmationForm;
use vova07\users\models\frontend\RecoveryForm;
use vova07\users\models\frontend\ResendForm;
use vova07\users\models\frontend\User;
use vova07\users\models\LoginForm;
use vova07\users\models\Profile;
use vova07\users\Module;
use yii\filters\AccessControl;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;
use yii\widgets\ActiveForm;
use Yii;

/**
 * Frontend controller for guest users.
 */
class GuestController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['?']
                    ]
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'fileapi-upload' => [
                'class' => FileAPIUpload::className(),
                'path' => $this->module->avatarsTempPath
            ],
            'auth' => [
                'class' => \yii\authclient\AuthAction::className(),
                'successCallback' => [$this, 'successCallback'],
            ],
        ];
    }


    public function successCallback($client)
    {
        $attributes = $client->getUserAttributes();



        $socialProfile = SocialProfile::findOne(['service_id' => $client->getName(), 'social_id' => $attributes['id']]);
        if ($socialProfile){
            $user = $socialProfile->user;
            Yii::$app->user->login($user);
            Yii::$app->session->setFlash(
                'success',
                Module::t('users', 'FRONTEND_FLASH_SUCCESS_SOCIAL_LOGIN')
            );

        } else {
            $user = new User(['scenario' => 'signup_after_oauth']);
            $profile = new Profile();
            $socialProfile = new SocialProfile();

            switch($client->getName())
            {
                case'facebook':
                    $user->username =  $client->getName() . '_' . $attributes['id'];
                    $user->email = $attributes['email'];
                    $profile->name = $attributes['first_name'];
                    $profile->surname = $attributes['last_name'];
                    $user->status_id = \vova07\users\models\User::STATUS_ACTIVE;
                    $socialProfile->service_id = 'facebook';
                    $socialProfile->social_id = $attributes['id'];
                    break;
                case'vkontakte':
                    if (isset($client->accessToken->params['email'])){
                        $user->email = $client->accessToken->params['email'];
                    }

                    $user->username =  $client->getName() . '_' . $attributes['uid'];

                    $profile->name = $attributes['first_name'];
                    $profile->surname = $attributes['last_name'];
                    $user->status_id = \vova07\users\models\User::STATUS_ACTIVE;
                    $socialProfile->service_id = 'vkontakte';
                    $socialProfile->social_id = $attributes['uid'];
                    break;
                default:
                    throw new \Exception('не реализованно ещё');

            }

            $user->populateRelation('profile', $profile);
            $user->populateRelation('socialProfile', $socialProfile);
            if ($user->save(false)) {
                Yii::$app->user->login($user);
                Yii::$app->session->setFlash(
                    'success',
                    Module::t('users', 'FRONTEND_FLASH_SUCCESS_SIGNUP_WITH_LOGIN')
                );

                //return $this->goHome();
            } else {
                Yii::$app->session->setFlash('danger', Module::t('users', 'FRONTEND_FLASH_FAIL_SIGNUP'));
                //return $this->refresh();
            }
        }









        // user login or signup comes here
    }



    /**
     * Sign Up page.
     * If record will be successful created, user will be redirected to home page.
     */
    public function actionSignup()
    {
        $user = new User(['scenario' => 'signup']);
        $profile = new Profile();

        if ($user->load(Yii::$app->request->post()) && $profile->load(Yii::$app->request->post())) {
            if ($user->validate() && $profile->validate()) {
                $user->populateRelation('profile', $profile);
                if ($user->save(false)) {
                    if ($this->module->requireEmailConfirmation === true) {
                        Yii::$app->session->setFlash(
                            'success',
                            Module::t(
                                'users',
                                'FRONTEND_FLASH_SUCCESS_SIGNUP_WITHOUT_LOGIN',
                                [
                                    'url' => Url::toRoute('resend')
                                ]
                            )
                        );
                    } else {
                        Yii::$app->user->login($user);
                        Yii::$app->session->setFlash(
                            'success',
                            Module::t('users', 'FRONTEND_FLASH_SUCCESS_SIGNUP_WITH_LOGIN')
                        );
                    }
                    return $this->goHome();
                } else {
                    Yii::$app->session->setFlash('danger', Module::t('users', 'FRONTEND_FLASH_FAIL_SIGNUP'));
                    return $this->refresh();
                }
            } elseif (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($user);
            }
        }

        return $this->render(
            'signup',
            [
                'user' => $user,
                'profile' => $profile
            ]
        );
    }

    /**
     * Resend email confirmation token page.
     */
    public function actionResend()
    {
        $model = new ResendForm();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                if ($model->resend()) {
                    Yii::$app->session->setFlash('success', Module::t('users', 'FRONTEND_FLASH_SUCCESS_RESEND'));
                    return $this->goHome();
                } else {
                    Yii::$app->session->setFlash('danger', Module::t('users', 'FRONTEND_FLASH_FAIL_RESEND'));
                    return $this->refresh();
                }
            } elseif (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }

        return $this->render(
            'resend',
            [
                'model' => $model
            ]
        );
    }

    /**
     * Sign In page.
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                if ($model->login()) {
                    return $this->goHome();
                }
            } elseif (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }

        return $this->render(
            'login',
            [
                'model' => $model
            ]
        );
    }

    /**
     * Activate a new user page.
     *
     * @param string $token Activation token.
     *
     * @return mixed View
     */
    public function actionActivation($token)
    {
        $model = new ActivationForm(['token' => $token]);

        if ($model->validate() && $model->activation()) {
            Yii::$app->session->setFlash('success', Module::t('users', 'FRONTEND_FLASH_SUCCESS_ACTIVATION'));
        } else {
            Yii::$app->session->setFlash('danger', Module::t('users', 'FRONTEND_FLASH_FAIL_ACTIVATION'));
        }

        return $this->goHome();
    }

    /**
     * Request password recovery page.
     */
    public function actionRecovery()
    {
        $model = new RecoveryForm();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                if ($model->recovery()) {
                    Yii::$app->session->setFlash('success', Module::t('users', 'FRONTEND_FLASH_SUCCESS_RECOVERY'));
                    return $this->goHome();
                } else {
                    Yii::$app->session->setFlash('danger', Module::t('users', 'FRONTEND_FLASH_FAIL_RECOVERY'));
                    return $this->refresh();
                }
            } elseif (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }

        return $this->render(
            'recovery',
            [
                'model' => $model
            ]
        );
    }

    /**
     * Confirm password recovery request page.
     *
     * @param string $token Confirmation token
     *
     * @return mixed View
     */
    public function actionRecoveryConfirmation($token)
    {
        $model = new RecoveryConfirmationForm(['token' => $token]);

        if (!$model->isValidToken()) {
            Yii::$app->session->setFlash(
                'danger',
                Module::t('users', 'FRONTEND_FLASH_FAIL_RECOVERY_CONFIRMATION_WITH_INVALID_KEY')
            );
            return $this->goHome();
        }

        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                if ($model->recovery()) {
                    Yii::$app->session->setFlash(
                        'success',
                        Module::t('users', 'FRONTEND_FLASH_SUCCESS_RECOVERY_CONFIRMATION')
                    );
                    return $this->goHome();
                } else {
                    Yii::$app->session->setFlash(
                        'danger',
                        Module::t('users', 'FRONTEND_FLASH_FAIL_RECOVERY_CONFIRMATION')
                    );
                    return $this->refresh();
                }
            } elseif (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }

        return $this->render(
            'recovery-confirmation',
            [
                'model' => $model
            ]
        );

    }



}
