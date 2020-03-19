<?php
/**
 * Microweber auto provision plesk plugin
 * Author: Bozhidar Slaveykov
 * @email: info@microweber.com
 * Copyright: Microweber CMS
 */

include dirname(__DIR__) . '/library/MicroweberMarketplaceConnector.php';

class IndexController extends pm_Controller_Action
{

    private $taskManager = NULL;

    protected $_accessLevel = [
        'admin',
        'reseller',
        'client'
    ];
    protected $_moduleName = 'Microweber';

    public function init()
    {
        parent::init();

        if (is_null($this->taskManager)) {
            $this->taskManager = new pm_LongTask_Manager();
        }

        // Set module name to views
        $this->view->moduleName = $this->_moduleName;

        // Init tabs for all actions
        $this->view->tabs = [
            [
                'title' => 'Domains',
                'action' => 'index'
            ],
            [
                'title' => 'Install',
                'action' => 'install'
            ]
        ];

        if (pm_Session::getClient()->isAdmin()) {
            $this->view->tabs[] = [
                'title' => 'Versions',
                'action' => 'versions'
            ];
            $this->view->tabs[] = [
                'title' => 'White Label',
                'action' => 'whitelabel'
            ];
            $this->view->tabs[] = [
                'title' => 'Settings',
                'action' => 'settings',
            ];
        }

        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl() . 'css/app.css');
    }

    public function indexAction()
    {

        $this->_checkAppSettingsIsCorrect();

        $this->view->pageTitle = $this->_moduleName . ' - Domains';
        $this->view->list = $this->_getDomainsList();
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/jquery.min.js');
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/index.js');
    }

    public function versionsAction()
    {
        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $this->_checkAppSettingsIsCorrect();

        $release = $this->_getRelease();

        $availableTemplates = Modules_Microweber_Config::getSupportedTemplates();
        if (!empty($availableTemplates)) {
            $availableTemplates = implode(', ', $availableTemplates);
        } else {
            $availableTemplates = 'No templates available';
        }

        $this->view->pageTitle = $this->_moduleName . ' - Versions';

        $this->view->latestVersion = 'unknown';
        $this->view->currentVersion = $this->_getCurrentVersion();
        $this->view->latestDownloadDate = $this->_getCurrentVersionLastDownloadDateTime();
        $this->view->availableTemplates = $availableTemplates;

        if (!empty($release)) {
            $this->view->latestVersion = $release['version'];
        }

        $this->view->updateLink = pm_Context::getBaseUrl() . 'index.php/index/update';
        $this->view->updateTemplatesLink = pm_Context::getBaseUrl() . 'index.php/index/update_templates';
    }

    public function whitelabelAction()
    {

        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $this->_checkAppSettingsIsCorrect();

        $this->view->pageTitle = $this->_moduleName . ' - White Label';

        // WL - white label

        $form = new pm_Form_Simple();
      /*  $form->addElement('text', 'wl_key', [
            'label' => 'White Label Key',
            'value' => pm_Settings::get('wl_key'),
            'placeholder' => 'Place your microweber white label key.'
        ]);*/
        $form->addElement('text', 'wl_brand_name', [
            'label' => 'Brand Name',
            'value' => pm_Settings::get('wl_brand_name'),
            'placeholder' => 'Enter the name of your company.'
        ]);
        $form->addElement('text', 'wl_admin_login_url', [
            'label' => 'Admin login - White Label URL?',
            'value' => pm_Settings::get('wl_admin_login_url'),
            'placeholder' => 'Enter website url of your company.'
        ]);
        $form->addElement('text', 'wl_contact_page', [
            'label' => 'Enable support links?',
            'value' => pm_Settings::get('wl_contact_page'),
            'placeholder' => 'Enter url of your contact page'
        ]);
        $form->addElement('checkbox', 'wl_enable_support_links',
            [
                'label' => 'Enable support links', 'value' => pm_Settings::get('wl_enable_support_links')
            ]
        );
        $form->addElement('textarea', 'wl_powered_by_link',
            [
                'label' => 'Enter "Powered by" text',
                'value' => pm_Settings::get('wl_powered_by_link'),
                'rows' => 3
            ]
        );
        $form->addElement('checkbox', 'wl_hide_powered_by_link',
            [
                'label' => 'Hide "Powered by" link', 'value' => pm_Settings::get('wl_hide_powered_by_link')
            ]
        );
        $form->addElement('text', 'wl_logo_admin_panel', [
            'label' => 'Logo for Admin panel (size: 180x35px)',
            'value' => pm_Settings::get('wl_logo_admin_panel'),
            'placeholder' => ''
        ]);
        $form->addElement('text', 'wl_logo_live_edit_toolbar', [
            'label' => 'Logo for Live-Edit toolbar (size: 50x50px)',
            'value' => pm_Settings::get('wl_logo_live_edit_toolbar'),
            'placeholder' => ''
        ]);
        $form->addElement('text', 'wl_logo_login_screen', [
            'label' => 'Logo for Login screen (max width: 290px)',
            'value' => pm_Settings::get('wl_logo_login_screen'),
            'placeholder' => ''
        ]);
        $form->addElement('checkbox', 'wl_disable_microweber_marketplace',
            [
                'label' => 'Disable Microweber Marketplace', 'value' => pm_Settings::get('wl_disable_microweber_marketplace')
            ]
        );
        $form->addElement('text', 'wl_external_login_server_button_text', [
            'label' => 'External Login Server Button Text',
            'value' => pm_Settings::get('wl_external_login_server_button_text'),
            'placeholder' => 'Login with Microweber Account'
        ]);
        $form->addElement('checkbox', 'wl_external_login_server_enable',
            [
                'label' => 'External Login Server Enable', 'value' => pm_Settings::get('wl_external_login_server_enable')
            ]
        );

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

          /*  // Check license and save it to pm settings
            $licenseCheck = Modules_Microweber_LicenseData::getLicenseData($form->getValue('wl_key'));

            pm_Settings::set('wl_key', $form->getValue('wl_key'));

            if (isset($licenseCheck['status']) && $licenseCheck['status'] == 'active') {*/

               // pm_Settings::set('wl_license_data', json_encode($licenseCheck));
                pm_Settings::set('wl_brand_name', $form->getValue('wl_brand_name'));
                pm_Settings::set('wl_admin_login_url', $form->getValue('wl_admin_login_url'));
                pm_Settings::set('wl_contact_page', $form->getValue('wl_contact_page'));
                pm_Settings::set('wl_enable_support_links', $form->getValue('wl_enable_support_links'));
                pm_Settings::set('wl_powered_by_link', $form->getValue('wl_powered_by_link'));
                pm_Settings::set('wl_hide_powered_by_link', $form->getValue('wl_hide_powered_by_link'));
                pm_Settings::set('wl_logo_admin_panel', $form->getValue('wl_logo_admin_panel'));
                pm_Settings::set('wl_logo_live_edit_toolbar', $form->getValue('wl_logo_live_edit_toolbar'));
                pm_Settings::set('wl_logo_login_screen', $form->getValue('wl_logo_login_screen'));
                pm_Settings::set('wl_disable_microweber_marketplace', $form->getValue('wl_disable_microweber_marketplace'));
                pm_Settings::set('wl_external_login_server_button_text', $form->getValue('wl_external_login_server_button_text'));
                pm_Settings::set('wl_external_login_server_enable', $form->getValue('wl_external_login_server_enable'));

                Modules_Microweber_WhiteLabel::updateWhiteLabelDomains();

                $this->_status->addMessage('info', 'Settings was successfully saved.');

          /*  } else {
                pm_Settings::set('wl_license_data', false);
                $this->_status->addMessage('error', 'The license key is wrong or expired.');
            }*/

            $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/whitelabel']);
        }

        // Show is licensed
        $this->_getLicensedView();

        $this->view->form = $form;
    }

    public function updateAction()
    {

        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $this->_status->addMessage('info', $this->_updateApp());

        return $this->_redirect('index.php/index/versions');
    }

    public function updatetemplatesAction()
    {

        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $this->_status->addMessage('info', $this->_updateTemplates());

        return $this->_redirect('index.php/index/versions');
    }

    public function installAction()
    {

        $this->_checkAppSettingsIsCorrect();

        $this->view->pageTitle = $this->_moduleName . ' - Install';

        $domainsSelect = ['no_select' => 'Select domain to install..'];
        foreach (Modules_Microweber_Domain::getDomains() as $domain) {

            $domainId = $domain->getId();
            $domainName = $domain->getDisplayName();

            $domainsSelect[$domainId] = $domainName;
        }

        $form = new pm_Form_Simple();

        $form->addElement('select', 'installation_domain', [
            'label' => 'Domain',
            'multiOptions' => $domainsSelect,
            'required' => true,
        ]);

        $form->addElement(
            new Zend_Form_Element_Note('create_new_domain_link',
                ['value' => '<a href="/smb/web/add-domain" style="margin-left:175px;top: -15px;position:relative;">Create New Domain</a>']
            )
        );

        $form->addElement('select', 'installation_language', [
            'label' => 'Installation Language',
            'multiOptions' => Modules_Microweber_Config::getSupportedLanguages(),
            'value' => pm_Settings::get('installation_language'),
            'required' => true,
        ]);

        $form->addElement('select', 'installation_template', [
            'label' => 'Installation Template',
            'multiOptions' => Modules_Microweber_Config::getSupportedTemplates(),
            'value' => pm_Settings::get('installation_template'),
            'required' => true,
        ]);

        $form->addElement('radio', 'installation_type', [
            'label' => 'Installation Type',
            'multiOptions' =>
                [
                    'default' => 'Default',
                    'symlink' => 'Sym-Linked'
                ],
            'value' => pm_Settings::get('installation_type'),
            'required' => true,
        ]);

        $form->addElement('select', 'installation_database_driver', [
            'label' => 'Database Driver',
            'multiOptions' => ['mysql' => 'MySQL', 'sqlite' => 'SQL Lite'],
            'value' => pm_Settings::get('installation_database_driver'),
            'required' => true,
        ]);

        $httpHost = '';
        if (isset($_SERVER['HTTP_HOST'])) {
            $httpHost = $_SERVER['HTTP_HOST'];
            $exp = explode(":", $httpHost);
            if (isset($exp[0])) {
                $httpHost = $exp[0];
            }
        }

        $client = pm_Session::getClient();
        $adminEmail = $client->getProperty('email');
        $adminPassword = $this->_getRandomPassword(12, true);
        $adminUsername = str_replace(strrchr($adminEmail, '@'), '', $adminEmail);
        $adminUsername = $adminUsername . '_' . $this->_getRandomPassword(9);

        $form->addElement('text', 'installation_email', [
            'label' => 'Admin Email',
            'value' => $adminEmail,
        ]);
        $form->addElement('text', 'installation_username', [
            'label' => 'Admin Username',
            'value' => $adminUsername,
        ]);
        $form->addElement('text', 'installation_password', [
            'label' => 'Admin Password',
            'value' => $adminPassword,
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $post = $this->getRequest()->getPost();

            $currentVersion = $this->_getCurrentVersion();
            if ($currentVersion == 'unknown') {
                $this->_updateApp();
                $this->_updateTemplates();
            }

            $currentVersion = $this->_getCurrentVersion();
            if ($currentVersion == 'unknown') {
                $this->_status->addMessage('error', 'Can\'t install app because not releases found.');
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/index']);
            }

            $domain = new pm_Domain($post['installation_domain']);
            if (!$domain->getName()) {
                $this->_status->addMessage('error', 'Please, select domain to install microweber.');
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/install']);
            }

            $hostingManager = new Modules_Microweber_HostingManager();
            $hostingManager->setDomainId($domain->getId());
            $hostingProperties = $hostingManager->getHostingProperties();
            if (!$hostingProperties['php']) {
                $this->_status->addMessage('error', 'PHP is not activated on selected domain.');
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/install']);
            }

            $phpHandler = $hostingManager->getPhpHandler($hostingProperties['php_handler_id']);
            if (version_compare($phpHandler['version'], '7.2', '<')) {
                $this->_status->addMessage('error', 'PHP version ' . $phpHandler['version'] . ' is not supported by Microweber. You must install PHP 7.2 or newer.');
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/install']);
            }

            $task = new Modules_Microweber_TaskInstall();
            $task->setParam('domainId', $domain->getId());
            $task->setParam('domainName', $domain->getName());
            $task->setParam('domainDisplayName', $domain->getDisplayName());
            $task->setParam('type', $post['installation_type']);
            $task->setParam('databaseDriver', $post['installation_database_driver']);
            $task->setParam('path', $post['installation_folder']);
            $task->setParam('template', $post['installation_template']);
            $task->setParam('language', $post['installation_language']);
            $task->setParam('email', $post['installation_email']);
            $task->setParam('username', $post['installation_username']);
            $task->setParam('password', $post['installation_password']);

            if (pm_Session::getClient()->isAdmin()) {
                // Run global
                $this->taskManager->start($task, NULL);
            } else {
                // Run for domain
                $this->taskManager->start($task, $domain);
            }

            $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/index']);
            /*
                        $newInstallation = new Modules_Microweber_Install();
                        $newInstallation->setDomainId($post['installation_domain']);
                        $newInstallation->setType($post['installation_type']);
                        $newInstallation->setDatabaseDriver($post['installation_database_driver']);
                        $newInstallation->setPath($post['installation_folder']);
                        $newInstallation->setTemplate($post['installation_template']);
                        $newInstallation->setLanguage($post['installation_language']);

                        if (!empty($post['installation_email'])) {
                            $newInstallation->setEmail($post['installation_email']);
                        }

                        if (!empty($post['installation_username'])) {
                            $newInstallation->setUsername($post['installation_username']);
                        }

                        if (!empty($post['installation_password'])) {
                            $newInstallation->setPassword($post['installation_password']);
                        }

                        var_dump($newInstallation->run());
                        die();*/

        }

        $this->view->form = $form;
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/jquery.min.js');
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/install.js');
    }

    public function checkinstallpathAction()
    {

        $json = [];
        $json['found_app'] = false;
        $json['found_thirdparty_app'] = false;

        try {

            $domainId = (int)$_GET['installation_domain'];
            $domainInstallPath = trim($_GET['installation_folder']);

            $domain = Modules_Microweber_Domain::getUserDomainById($domainId);
            $fileManager = new pm_FileManager($domain->getId());

            if (!empty($domainInstallPath)) {
                $domainInstallPath = $domain->getDocumentRoot() . '/' . $domainInstallPath;
            } else {
                $domainInstallPath = $domain->getDocumentRoot();
            }

            if ($fileManager->fileExists($domainInstallPath . '/index.php')) {
                $json['found_thirdparty_app'] = true;
            }

            if ($fileManager->fileExists($domainInstallPath . '/index.html')) {
                $json['found_thirdparty_app'] = true;
            }

            if ($fileManager->fileExists($domainInstallPath . '/vendor')) {
                $json['found_thirdparty_app'] = true;
            }

            if ($fileManager->fileExists($domainInstallPath . '/config/microweber.php')) {
                $json['found_app'] = true;
            }

            $json['domain_found'] = true;

        } catch (Exception $e) {
            $json['error'] = $e->getMessage();
            $json['domain_found'] = false;
        }

        die(json_encode($json, JSON_PRETTY_PRINT));
    }

    public function startupAction()
    {
        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $release = $this->_getRelease();

        $availableTemplates = Modules_Microweber_Config::getSupportedTemplates();
        if (!empty($availableTemplates)) {
            $availableTemplates = implode(', ', $availableTemplates);
        } else {
            $availableTemplates = 'No templates available';
        }

        $this->view->pageTitle = $this->_moduleName;

        $this->view->latestVersion = 'unknown';
        $this->view->currentVersion = $this->_getCurrentVersion();
        $this->view->latestDownloadDate = $this->_getCurrentVersionLastDownloadDateTime();
        $this->view->availableTemplates = $availableTemplates;

        if (!empty($release)) {
            $this->view->latestVersion = $release['version'];
        }

        $this->view->updateLink = pm_Context::getBaseUrl() . 'index.php/index/update';
        $this->view->updateTemplatesLink = pm_Context::getBaseUrl() . 'index.php/index/update_templates';

        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/jquery.min.js');
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/startup.js');
    }

    public function settingsAction()
    {

        if (!pm_Session::getClient()->isAdmin()) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $this->view->pageTitle = $this->_moduleName . ' - Settings';

        $form = new pm_Form_Simple();

        $form->addElement('select', 'installation_template', [
            'label' => 'Default Installation template',
            'multiOptions' => Modules_Microweber_Config::getSupportedTemplates(),
            'value' => pm_Settings::get('installation_template'),
            'required' => true,
        ]);

        $form->addElement('select', 'installation_language', [
            'label' => 'Default Installation language',
            'multiOptions' => Modules_Microweber_Config::getSupportedLanguages(),
            'value' => pm_Settings::get('installation_language'),
            'required' => true,
        ]);

        $form->addElement('radio', 'installation_type', [
            'label' => 'Default Installation type',
            'multiOptions' =>
                [
                    'default' => 'Default',
                    'symlink' => 'Sym-Linked (saves a big amount of disk space)'
                ],
            'value' => pm_Settings::get('installation_type'),
            'required' => true,
        ]);

        $form->addElement('select', 'installation_database_driver', [
            'label' => 'Database Driver',
            'multiOptions' => ['mysql' => 'MySQL', 'sqlite' => 'SQL Lite'],
            'value' => pm_Settings::get('installation_database_driver'),
            'required' => true,
        ]);

        $form->addElement('text', 'update_app_url', [
            'label' => 'Update App Url',
            'value' => Modules_Microweber_Config::getUpdateAppUrl(),
            //'required' => true,
        ]);

        $form->addElement('text', 'whmcs_url', [
            'label' => 'WHMCS Url',
            'value' => pm_Settings::get('whmcs_url'),
            //'required' => true,
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $success = true;

            // Form proccessing
            pm_Settings::set('installation_language', $form->getValue('installation_language'));
            pm_Settings::set('installation_template', $form->getValue('installation_template'));
            pm_Settings::set('installation_type', $form->getValue('installation_type'));
            pm_Settings::set('installation_database_driver', $form->getValue('installation_database_driver'));

            pm_Settings::set('update_app_url', $form->getValue('update_app_url'));
            pm_Settings::set('whmcs_url', $form->getValue('whmcs_url'));


            $release = $this->_getRelease();
            if (empty($release)) {
                $this->_status->addMessage('error', 'Can\'t get latest version from selected download url.');
                $success = false;
            }

            Modules_Microweber_WhmcsConnector::updateWhmcsConnector();

            if ($success) {
                $this->_status->addMessage('info', 'Settings was successfully saved.');
            }

            $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index.php/index/settings']);
        }

        $this->view->form = $form;
    }

    public function listDataAction()
    {
        $list = $this->_getDomainsList();

        $this->_helper->json($list->fetchData());
    }

    public function domaindetailsAction()
    {
        $json = [];
        $domainFound = false;
        $domainId = (int)$_POST['domain_id'];
        $websiteUrl = $_POST['website_url'];
        $domainDocumentRoot = $_POST['document_root'];
        $domainDocumentRootHash = md5($domainDocumentRoot);

        try {
            $domain = Modules_Microweber_Domain::getUserDomainById($domainId);
        } catch (Exception $e) {
            $domainFound = false;
        }
        if ($domain) {
            $domainFound = true;
        }

        if ($domainFound) {

            $json['languages'] = Modules_Microweber_Config::getSupportedLanguages();

            $json['admin_email'] = 'No information';
            $json['admin_username'] = 'No information';
            $json['admin_password'] = 'No information';
            $json['admin_url'] = 'admin';
            $json['language'] = 'en';

            $domainSettings = $domain->getSetting('mw_settings_' . $domainDocumentRootHash);
            $domainSettings = unserialize($domainSettings);

            if (isset($domainSettings['admin_email']) && !empty($domainSettings['admin_email'])) {
                $json['admin_email'] = $domainSettings['admin_email'];
            }

            if (isset($domainSettings['admin_username']) && !empty($domainSettings['admin_username'])) {
                $json['admin_username'] = $domainSettings['admin_username'];
            }

            if (isset($domainSettings['admin_password']) && !empty($domainSettings['admin_password'])) {
                $json['admin_password'] = $domainSettings['admin_password'];
            }

            if (isset($domainSettings['admin_url']) && !empty($domainSettings['admin_url'])) {
                $json['admin_url'] = $domainSettings['admin_url'];
            }

            if (isset($domainSettings['language']) && !empty($domainSettings['language'])) {
                $json['language'] = $domainSettings['language'];
            }

            $json['domain_id'] = $domainId;

        } else {
            $json['message'] = 'Domain not found.';
            $json['status'] = 'error';
        }

        $this->_helper->json($json);
    }

    public function domainupdateAction()
    {
        $json = [];
        $domainFound = false;
        $domainId = (int)$_POST['domain_id'];
        $adminUsername = $_POST['admin_username'];
        $adminPassword = $_POST['admin_password'];
        $adminEmail = $_POST['admin_email'];
        $adminUrl = $_POST['admin_url'];
        // $websiteUrl = $_POST['website_url'];
        $websiteLanguage = $_POST['website_language'];
        $domainDocumentRoot = $_POST['document_root'];
        $domainDocumentRootHash = md5($domainDocumentRoot);

        try {
            $domain = Modules_Microweber_Domain::getUserDomainById($domainId);
        } catch (Exception $e) {
            $domainFound = false;
        }
        if ($domain) {
            $domainFound = true;
        }

        if ($domainFound) {

            $artisan = new Modules_Microweber_ArtisanExecutor();
            $artisan->setDomainId($domain->getId());
            $artisan->setDomainDocumentRoot($domainDocumentRoot);

            // Change Language
            $artisan->exec([
                'microweber:option',
                'language',
                $websiteLanguage,
                'website'
            ]);

            // Change Admin Details
            $commandAdminDetailsResponse = $artisan->exec([
                'microweber:change-admin-details',
                '--username=' . $adminUsername,
                '--newPassword=' . $adminPassword,
                '--newEmail=' . $adminEmail
            ]);

            // Update Server details
            $artisan->exec([
                'microweber:server-set-config',
                '--key=admin_url',
                '--value=' . $adminUrl
            ]);

            $artisan->exec([
                'microweber:server-set-config',
                '--key=site_lang',
                '--value=' . $websiteLanguage
            ]);

            // Cache clear
            $artisan->exec([
                'microweber:server-clear-cache'
            ]);

            $successChange = false;
            if (isset($commandAdminDetailsResponse['stdout'])) {
                if (strpos(strtolower($commandAdminDetailsResponse['stdout']), 'done') !== false) {
                    $successChange = true;
                }
            }

            if ($successChange) {

                $domainSettings = $domain->getSetting('mw_settings_' . $domainDocumentRootHash);
                $domainSettings = unserialize($domainSettings);

                $domainSettings['admin_email'] = $adminEmail;
                $domainSettings['admin_password'] = $adminPassword;
                $domainSettings['admin_url'] = $adminUrl;
                $domainSettings['website_language'] = $websiteLanguage;

                $domain->setSetting('mw_settings_' . $domainDocumentRootHash, serialize($domainSettings));

                $json['message'] = 'Domain settings are updated successfully.';
                $json['status'] = 'success';
            } else {
                $json['message'] = 'Can\'t change domain settings.';
                $json['status'] = 'error';
            }
        } else {
            $json['message'] = 'Domain not found.';
            $json['status'] = 'error';
        }

        $this->_helper->json($json);
    }

    public function domainloginAction()
    {

        $domainFound = false;
        $domainId = (int)$_POST['domain_id'];
        $websiteUrl = $_POST['website_url'];
        $domainDocumentRoot = $_POST['document_root'];

        try {
            $domain = Modules_Microweber_Domain::getUserDomainById($domainId);
        } catch (Exception $e) {
            $domainFound = false;
        }
        if ($domain) {
            $domainFound = true;
        }

        if (!$domainFound) {
            return $this->_redirect('index.php/index/error?type=permission');
        }

        $artisan = new Modules_Microweber_ArtisanExecutor();
        $artisan->setDomainId($domain->getId());
        $artisan->setDomainDocumentRoot($domainDocumentRoot);

        $commandResponse = $artisan->exec(['microweber:generate-admin-login-token']);

        if (!empty($commandResponse['stdout'])) {

            $token = $commandResponse['stdout'];
            $token = str_replace(' ', false, $token);
            $token = str_replace(PHP_EOL, false, $token);
            $token = trim($token);

            return $this->_redirect('http://www.' . $websiteUrl . '/api/user_login?secret_key=' . $token);
        }

        return $this->_redirect('index.php/index/error?type=permission');
    }

    public function errorAction()
    {
        $this->view->pageTitle = $this->_moduleName . ' - Error';
        $this->view->errorMessage = 'You don\'t have permissions to see this page.';
    }

    private function _getRandomPassword($length = 16, $complex = false)
    {
        $alphabet = 'ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        if ($complex) {
            $alphabet .= '-=~!@#$%^&*()_+,./<>?;:[]{}\|';
        }

        $pass = [];
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }

    private function _updateApp()
    {

        $release = $this->_getRelease();

        if (empty($release)) {
            return 'No releases found.';
        }

        $downloadLog = '';

        $downloadLog .= pm_ApiCli::callSbin('unzip_app_version.sh', [base64_encode($release['url']), Modules_Microweber_Config::getAppSharedPath()])['stdout'];

        // Whm Connector
        $downloadUrl = 'https://github.com/microweber-dev/whmcs-connector/archive/master.zip';
        $downloadLog .= pm_ApiCli::callSbin('unzip_app_modules.sh', [base64_encode($downloadUrl), Modules_Microweber_Config::getAppSharedPath()])['stdout'];

        // Login with token
        $downloadUrl = 'https://github.com/microweber-modules/login_with_token/archive/master.zip';
        $downloadLog .= pm_ApiCli::callSbin('unzip_app_modules.sh', [base64_encode($downloadUrl), Modules_Microweber_Config::getAppSharedPath()])['stdout'];

        Modules_Microweber_WhmcsConnector::updateWhmcsConnector();

        return $downloadLog;
    }

    private function _updateTemplates()
    {

        $templates = $this->_getTemplatesUrl();

        foreach ($templates as $template) {

            $task = new Modules_Microweber_TaskTemplateDownload();
            $task->setParam('downloadUrl', $template['download_url']);
            $task->setParam('targetDir', $template['target_dir']);

            $this->taskManager->start($task, NULL);
        }

        return 'Downloading templates task started.';
    }

    private function _getLicensedView()
    {
        $this->view->isLicensed = false;

        $licenseData = pm_Settings::get('wl_license_data');
        if (!empty($licenseData)) {

            $licenseData = json_decode($licenseData, TRUE);

            if ($licenseData['status'] == 'active') {

                $this->view->isLicensed = true;
                $this->view->dueOn = $licenseData['due_on'];
                $this->view->registeredName = $licenseData['registered_name'];
                $this->view->relName = $licenseData['rel_name'];
                $this->view->regOn = date("Y-m-d", strtotime($licenseData['reg_on']));
                $this->view->billingCycle = $licenseData['billing_cycle'];

            }
        }

        $this->view->buyLink = pm_Context::getBuyUrl();

        $pmLicense = pm_License::getAdditionalKey();
        if (isset($pmLicense->getProperties('product')['name'])) {
            if (strpos($pmLicense->getProperties('product')['name'], 'microweber') !== false) {
                $this->view->isLicensed = true;
            }
        }
    }

    private function _checkAppSettingsIsCorrect()
    {
        $currentVersion = $this->_getCurrentVersion();
        if ($currentVersion == 'unknown') {

            if (empty(pm_Settings::get('installation_language'))) {
                pm_Settings::set('installation_language', 'en');
            }

            if (empty(pm_Settings::get('installation_type'))) {
                pm_Settings::set('installation_type', 'symlink');
            }

            if (empty(pm_Settings::get('installation_database_driver'))) {
                pm_Settings::set('installation_database_driver', 'sqlite');
            }

            header("Location: " . pm_Context::getBaseUrl() . 'index.php/index/startup');
            exit;
        }
    }

    private function _getCurrentVersionLastDownloadDateTime()
    {
        $manager = new pm_ServerFileManager();

        $version_file = $manager->fileExists(Modules_Microweber_Config::getAppSharedPath() . 'version.txt');
        if ($version_file) {
            $version = filectime(Modules_Microweber_Config::getAppSharedPath() . 'version.txt');
            if ($version) {
                return date('Y-m-d H:i:s', $version);
            }
        }
    }

    private function _getCurrentVersion()
    {
        $manager = new pm_ServerFileManager();

        $versionFile = $manager->fileExists(Modules_Microweber_Config::getAppSharedPath() . 'version.txt');

        $version = 'unknown';
        if ($versionFile) {
            $version = $manager->fileGetContents(Modules_Microweber_Config::getAppSharedPath() . 'version.txt');
            $version = strip_tags($version);
        }

        return $version;
    }

    private function _getAppInstalations()
    {

        $data = [];

        foreach (Modules_Microweber_Domain::getDomains() as $domain) {

            $installationsFind = [];

            $domainDocumentRoot = $domain->getDocumentRoot();
            $domainName = $domain->getName();
            $domainDisplayName = $domain->getDisplayName();
            $domainIsActive = $domain->isActive();
            $domainCreation = $domain->getProperty('cr_date');

            $appVersion = 'unknown';
            $installationType = 'unknown';

            $fileManager = new pm_FileManager($domain->getId());

            $allDirs = $fileManager->scanDir($domainDocumentRoot, true);
            foreach ($allDirs as $dir) {
                if (!is_dir($domainDocumentRoot . '/' . $dir . '/config/')) {
                    continue;
                }
                if (is_file($domainDocumentRoot . '/' . $dir . '/config/microweber.php')) {
                    $installationsFind[] = $domainDocumentRoot . '/' . $dir . '/config/microweber.php';
                }
            }

            if (is_dir($domainDocumentRoot . '/config/')) {
                if (is_file($domainDocumentRoot . '/config/microweber.php')) {
                    $installationsFind[] = $domainDocumentRoot . '/config/microweber.php';
                }
            }

            if (!empty($installationsFind)) {

                foreach ($installationsFind as $appInstallationConfig) {

                    if (strpos($appInstallationConfig, 'backup-files') !== false) {
                        continue;
                    }

                    $appInstallation = str_replace('/config/microweber.php', false, $appInstallationConfig);

                    // Find app in main folder
                    if ($fileManager->fileExists($appInstallation . '/version.txt')) {
                        $appVersion = $fileManager->fileGetContents($appInstallation . '/version.txt');
                    }

                    if (is_link($appInstallation . '/vendor')) {
                        $installationType = 'Symlinked';
                    } else {
                        $installationType = 'Standalone';
                    }

                    $domainNameUrl = $appInstallation;
                    $domainNameUrl = str_replace('/var/www/vhosts/', false, $domainNameUrl);
                    $domainNameUrl = str_replace($domainName . '/httpdocs', $domainName, $domainNameUrl);
                    $domainNameUrl = str_replace($domainName, $domainDisplayName, $domainNameUrl);

                    $loginToWebsite = '<form method="post" class="js-open-settings-domain" action="' . pm_Context::getBaseUrl() . 'index.php/index/domainlogin" target="_blank">';
                    $loginToWebsite .= '<input type="hidden" name="website_url" value="' . $domainNameUrl . '" />';
                    $loginToWebsite .= '<input type="hidden" name="domain_id" value="' . $domain->getId() . '" />';
                    $loginToWebsite .= '<input type="hidden" name="document_root" value="' . $appInstallation . '" />';
                    $loginToWebsite .= '<button type="submit" name="login" value="1" class="btn btn-info"><img src="/modules/catalog/images/open-in-browser-a3af024.png" alt=""> Login to website</button>';
                    $loginToWebsite .= '<button type="button" onclick="openSetupForm(this)" name="setup" value="1" class="btn btn-info"><i class="icon-manage" style="color:#000;"></i> Setup</button>';
                    $loginToWebsite .= '</form>';

                    $data[] = [
                        'domain' => '<a href="http://' . $domainNameUrl . '" target="_blank">' . $domainNameUrl . '</a> ',
                        'created_date' => $domainCreation,
                        'type' => $installationType,
                        'app_version' => $appVersion,
                        'document_root' => $appInstallation,
                        'active' => ($domainIsActive ? 'Yes' : 'No'),
                        'action' => $loginToWebsite
                    ];

                }
            }
        }

        return $data;
    }

    private function _getDomainsList()
    {

        $options = [
            'pageable' => true,
            'defaultSortField' => 'active',
            'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_DOWN,
        ];

        $list = new pm_View_List_Simple($this->view, $this->_request, $options);
        $list->setData($this->_getAppInstalations());
        $list->setColumns([
            // pm_View_List_Simple::COLUMN_SELECTION,
            'domain' => [
                'title' => 'Domain',
                'noEscape' => true,
                'searchable' => true,
            ],
            'created_date' => [
                'title' => 'Created at',
                'noEscape' => true,
                'searchable' => true,
            ],
            'type' => [
                'title' => 'Type',
                'noEscape' => true,
                'sortable' => false,
            ],
            'app_version' => [
                'title' => 'App Version',
                'noEscape' => true,
                'sortable' => false,
            ],
            'active' => [
                'title' => 'Active',
                'noEscape' => true,
                'sortable' => false,
            ],
            'document_root' => [
                'title' => 'Document Root',
                'noEscape' => true,
                'sortable' => false,
            ],
            'action' => [
                'title' => 'Action',
                'noEscape' => true,
                'searchable' => false,
            ]
        ]);

        // Take into account listDataAction corresponds to the URL /list-data/
        $list->setDataUrl(['action' => 'list-data']);

        return $list;
    }

    private function _getTemplatesUrl()
    {

        $connector = new MicroweberMarketplaceConnector();
        $connector->set_whmcs_url(Modules_Microweber_Config::getWhmcsUrl());

        $templatesUrl = $connector->get_templates_download_urls();

        return $templatesUrl;
    }

    private function _getRelease()
    {

        $releaseUrl = Modules_Microweber_Config::getUpdateAppUrl();
        $releaseUrl .= '?api_function=get_download_link&get_last_version=';

        return $this->_getJson($releaseUrl);
    }

    private function _getJson($url)
    {

        $tuCurl = curl_init();
        curl_setopt($tuCurl, CURLOPT_URL, $url);
        curl_setopt($tuCurl, CURLOPT_VERBOSE, 0);
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($tuCurl, CURLOPT_SSL_VERIFYPEER, false);

        $tuData = curl_exec($tuCurl);
        if (!curl_errno($tuCurl)) {
            $info = curl_getinfo($tuCurl);
            $debug = 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'];
        } else {
            $debug = 'Curl error: ' . curl_error($tuCurl);
        }

        curl_close($tuCurl);

        $json = json_decode($tuData, TRUE);

        return $json;
    }

}
