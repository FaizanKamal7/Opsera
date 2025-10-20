<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserType extends AbstractType
{
    public function __construct(
        /** @var string[] */
        private readonly array $enabledLocales,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // For the full reference of options defined by each form field type
        // see https://symfony.com/doc/current/reference/forms/types.html

        // By default, form fields include the 'required' attribute, which enables
        // the client-side form validation. This means that you can't test the
        // server-side validation errors from the browser. To temporarily disable
        // this validation, set the 'required' attribute to 'false':
        // $builder->add('title', null, ['required' => false, ...]);

        $enabledLocales = $this->enabledLocales;

        $builder
            ->add('username', TextType::class, [
                'label' => 'label.username',
                'disabled' => true,
            ])
            ->add('fullName', TextType::class, [
                'label' => 'label.fullname',
            ])
            ->add('email', EmailType::class, [
                'label' => 'label.email',
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'label.language',
                'choice_loader' => new CallbackChoiceLoader(static function () use ($enabledLocales) {
                    $list = array_values(array_filter(Languages::getLanguageCodes(), static fn ($v) => \in_array($v, $enabledLocales, true)));
                    $list = array_filter(array_combine($list, array_map(static function ($languageCode) {
                        try {
                            return Languages::getName($languageCode, $languageCode);
                        } catch (MissingResourceException) {
                            return null;
                        }
                    }, $list)));

                    return array_flip($list);
                }),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    /**
     * @param array<array-key, string> $enabledLocales
     *
     * @return array<string, string>
     */
    public static function formatEnabledLanguagesList(array $enabledLocales): array
    {
        $list = array_values(array_filter(Languages::getLanguageCodes(), static fn ($v) => \in_array($v, $enabledLocales, true)));

        return array_filter(array_combine($list, array_map(static function ($languageCode) {
            try {
                return Languages::getName($languageCode, $languageCode);
            } catch (MissingResourceException) {
                return null;
            }
        }, $list)));
    }
}
