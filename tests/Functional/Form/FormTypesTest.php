<?php

namespace App\Tests\Functional\Form;

use App\Entity\Formation;
use App\Form\ApprenticeshipType;
use App\Form\CohortType;
use App\Form\CompanyType;
use App\Form\CompetenceBlockType;
use App\Form\CompetenceType;
use App\Form\Data\ApprenticeshipData;
use App\Form\Data\CohortData;
use App\Form\Data\CompanyData;
use App\Form\Data\CompetenceBlockData;
use App\Form\Data\CompetenceData;
use App\Form\Data\EvaluationData;
use App\Form\Data\FormationData;
use App\Form\Data\NewPasswordData;
use App\Form\Data\PasswordResetRequestData;
use App\Form\EvaluationType;
use App\Form\FormationType;
use App\Form\NewPasswordType;
use App\Form\PasswordResetRequestType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/** Chaque formulaire : objet de saisie attendu, libellés, aides et valeurs par défaut des champs vides. */
#[CoversClass(FormationType::class)]
#[CoversClass(CompetenceBlockType::class)]
#[CoversClass(CompetenceType::class)]
#[CoversClass(CompanyType::class)]
#[CoversClass(CohortType::class)]
#[CoversClass(ApprenticeshipType::class)]
#[CoversClass(EvaluationType::class)]
#[CoversClass(NewPasswordType::class)]
#[CoversClass(PasswordResetRequestType::class)]
final class FormTypesTest extends KernelTestCase
{
    /** @return iterable<string, array{class-string, class-string, array<string, string>, list<string>}> */
    public static function simpleForms(): iterable
    {
        yield 'formation' => [FormationType::class, FormationData::class, ['name' => 'Nom de la formation', 'diploma' => 'Diplôme préparé'], ['name', 'diploma']];
        yield 'bloc' => [CompetenceBlockType::class, CompetenceBlockData::class, ['code' => 'Code du bloc', 'title' => 'Intitulé'], ['code', 'title']];
        yield 'compétence' => [CompetenceType::class, CompetenceData::class, ['code' => 'Code', 'label' => 'Libellé'], ['code', 'label']];
        yield 'entreprise' => [CompanyType::class, CompanyData::class, ['name' => 'Raison sociale', 'city' => 'Ville'], ['name', 'city']];
        yield 'promotion' => [CohortType::class, CohortData::class, ['name' => 'Nom de la promotion', 'startYear' => 'Année d\'entrée'], ['name']];
        yield 'mot de passe oublié' => [PasswordResetRequestType::class, PasswordResetRequestData::class, ['email' => 'Adresse e-mail'], ['email']];
    }

    /**
     * @param class-string          $pType
     * @param class-string          $pDataClass
     * @param array<string, string> $pLabels
     * @param list<string>          $pTextFields champs dont la valeur vide devient une chaîne vide, jamais null
     */
    #[DataProvider('simpleForms')]
    public function testSimpleFormsUseTheirInputObjectAndLabels(string $pType, string $pDataClass, array $pLabels, array $pTextFields): void
    {
        $vForm = $this->factory()->create($pType, null, ['csrf_protection' => false]);

        self::assertSame($pDataClass, $vForm->getConfig()->getDataClass());
        self::assertSame($pLabels, $this->labels($vForm));

        $vForm->submit([]);
        $vData = $vForm->getData();
        self::assertInstanceOf($pDataClass, $vData);
        foreach ($pTextFields as $vField) {
            self::assertSame('', $vData->{$vField});
        }
    }

    public function testFormationDiplomaHasAHelp(): void
    {
        self::assertSame('Par exemple : BTS, titre professionnel, licence professionnelle', $this->factory()->create(FormationType::class)->get('diploma')->getConfig()->getOption('help'));
    }

