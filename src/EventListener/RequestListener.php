<?php

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use EMS\CoreBundle\Service\Revision\LoggingContext;
use Exception;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

class RequestListener
{
    private ChannelRegistrar $channelRegistrar;
    private TwigEnvironment $twig;
    private Registry $doctrine;
    private Logger $logger;
    private RouterInterface $router;

    public function __construct(
        ChannelRegistrar $channelRegistrar,
        TwigEnvironment $twig,
        Registry $doctrine,
        Logger $logger,
        RouterInterface $router
    ) {
        $this->channelRegistrar = $channelRegistrar;
        $this->twig = $twig;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->router = $router;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMasterRequest()) {
            $this->channelRegistrar->register($event->getRequest());
        }

        // TODO: move the next block to the FOS controller:
//        if ($request->get('_route') === $this->userRegistrationRoute && !$this->allowUserRegistration) {
//            $response = new RedirectResponse($this->router->generate($this->userLoginRoute, [], UrlGeneratorInterface::RELATIVE_PATH));
//            $event->setResponse($response);
//        }
//
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $redirectUrl = $event->getRequest()->get('redirectToUrl');
        if ($response instanceof RedirectResponse && \is_string($redirectUrl)) {
            $response->setTargetUrl($redirectUrl);
        }
    }

    public function onKernelException(ExceptionEvent $event)
    {
        //hide all errors to unauthenticated users
        $exception = $event->getThrowable();

        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                $this->logger->error(($exception instanceof LockedException ? 'log.locked_exception_error' : 'log.privilege_exception_error'), \array_merge(['username' => $exception->getRevision()->getLockBy()], LoggingContext::read($exception->getRevision())));
                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
                            'contentTypeId' => $exception->getRevision()->giveContentType()->getId(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                } else {
                    $response = new RedirectResponse($this->router->generate(Routes::VIEW_REVISIONS, [
                            'type' => $exception->getRevision()->giveContentType()->getName(),
                            'ouuid' => $exception->getRevision()->getOuuid(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                }
                $event->setResponse($response);
            }
            if ($exception instanceof ElasticmsException) {
                $this->logger->error('log.error', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $exception,
                ]);
                $response = new RedirectResponse($this->router->generate('notifications.list', [
                    ]));
                $event->setResponse($response);
            }
            if ($exception instanceof AccessDeniedHttpException && null === $event->getRequest()->getUser()) {
                $response = new RedirectResponse($this->router->generate('fos_user_security_login', [
                    '_target_path' => $event->getRequest()->getRequestUri(),
                ]));
                $event->setResponse($response);
            }
        } catch (Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }

    public function provideTemplateTwigObjects(ControllerEvent $event)
    {
        //TODO: move to twig appextension?
        $repository = $this->doctrine->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $repository->findBy([
                'deleted' => false,
//                 'rootContentType' => true,
        ], [
                'orderKey' => 'ASC',
        ]);

        $this->twig->addGlobal('contentTypes', $contentTypes);

        $envRepository = $this->doctrine->getRepository('EMSCoreBundle:Environment');
        $contentTypes = $envRepository->findBy([
                'inDefaultSearch' => true,
        ]);

        $defaultEnvironments = [];
        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $defaultEnvironments[] = $contentType->getName();
        }

        $this->twig->addGlobal('defaultEnvironments', $defaultEnvironments);
    }
}
