<?php
/**
 * Product groups module for PrestaShop by Szymon Gałąska
 *
 *  @author    Szymon Gałąska <szymon.galaska.9@gmail.com>
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'productgroups/classes/ProductGroup.php';
require_once _PS_MODULE_DIR_ . 'productgroups/classes/ProductGroupProduct.php';

use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

class Productgroups extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'productgroups';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Szymon Gałąska';
        $this->need_instance = 0;
        $this->bootstrap = true;

        $this->displayName = $this->l('Product groups');
        $this->description = $this->l('Create groups consisting of any products. Thumbnails with links of other products from the group will appear on the product page.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '8.0');

        parent::__construct();
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include (dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayProductAdditionalInfo') &&
            $this->registerHook('actionProductDelete') &&
            // $this->registerHook('displayProductBeforeVariants') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->installTab();
    }

    public function uninstall()
    {
        include (dirname(__FILE__) . '/sql/uninstall.php');

        $this->uninstallTab();

        return parent::uninstall();
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminProductGroups';
        $tab->name = [];

        foreach (Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = $this->trans('Product groups', array(), 'Modules.Productgroups.Admin', $lang['locale']);
        }
        $tab->id_parent = Tab::getIdFromClassName('AdminCatalog');
        $tab->module = $this->name;

        return $tab->save();
    }

    private function uninstallTab()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminProductGroups');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return $tab->delete();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/admin.tpl');

        return $output;
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/back.js');
    }

    /**
     * Deletes products from groups when product is deleted
     * @param mixed $params
     * @return mixed
     */
    public function hookActionProductDelete($params)
    {
        $sql = 'DELETE FROM `'._DB_PREFIX_.'product_groups_product` pgp WHERE `id_product` = '.$params['id_product'];

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }   

    public function hookDisplayProductAdditionalInfo($params)
    // public function hookDisplayProductBeforeVariants($params)
    {
        if (!$id_product_groups = $this->getGroups($params['product']['id_product'], true))
            return false;

        $assembler = new ProductAssembler($this->context);
        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever(
                $this->context->link
            ),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        foreach ($id_product_groups as $id_product_group) {
            $productGroup = new ProductGroup($id_product_group['id_product_groups']);
            $products = $productGroup->getProducts(true);

            $products = array_map(function ($rawProduct) use ($presenter, $assembler, $presentationSettings) {
                return $presenter->present(
                    $presentationSettings,
                    $assembler->assembleProduct($rawProduct),
                    $this->context->language
                );
            }, $products);


            $productGroups[] = [
                'products' => $products,
                'name' => $productGroup->getName()
            ];
        }

        $this->context->smarty->assign(
            [
                'productGroups' => $productGroups
            ]
        );

        return $this->display(__FILE__, 'views/templates/hook/productgroups.tpl');
    }

    /**
     * Get products which can be added to group
     * 
     * @param int $id_lang ID of language
     * @param int|null $exclude_group_id ID of group which products will be excluded
     * 
     * @return array
     */
    public static function getProductsForForm($id_lang, $exclude_group_id = null)
    {

        $sql = 'SELECT p.`id_product`, pl.`name`, p.`reference`, CONCAT(pl.`name`, \' reference: \', p.reference) product_name
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                WHERE pl.`id_lang` = ' . (int) $id_lang;

        if ($exclude_group_id !== null)
            $sql .= ' AND p.`id_product` NOT IN (SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_groups_product` WHERE `id_product_groups` = ' . $exclude_group_id . ')';

        $sql .= ' ORDER BY pl.`name`';

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    public function getGroups($id_product, $active = null)
    {
        $query = new DbQuery;

        $query->select('pgp.`id_product_groups`');
        $query->from('product_groups_product', 'pgp');
        $query->leftJoin('product_groups', 'pg', 'pg.`id_product_groups` = pgp.`id_product_groups`');
        $query->orderBy('pg.`name` ASC');
        $query->where('`id_product` = ' . $id_product);
        
        if($active == true)
            $query->where('pg.`active` = 1');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query->build());
    }
}