    public function testApprenticeshipForm(): void
    {
        $vForm = $this->factory()->create(ApprenticeshipType::class);

        self::assertSame(ApprenticeshipData::class, $vForm->getConfig()->getDataClass());
        self::assertSame([
            'apprentice' => 'Apprenti',
            'tutor' => 'Tuteur',
            'company' => 'Entreprise',
            'startDate' => 'Début du contrat',
            'endDate' => 'Fin du contrat',
        ], $this->labels($vForm));
        $vCompanies = array_map(static fn ($pChoice): string => $pChoice->label, $vForm->get('company')->createView()->vars['choices']);
        self::assertSame(['Atelier Lumen (Lille)', 'Studio Brique (Roubaix)'], array_values($vCompanies));
    }

    public function testEvaluationForm(): void
    {
        $vFormation = $this->formation('Développeur web');
        $vForm = $this->factory()->create(EvaluationType::class, null, ['formation' => $vFormation]);

        self::assertSame(EvaluationData::class, $vForm->getConfig()->getDataClass());
        self::assertSame(['competence' => 'Compétence', 'level' => 'Niveau', 'comment' => 'Commentaire'], $this->labels($vForm));
        self::assertTrue($vForm->get('level')->getConfig()->getOption('expanded'));
        self::assertFalse($vForm->get('comment')->getConfig()->getOption('required'));

        $vView = $vForm->createView();
        $vLevels = array_map(static fn ($pChoice): string => $pChoice->label, $vView['level']->vars['choices']);
        self::assertSame(['0 · Non abordée', '1 · Découverte', '2 · En cours d\'acquisition', '3 · Acquise', '4 · Maîtrisée'], array_values($vLevels));
        $vGroups = array_keys($vView['competence']->vars['choices']);
        self::assertSame(['B1 · Développer la partie front-end', 'B2 · Développer la partie back-end'], $vGroups);
        $vFirstGroup = $vView['competence']->vars['choices']['B1 · Développer la partie front-end'];
        self::assertSame(['C1.1 · Maquetter une application', 'C1.2 · Réaliser une interface web'], array_values(array_map(static fn ($pChoice): string => $pChoice->label, $vFirstGroup->choices)));
    }

    public function testEvaluationFormNeedsAFormation(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(EvaluationType::class, null, ['formation' => 'pas une formation']);
    }

    public function testNewPasswordForm(): void
    {
        $vForm = $this->factory()->create(NewPasswordType::class);
        $vPassword = $vForm->get('password');

        self::assertSame(NewPasswordData::class, $vForm->getConfig()->getDataClass());
        self::assertInstanceOf(PasswordType::class, $vPassword->get('first')->getConfig()->getType()->getInnerType());
        self::assertSame('Nouveau mot de passe', $vPassword->get('first')->getConfig()->getOption('label'));
        self::assertSame('12 caractères au moins', $vPassword->get('first')->getConfig()->getOption('help'));
        self::assertSame('Confirmation', $vPassword->get('second')->getConfig()->getOption('label'));
        self::assertSame(['autocomplete' => 'new-password'], $vPassword->get('first')->getConfig()->getOption('attr'));
        self::assertSame(['autocomplete' => 'new-password'], $vPassword->get('second')->getConfig()->getOption('attr'));
        self::assertSame('Les deux mots de passe ne correspondent pas.', $vPassword->getConfig()->getOption('invalid_message'));
    }

    /**
     * @param FormInterface<mixed> $pForm
     *
     * @return array<string, string>
     */
    private function labels(FormInterface $pForm): array
    {
        $rLabels = [];
        foreach ($pForm as $vName => $vChild) {
            if ('_token' !== $vName) {
                $rLabels[(string) $vName] = (string) $vChild->getConfig()->getOption('label');
            }
        }

        return $rLabels;
    }

    private function factory(): FormFactoryInterface
    {
        return static::getContainer()->get(FormFactoryInterface::class);
    }

    private function formation(string $pName): Formation
    {
        $rFormation = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Formation::class)->findOneBy(['aName' => $pName]);
        self::assertNotNull($rFormation);

        return $rFormation;
    }
}
