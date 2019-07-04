<?php
/**
 * 2007-2019 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

use PrestaShop\Module\TutoMailtemplate\MailTemplate\Transformation\CustomMessageColorTransformation;
use PrestaShop\PrestaShop\Core\MailTemplate\FolderThemeScanner;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\LayoutInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\LayoutVariablesBuilderInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\MailTemplateInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\MailTemplateRendererInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCatalogInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollectionInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\Transformation\TransformationCollectionInterface;

class tuto_mailtemplate extends Module
{
    public function __construct()
    {
        $this->name = 'tuto_mailtemplate';
        $this->author = 'PrestaShop';
        $this->version = '1.0.0';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Tutorial MailTemplate', array(), 'Modules.TutoMailtemplate.Admin');
        $this->description = $this->trans('Tutorial for Mail Template in PrestaShop.', array(), 'Modules.TutoMailtemplate.Admin');
        $this->secure_key = Tools::encrypt($this->name);

        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install()
            && $this->registerHooks()
            && $this->installTab()
        ;
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->unregisterHooks()
            && $this->uninstallTab()
        ;
    }

    public function enable($force_all = false)
    {
        return parent::enable($force_all)
            && $this->registerHooks()
            && $this->installTab()
        ;
    }

    public function disable($force_all = false)
    {
        return parent::disable($force_all)
            && $this->unregisterHooks()
            && $this->uninstallTab()
        ;
    }

    private function installTab()
    {
        $tabId = (int) Tab::getIdFromClassName('TutoMailtemplate');
        if (!$tabId) {
            $tabId = null;
        }

        $tab = new Tab($tabId);
        $tab->active = 1;
        $tab->class_name = 'TutoMailtemplate';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Tutorial MailTemplate';
        }
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminMailThemeParent');
        $tab->module = $this->name;

        return $tab->save();
    }

    private function uninstallTab()
    {
        $tabId = (int) Tab::getIdFromClassName('TutoMailtemplate');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return $tab->delete();
    }

    private function registerHooks()
    {
        return
            $this->registerHook(ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK)
            && $this->registerHook(LayoutVariablesBuilderInterface::BUILD_MAIL_LAYOUT_VARIABLES_HOOK)
            && $this->registerHook(MailTemplateRendererInterface::GET_MAIL_LAYOUT_TRANSFORMATIONS)
        ;
    }

    private function unregisterHooks()
    {
        return
            $this->unregisterHook(ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK)
            && $this->unregisterHook(LayoutVariablesBuilderInterface::BUILD_MAIL_LAYOUT_VARIABLES_HOOK)
            && $this->unregisterHook(MailTemplateRendererInterface::GET_MAIL_LAYOUT_TRANSFORMATIONS)
        ;
    }

    public function getContent()
    {
        //This controller actually does not exist, it is used in the tab
        //and is accessible thanks to routing settings with _legacy_link
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('TutoMailtemplate')
        );
    }

    /**
     * @param array $hookParams
     */
    public function hookActionListMailThemes(array $hookParams)
    {
        if (!isset($hookParams['mailThemes'])) {
            return;
        }

        /** @var ThemeCollectionInterface $themes */
        $themes = $hookParams['mailThemes'];

        $this->addTutoTheme($themes);
        $this->addAdditionalLayout($themes);
        $this->addExtendedLayout($themes);
    }

    /**
     * @param ThemeCollectionInterface $themes
     */
    private function addAdditionalLayout(ThemeCollectionInterface $themes)
    {
        /** @var ThemeInterface $theme */
        foreach ($themes as $theme) {
            if (!in_array($theme->getName(), ['classic', 'modern'])) {
                continue;
            }

            // Add a layout to each theme (don't forget to specify the module name)
            $theme->getLayouts()->add(new Layout(
                'additional_template',
                __DIR__ . '/mails/layouts/additional_' . $theme->getName() . '_layout.html.twig',
                '',
                $this->name
            ));
        }
    }

    /**
     * @param ThemeCollectionInterface $themes
     */
    private function addExtendedLayout(ThemeCollectionInterface $themes)
    {
        $theme = $themes->getByName('modern');
        if (!$theme) {
            return;
        }

        // First parameter is the layout name, second one is the module name (empty value matches the core layouts)
        $orderConfLayout = $theme->getLayouts()->getLayout('order_conf', '');
        if (null === $orderConfLayout) {
            return;
        }

        //The layout collection extends from ArrayCollection so it has more feature than it seems..
        //It allows to REPLACE the existing layout easily
        $orderIndex = $theme->getLayouts()->indexOf($orderConfLayout);
        $theme->getLayouts()->offsetSet($orderIndex, new Layout(
            $orderConfLayout->getName(),
            __DIR__ . '/mails/layouts/order_conf.html.twig',
            ''
        ));
    }

    /**
     * @param ThemeCollectionInterface $themes
     */
    private function addTutoTheme(ThemeCollectionInterface $themes)
    {
        $scanner = new FolderThemeScanner();
        $tutoTheme = $scanner->scan(__DIR__ . '/mails/themes/tuto_modern');
        if (null !== $tutoTheme && $tutoTheme->getLayouts()->count() > 0) {
            $themes->add($tutoTheme);
        }
    }

    /**
     * @param array $hookParams
     */
    public function hookActionBuildMailLayoutVariables(array $hookParams)
    {
        if (!isset($hookParams['mailLayout'])) {
            return;
        }

        /** @var LayoutInterface $mailLayout */
        $mailLayout = $hookParams['mailLayout'];
        if ($mailLayout->getModuleName() != $this->name || $mailLayout->getName() != 'additional_template') {
            return;
        }

        $locale = $hookParams['mailLayoutVariables']['locale'];
        if (strpos($locale, 'fr') === 0) {
            $hookParams['mailLayoutVariables']['customMessage'] = 'Mon message personnalisé';
        } else {
            $hookParams['mailLayoutVariables']['customMessage'] = 'My custom message';
        }
    }

    /**
     * @param array $hookParams
     */
    public function hookActionGetMailLayoutTransformations(array $hookParams)
    {
        if (!isset($hookParams['templateType']) ||
            MailTemplateInterface::HTML_TYPE !== $hookParams['templateType'] ||
            !isset($hookParams['mailLayout']) ||
            !isset($hookParams['layoutTransformations'])) {
            return;
        }

        /** @var LayoutInterface $mailLayout */
        $mailLayout = $hookParams['mailLayout'];
        if ($mailLayout->getModuleName() != $this->name) {
            return;
        }

        /** @var TransformationCollectionInterface $transformations */
        $transformations = $hookParams['layoutTransformations'];
        $transformations->add(new CustomMessageColorTransformation('#FF0000'));
    }
}
