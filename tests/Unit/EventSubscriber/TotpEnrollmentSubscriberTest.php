<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\Enum\UserRole;
use App\EventSubscriber\TotpEnrollmentSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(TotpEnrollmentSubscriber::class)]
final class TotpEnrollmentSubscriberTest extends TestCase
{
    public function testListensToRequests(): void
    {
        self::assertSame([KernelEvents::REQUEST => 'onKernelRequest'], TotpEnrollmentSubscriber::getSubscribedEvents());
    }

    public function testSchoolWithoutTotpIsRedirected(): void
    {
        $vEvent = $this->event('app_school_home');

        $this->subscriber($this->user(UserRole::School, false))->onKernelRequest($vEvent);

        $vResponse = $vEvent->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $vResponse);
        self::assertSame('/double-authentification', $vResponse->getTargetUrl());
    }

    /** @return iterable<string, array{?UserInterface, string, bool, int}> */
    public static function untouchedRequests(): iterable
    {
        $vSchool = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vTutorWithTotp = new User('tuteur@cursus.test', UserRole::Tutor, 'Léo', 'Martin');
        $vTutorWithTotp->enableTotp('JBSWY3DPEHPK3PXP');

        yield 'anonyme' => [null, 'app_login', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'apprenti' => [new User('apprenti@cursus.test', UserRole::Apprentice, 'Léa', 'Martin'), 'app_apprentice_home', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'tuteur déjà équipé' => [$vTutorWithTotp, 'app_tutor_home', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'page d\'activation' => [$vSchool, 'app_totp_setup', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'déconnexion' => [$vSchool, 'app_logout', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'mentions légales' => [$vSchool, 'app_legal_notice', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'confidentialité' => [$vSchool, 'app_legal_privacy', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'conditions' => [$vSchool, 'app_legal_terms', true, HttpKernelInterface::MAIN_REQUEST];
        yield 'sous-requête' => [$vSchool, 'app_school_home', true, HttpKernelInterface::SUB_REQUEST];
        yield 'obligation désactivée' => [$vSchool, 'app_school_home', false, HttpKernelInterface::MAIN_REQUEST];
    }

    #[DataProvider('untouchedRequests')]
    public function testRequestIsLeftAlone(?UserInterface $pUser, string $pRoute, bool $pRequired, int $pRequestType): void
    {
        $vEvent = $this->event($pRoute, $pRequestType);

        $this->subscriber($pUser, $pRequired)->onKernelRequest($vEvent);

        self::assertNull($vEvent->getResponse());
    }

    private function user(UserRole $pRole, bool $pWithTotp): User
    {
        $rUser = new User('compte@cursus.test', $pRole, 'Léa', 'Martin');
        if ($pWithTotp) {
            $rUser->enableTotp('JBSWY3DPEHPK3PXP');
        }

        return $rUser;
    }

    private function subscriber(?UserInterface $pUser, bool $pRequired = true): TotpEnrollmentSubscriber
    {
        $vSecurity = $this->createStub(Security::class);
        $vSecurity->method('getUser')->willReturn($pUser);
        $vUrlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $vUrlGenerator->method('generate')->willReturnMap([['app_totp_setup', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/double-authentification']]);

        $rSubscriber = new TotpEnrollmentSubscriber($vSecurity, $vUrlGenerator, $pRequired);

        return $rSubscriber;
    }

    private function event(string $pRoute, int $pRequestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $vRequest = new Request();
        $vRequest->attributes->set('_route', $pRoute);

        $rEvent = new RequestEvent($this->createStub(HttpKernelInterface::class), $vRequest, $pRequestType);

        return $rEvent;
    }
}
