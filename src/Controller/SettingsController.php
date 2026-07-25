<?php

namespace Cloudexus\Controller;

use Cloudexus\Model\Core\SettingModel;

class SettingsController extends BaseController
{
    private SettingModel $settings;

    public function __construct()
    {
        parent::__construct();
        $this->settings = new SettingModel();
        $this->activeMenu = 'settings-company';
    }

    public function company(): void
    {
        $this->requireAdmin();

        $this->pageTitle = $this->t('settings.company_title');
        $this->render('settings/company.twig', [
            'company' => $this->settings->company(),
        ]);
    }

    public function companyUpdate(): void
    {
        $this->requireAdmin();

        $fields = ['name', 'address', 'tax_number', 'bank_account', 'email', 'phone'];
        $pairs = [];
        foreach ($fields as $field) {
            $pairs['company.' . $field] = trim($_POST[$field] ?? '');
        }

        if ($pairs['company.name'] === '') {
            $this->flashError($this->t('settings.company_name_required'));
            $this->redirect('/settings/company');
        }

        $this->settings->setMany($pairs);
        $this->flashSuccess($this->t('settings.company_saved'));
        $this->redirect('/settings/company');
    }
}
