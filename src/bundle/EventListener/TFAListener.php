<?php

namespace Edgar\EzTFABundle\EventListener;

use Edgar\EzTFA\Handler\AuthHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use eZ\Publish\Core\MVC\Symfony\Security\User;
use Edgar\EzTFABundle\Entity\EdgarEzTFA;
use Edgar\EzTFA\Repository\EdgarEzTFARepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Edgar\EzTFA\Provider\ProviderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\Translator;
use EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface;

class TFAListener
{
    /** @var TokenStorage $tokenStorage */
    protected $tokenStorage;

    /** @var AccessDecisionManagerInterface $accessDecisionManager */
    protected $accessDecisionManager;

    /** @var AuthHandler $authHandler */
    protected $authHandler;

    /** @var EdgarEzTFARepository $tfaRepository */
    protected $tfaRepository;

    /** @var Translator */
    protected $translator;

    /** @var RouterInterface */
    protected $router;

    /** @var NotificationHandlerInterface */
    protected $notificationHandler;

    /**
     * TFAListener constructor.
     *
     * @param TokenStorage                   $tokenStorage
     * @param AccessDecisionManagerInterface $accessDecisionManager
     * @param AuthHandler                    $authHandler
     * @param Registry                       $doctrineRegistry
     * @param Translator                     $translator
     * @param RouterInterface                $router
     * @param NotificationHandlerInterface   $notificationHandler
     */
    public function __construct(
        TokenStorage $tokenStorage,
        AccessDecisionManagerInterface $accessDecisionManager,
        AuthHandler $authHandler,
        Registry $doctrineRegistry,
        Translator $translator,
        RouterInterface $router,
        NotificationHandlerInterface $notificationHandler
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->authHandler = $authHandler;
        $entityManager = $doctrineRegistry->getManager();
        $this->tfaRepository = $entityManager->getRepository(EdgarEzTFA::class);
        $this->translator = $translator;
        $this->router = $router;
        $this->notificationHandler = $notificationHandler;
    }

    /**
     * Handle event
     *
     * @param FilterControllerEvent $event
     */
    public function onRequest(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if ($event->getRequestType() === HttpKernelInterface::SUB_REQUEST || $request->isXmlHttpRequest()) {
            return;
        }

        if ($request->attributes->get('_route') === 'fos_js_routing_js') {
            return;
        }

        if (strpos($request->getUri(), '/_tfa/') !== false) {
            return;
        }

        /** @var ProviderInterface[] $tfaProviders */
        $providers = $this->authHandler->getProviders();
        foreach ($providers as $key => $provider) {
            if (strpos($request->getUri(), '/_tfa/'.$key.'/auth') !== false) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $providersConfig = $this->authHandler->getProvidersConfig();
        if (null === $providersConfig) {
            return;
        }

        foreach ($providersConfig as $providerKey => $configData) {
            $activeProvider = $providerKey;
            $disabled = (isset($configData['disabled']) && $configData['disabled']);
            $autoSetup = (isset($configData['auto_setup']) && $configData['auto_setup']);
            if (!$disabled && $autoSetup) {
                break;
            }
            $activeProvider = null;
        }

        if ($this->authHandler->isAuthenticated()) {
            $user = $token->getUser();
            if ($user instanceof User) {
                $apiUser = $user->getAPIUser();
                $userProvider = $this->tfaRepository->findOneByUserId($apiUser->id);
                if (!$userProvider instanceof EdgarEzTFA && null !== $activeProvider) {
                    $tfaProvider = $providers[$activeProvider];
                    $tfaProvider->register($this->tfaRepository, $apiUser->id,$activeProvider);

                    $this->notificationHandler->success(
                        $this->translator->trans(
                            'edgar.eztfa.provider.selected',
                            [],
                            'edgareztfa'
                        )
                    );

                    $menuUrl = $this->router->generate('edgar.eztfa.menu');
                    $event->setController(
                        function () use ($menuUrl) {
                            return new RedirectResponse(
                                $menuUrl,
                                302,
                                ['Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0']
                            );
                        }
                    );
                }
            }
        } else {
            $redirectUrl = $this->authHandler->requestAuthCode($request);

            if ($redirectUrl) {
                $event->setController(
                    function () use ($redirectUrl) {
                        return new RedirectResponse(
                            $redirectUrl,
                            302,
                            ['Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0']
                        );
                    }
                );
            }
        }
    }
}
