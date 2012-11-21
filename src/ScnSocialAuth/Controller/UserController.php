<?php
namespace ScnSocialAuth\Controller;

use Hybrid_Auth;
use ScnSocialAuth\Mapper\UserProviderInterface;
use ScnSocialAuth\Options\ModuleOptions;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ModelInterface;
use Zend\View\Model\ViewModel;

class UserController extends AbstractActionController
{
    /**
     * @var UserProviderInterface
     */
    protected $mapper;

    /**
     * @var Hybrid_Auth
     */
    protected $hybridAuth;

    /**
     * @var ModuleOptions
     */
    protected $options;

    public function addProviderAction()
    {
        // Make sure the provider is enabled, else 404
        $provider = $this->params('provider');
        if (!in_array($provider, $this->getOptions()->getEnabledProviders())) {
            return $this->notFoundAction();
        }

        $authService = $this->zfcUserAuthentication()->getAuthService();

        // If user is not logged in, redirect to login page
        if (!$authService->hasIdentity()) {
            return $this->redirect()->toRoute('zfcuser/login');
        }

        $hybridAuth = $this->getHybridAuth();
        $adapter = $hybridAuth->authenticate($provider);

        $localUser = $authService->getIdentity();
        $userProfile = $adapter->getUserProfile();
        $accessToken = $adapter->getAccessToken();

        $this->getMapper()->linkUserToProvider($localUser, $userProfile, $provider, $accessToken);

        $redirect = $this->params()->fromQuery('redirect', false);

        if ($this->getServiceLocator()->get('zfcuser_module_options')->getUseRedirectParameterIfPresent() && $redirect) {
            return $this->redirect()->toUrl($redirect);
        }

        return $this->redirect()->toRoute(
            $this->getServiceLocator()->get('zfcuser_module_options')->getLoginRedirectRoute()
        );
    }

    public function providerLoginAction()
    {
        $provider = $this->getEvent()->getRouteMatch()->getParam('provider');
        if (!in_array($provider, $this->getOptions()->getEnabledProviders())) {
            return $this->notFoundAction();
        }
        $hybridAuth = $this->getHybridAuth();
        $redirectUrl = $this->url()->fromRoute('scn-social-auth-user/authenticate/query', array('provider' => $provider));
        $adapter = $hybridAuth->authenticate(
            $provider,
            array(
                'hauth_return_to' => $redirectUrl,
            )
        );

        return $this->redirect()->toUrl($redirectUrl);
    }

    public function loginAction()
    {
        $zfcUserLogin = $this->forward()->dispatch('zfcuser', array('action' => 'login'));
        if (!$zfcUserLogin instanceof ModelInterface) {
            return $zfcUserLogin;
        }
        $viewModel = new ViewModel();
        $viewModel->addChild($zfcUserLogin, 'zfcUserLogin');
        $viewModel->setVariable('options', $this->getOptions());

        return $viewModel;
    }

    public function logoutAction()
    {
        Hybrid_Auth::logoutAllProviders();

        return $this->forward()->dispatch('zfcuser', array('action' => 'logout'));
    }

    public function registerAction()
    {
        $zfcUserRegister = $this->forward()->dispatch('zfcuser', array('action' => 'register'));
        if (!$zfcUserRegister instanceof ModelInterface) {
            return $zfcUserRegister;
        }
        $viewModel = new ViewModel();
        $viewModel->addChild($zfcUserRegister, 'zfcUserLogin');
        $viewModel->setVariable('options', $this->getOptions());

        return $viewModel;
    }

    /**
     * set mapper
     *
     * @param  UserProviderInterface $mapper
     * @return HybridAuth
     */
    public function setMapper(UserProviderInterface $mapper)
    {
        $this->mapper = $mapper;

        return $this;
    }

    /**
     * get mapper
     *
     * @return UserProviderInterface
     */
    public function getMapper()
    {
        if (!$this->mapper instanceof UserProviderInterface) {
            $this->setMapper($this->getServiceLocator()->get('ScnSocialAuth-UserProviderMapper'));
        }

        return $this->mapper;
    }

    /**
     * Get the Hybrid_Auth object
     *
     * @return Hybrid_Auth
     */
    public function getHybridAuth()
    {
        if (!$this->hybridAuth) {
            $this->hybridAuth = $this->getServiceLocator()->get('HybridAuth');
        }

        return $this->hybridAuth;
    }

    /**
     * Set the Hybrid_Auth object
     *
     * @param  Hybrid_Auth    $hybridAuth
     * @return UserController
     */
    public function setHybridAuth(Hybrid_Auth $hybridAuth)
    {
        $this->hybridAuth = $hybridAuth;

        return $this;
    }

    /**
     * set options
     *
     * @param  ModuleOptions  $options
     * @return UserController
     */
    public function setOptions(ModuleOptions $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * get options
     *
     * @return ModuleOptions
     */
    public function getOptions()
    {
        if (!$this->options instanceof ModuleOptions) {
            $this->setOptions($this->getServiceLocator()->get('ScnSocialAuth-ModuleOptions'));
        }

        return $this->options;
    }
}
