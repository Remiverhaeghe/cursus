<?php

namespace App\Tests\Functional\School;

use App\Controller\School\FormationController;
use App\DataFixtures\UserFixtures;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Form\CompetenceBlockType;
use App\Form\CompetenceType;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormationController::class)]
#[CoversClass(FormationRepository::class)]
#[CoversClass(FormationType::class)]
#[CoversClass(CompetenceBlockType::class)]
#[CoversClass(CompetenceType::class)]
final class FormationControllerTest extends AppWebTestCase
{
    private const string SIO = 'BTS Services informatiques aux organisations';
    private const string WEB = 'Développeur web';

    public function testSchoolOnlySeesItsOwnFormations(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $this->aClient->request('GET', '/ecole/formations');

        self::assertResponseStatusCodeSame(200);
        self::assertAnySelectorTextContains('td a', self::SIO);
        self::assertAnySelectorTextNotContains('td a', self::WEB);
    }

    public function testSchoolCreatesAFormation(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', '/ecole/formations');

        $this->aClient->submitForm('Créer la formation', ['formation[name]' => ' Licence pro réseaux ', 'formation[diploma]' => 'Licence professionnelle']);

        $vFormation = $this->find(Formation::class, ['aName' => 'Licence pro réseaux']);
        self::assertResponseRedirects('/ecole/formations/'.$vFormation->getId());
        self::assertSame(UserFixtures::SCHOOL, $vFormation->getManager()->getEmail());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Formation créée.');
        self::assertSelectorTextContains('h1', 'Licence pro réseaux');
        $vList = $this->aClient->request('GET', '/ecole/formations');
        self::assertSame(['BTS Services informatiques aux organisations', 'Licence pro réseaux'], $vList->filter('td a')->each(static fn ($pLink): string => $pLink->text()));
    }

    public function testIncompleteFormationIsRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', '/ecole/formations');

        $this->aClient->submitForm('Créer la formation', ['formation[name]' => '', 'formation[diploma]' => 'BTS']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager()->getRepository(Formation::class)->findOneBy(['aDiploma' => 'BTS', 'aName' => '']));
    }

    public function testShowListsTheReferential(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);

        $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('h1', self::SIO);
        self::assertAnySelectorTextContains('h3', 'B1 · Support et mise à disposition de services informatiques');
        self::assertAnySelectorTextContains('td', 'Gérer le patrimoine informatique');
    }

    public function testAnotherSchoolCannotOpenTheFormation(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::WEB]);

        $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testSchoolAddsABlockThenACompetence(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);
        $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());

        $this->aClient->submitForm('Ajouter le bloc', ['competence_block[code]' => 'B3', 'competence_block[title]' => 'Cybersécurité']);
        self::assertResponseRedirects('/ecole/formations/'.$vFormation->getId());
        $vBlock = $this->find(CompetenceBlock::class, ['aCode' => 'B3']);
        self::assertSame(3, $vBlock->getPosition());

        $vCrawler = $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Bloc ajouté.');
        $vForm = $vCrawler->filter('form[name="competence_'.$vBlock->getId().'"]')->form([
            'competence_'.$vBlock->getId().'[code]' => 'C3.1',
            'competence_'.$vBlock->getId().'[label]' => 'Protéger les données',
        ]);
        $this->aClient->submit($vForm);

        self::assertResponseRedirects('/ecole/formations/'.$vFormation->getId());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Compétence ajoutée.');
        self::assertAnySelectorTextContains('td', 'Protéger les données');
    }

    public function testIncompleteBlockAndCompetenceAreRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);
        $vBlock = $this->find(CompetenceBlock::class, ['aFormation' => $vFormation, 'aCode' => 'B1']);
        $vCrawler = $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());

        $this->aClient->submitForm('Ajouter le bloc', ['competence_block[code]' => '', 'competence_block[title]' => 'Sans code']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="competence_block"]', 'This value should not be blank.');
        self::assertInputValueSame('competence_block[title]', 'Sans code');

        $vForm = $vCrawler->filter('form[name="competence_'.$vBlock->getId().'"]')->form([
            'competence_'.$vBlock->getId().'[code]' => 'C1.9',
            'competence_'.$vBlock->getId().'[label]' => '',
        ]);
        $this->aClient->submit($vForm);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="competence_'.$vBlock->getId().'"]', 'This value should not be blank.');
        self::assertInputValueSame('competence_'.$vBlock->getId().'[code]', 'C1.9');
        self::assertCount(3, $this->refreshedBlock($vBlock)->getCompetences());
    }

    public function testAnotherSchoolCannotChangeTheReferential(): void
    {
        $this->loginAs(UserFixtures::SCHOOL_OTHER);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);
        $vBlock = $this->find(CompetenceBlock::class, ['aFormation' => $vFormation, 'aCode' => 'B1']);

        $this->aClient->request('POST', '/ecole/formations/'.$vFormation->getId().'/blocs', ['competence_block' => ['code' => 'B9', 'title' => 'Intrus']]);
        self::assertResponseStatusCodeSame(403);

        $this->aClient->request('POST', '/ecole/blocs/'.$vBlock->getId().'/competences', ['competence_'.$vBlock->getId() => ['code' => 'C9', 'label' => 'Intrus']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTutorCannotReachTheSchoolArea(): void
    {
        $this->loginAs(UserFixtures::TUTOR);

        $this->aClient->request('GET', '/ecole/formations');

        self::assertResponseStatusCodeSame(403);
    }

    private function refreshedBlock(CompetenceBlock $pBlock): CompetenceBlock
    {
        $rBlock = $this->find(CompetenceBlock::class, ['aId' => $pBlock->getId()]);
        $this->entityManager()->refresh($rBlock);

        return $rBlock;
    }
}
