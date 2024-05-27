<?php
/**
 * Product groups module for PrestaShop by Szymon Gałąska
 *
 *  @author    Szymon Gałąska <szymon.galaska.9@gmail.com>
 */

class AdminProductGroupsController extends ModuleAdminController
{

    public function __construct()
    {

        $this->table = 'product_groups';
        $this->className = 'ProductGroup';

        $this->bootstrap = true;

        parent::__construct();

        $this->fields_list = [
            'id_product_groups' => [
                'title' => $this->l('ID'),
                'align' => 'left',
                'width' => 25,
            ],
            'name' => [
                'title' => $this->l('Name'),
                'width' => 'auto',
                'search' => 'true',
            ],
            'products' => [
                'title' => $this->trans('Products', [], 'Admin.Global'),
                'width' => 'auto',
                'search' => false,
                'filter' => false,
            ],
            'active' => [
                'title' => $this->l('Active'),
                'type' => 'bool',
                'active' => 'status',
                'width' => 'auto',
                'orderby' => false,
            ],
        ];

        $this->_select = 'COUNT(`id_product`) as products';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'product_groups_product` pgp
        ON (pgp.`id_product_groups` = a.`id_product_groups`)';
        $this->_group = 'GROUP BY `id_product_groups`';

        $this->bulk_actions = ['delete' => ['text' => $this->l('Delete selected'), 'confirm' => $this->l('Delete selected items?')]];
    }
    
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia();
        $this->addJS(_MODULE_DIR_.'productgroups/views/js/select2/select2.min.js');
        $this->addJS(_MODULE_DIR_.'productgroups/views/js/select2/i18n/'.$this->context->language->iso_code.'.js'); 
        $this->addCSS(_MODULE_DIR_.'productgroups/views/css/select2.min.css');
    }

    public function renderList()
    {
        $this->addRowAction('view');
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        return parent::renderList();
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Create new group of products'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => false,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'required' => true,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return parent::renderForm();
    }


    public function renderView()
    {
        if ($id = Tools::getValue('id_product_groups')) {
            $this->table = 'product_groups_product';
            $this->className = 'ProductGroupProduct';
            $this->identifier = 'id_product_groups_product';
            $this->position_identifier = 'id_product_groups_product';
            $this->list_id = 'product_groups_product';


            $this->fields_list = [
                'id_product' => [
                    'title' => $this->l('ID'),
                    'filter_key' => 'p!id_product'
                ],
                'image' => [
                    'title' => $this->trans('Image', [], 'Admin.Global'),
                    'image' => 'p',
                    'search' => false,
                    'orderby' => false,
                    'filter' => false,
                ],
                'reference' => [
                    'title' => $this->trans('Reference', [], 'Admin.Global'),
                    'filter_key' => 'p!reference',
                ],
                'name' => [
                    'title' => $this->trans('Name', [], 'Admin.Global'),
                    'filter_key' => 'pl!name',
                ],
                'position' => [
                    'title' => $this->l('Position'),
                    'position' => 'position',
                    'filter' => false,
                    'search' => false,
                    // 'filter_key' => 'a!position',
                ],
            ];

            $this->_select = 'p.`reference`, pl.`name`, i.`id_image` AS id_image';
            $this->_join = '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p
                ON (p.`id_product` = a.`id_product`)
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON (pl.`id_product` = p.`id_product`)
                LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` i
                ON (a.`id_product` = i.`id_product`)
                ';

            $this->_where = 'AND `id_product_groups` = ' . $id . ' AND i.`cover` = 1';
            $this->_orderBy = 'position';
            $this->_group = '';

            $this->addRowAction('delete');

            $this->processFilter();
            self::$currentIndex = self::$currentIndex . '&id_product_groups=' . (int) $id . '&viewproduct_groups';

            $this->context->smarty->assign(
                array(
                    'current' => self::$currentIndex
                )
            );

            return parent::renderList();
        }
    }

    public function initToolbar()
    {
        if ($this->display == 'view') {
            $this->toolbar_btn['newAttributes'] = array(
                'href' => self::$currentIndex . '&addproduct_groups_product&id_product_groups=' . (int) Tools::getValue('id_product_groups') . '&token=' . $this->token,
                'desc' => $this->trans('Add New Values', array(), 'Admin.Catalog.Feature'),
                'class' => 'toolbar-new'
            );
        }

        parent::initToolbar();
    }

    /**
     * Updates positions of products
     */
    public function ajaxProcessUpdatePositions()
    {
        $way = (int) Tools::getValue('way');
        $id_product_groups_product = (int) Tools::getValue('id');
        $positions = Tools::getValue('product_groups_product');

        if (is_array($positions)) {
            foreach ($positions as $position => $value) {
                $pos = explode('_', $value);

                if ((isset ($pos[1]) && isset ($pos[2])) && (int) $pos[2] === $id_product_groups_product) {
                    if ($product = new ProductGroupProduct((int) $pos[2])) {
                        if (isset ($position) && $product->updatePosition($way, $position)) {
                            echo 'ok position ' . (int) $position . ' for product' . (int) $pos[2] . '\r\n';
                        } else {
                            echo '{"hasError" : true, "errors" : "Can not update the ' . (int) $product->id_product . ' product to position ' . (int) $position . ' "}';
                        }
                    } else {
                        echo '{"hasError" : true, "errors" : "The (' . (int) $id_product_groups_product . ') product cannot be loaded"}';
                    }
                    break;
                }
            }
        }
    }

    public function initContent()
    {
        if (Tools::getValue('addproduct_groups_product') !== false) {

            $this->table = 'product_groups_product';
            $this->className = 'ProductGroupProduct';
            $this->identifier = 'id_product_groups_product';
            $this->content .= $this->renderProductAddForm();

            $this->context->smarty->assign(
                array(
                    'table' => $this->table,
                    'current' => self::$currentIndex,
                    'token' => $this->token,
                    'content' => $this->content,
                )
            );

        } else {
            if(Tools::getValue('submitFilterproduct_groups_product', 0) == 0 && Tools::getIsset('id_product_groups')) {
                $this->processResetFilters('product_groups_product');
            }
            parent::initContent();
        }
    }

    /**
     * Creates form where products can be added to group
     */
    public function renderProductAddForm()
    {
        $products = $this->module->getProductsForForm($this->context->language->id, Tools::getValue('id_product_groups'));

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Add products to group'),
            ],
            'input' => [
                [
                    'title' => $this->trans('Products', [], 'Admin.Global'),
                    'label' => $this->trans('Products', [], 'Admin.Global'),
                    'type' => 'select',
                    'name' => 'id_products[]',
                    'id' => 'product_select',
                    'required' => true,
                    'multiple' => true,
                    'options' => [
                        'query' => $products,
                        'id' => 'id_product',
                        'name' => 'product_name',
                    ],
                ],
                [
                    'type' => 'text',
                    'name' => 'id_product_groups',
                    'class' => 'hidden',
                    'value' => Tools::getValue('id_product_groups'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return parent::renderForm();
    }

    public function postProcess()
    {

        if (Tools::getValue('id_product_groups_product') || Tools::isSubmit('deleteproduct_groups_product') || Tools::isSubmit('submitAddproduct_groups_product') || Tools::isSubmit('submitBulkdeleteproduct_groups_product')) {
            $this->table = 'product_groups_product';
            $this->className = 'ProductGroupProduct';
            $this->identifier = 'id_product_groups_product';
        }

        if (Tools::isSubmit('deleteproduct_groups_product') || Tools::isSubmit('submitAddproduct_groups_product') || Tools::isSubmit('submitBulkdeleteproduct_groups_product')) {
            //Handle adding products to group
            if (Tools::isSubmit('submitAddproduct_groups_product') && Tools::getValue('id_product_groups')) {
                $this->action = 'save';
                $this->processSave();
            }

            //Handle bulk delete of products from group
            if(Tools::isSubmit('submitBulkdeleteproduct_groups_product') && Tools::getValue('product_groups_productBox'))
            {
                foreach(Tools::getValue('product_groups_productBox') as $identifier)
                {
                    $this->deleteProductFromGroup(new ProductGroupProduct((int) $identifier));
                }
                Tools::redirectAdmin(self::$currentIndex . '&id_product_groups=' . Tools::getValue('id_product_groups') . '&viewproduct_groups&conf=1&token=' . Tools::getAdminTokenLite('AdminProductGroups'));
            }

            //Handle deleting product from group
            if (Tools::isSubmit('deleteproduct_groups_product') && Tools::getValue('id_product_groups_product')) {
                    $this->deleteProductFromGroup(new ProductGroupProduct((int) Tools::getValue($this->identifier)));
                    Tools::redirectAdmin(self::$currentIndex . '&id_product_groups=' . Tools::getValue('id_product_groups') . '&viewproduct_groups&conf=1&token=' . Tools::getAdminTokenLite('AdminProductGroups'));
            }
        } else {
            parent::postProcess();
        }
    }

    /**
     * Deletes given product from group
     * 
     * @return bool
     */
    private function deleteProductFromGroup($object)
    {
        if (!$object) {
            $this->errors[] = $this->trans('An error occurred while updating the status for an object.', array(), 'Admin.Notifications.Error') .
                ' <b>' . $this->table . '</b> ' .
                $this->trans('(cannot load object)', array(), 'Admin.Notifications.Error');
        }

        if (!$object->delete()){
            $this->errors[] = $this->trans('Failed to delete the attribute.', array(), 'Admin.Catalog.Notification');
            return false;
        }
        
        return true;
    }

    public function processSave()
    {
        if (Tools::isSubmit('submitAddproduct_groups_product') && Tools::getValue('id_product_groups')) {
            if ($this->table === 'product_groups_product') {
                $productGroup = new ProductGroup(Tools::getValue('id_product_groups'));
                $position = $productGroup->getNextPositionForGroup();
                foreach (Tools::getValue('id_products') as $product) {
                    $_POST['id_product'] = $product;
                    $_POST['position'] = $position;
                    $objects[] = $this->processAdd();
                    $position++;
                }
                $this->redirect_after = self::$currentIndex . '&id_product_groups=' . Tools::getValue('id_product_groups') . '&viewproduct_groups&conf=3&token=' . Tools::getAdminTokenLite('AdminProductGroups');
                return array_search(false, $objects) ? false : true;
            }
        } else
            return parent::processSave();
    }

    public function processAdd()
    {
        if ($this->table === 'product_groups_product') {
            $object = new $this->className();
        }

        $object = parent::processAdd();

        return $object;
    }
    
    /**
     * Delete all products from group
     * 
     * @param ProductGroup $object
     * 
     * @return bool
     */
    private function bulkDeleteProductsFromGroup($object)
    {
        $productGroupsProduct = $object->getProductGroupProductsByProductGroups();

        foreach($productGroupsProduct as $productGroupProduct)
        {   
            $productGroupProduct = new ProductGroupProduct((int) $productGroupProduct['id_product_groups_product']);
            if(!$this->deleteProductFromGroup($productGroupProduct))
                return false;
        }

        return true;
    }

    public function processDelete()
    {
        if($this->table === 'product_groups')
        {
            if (!($object = $this->loadObject(true)))
            {
                return;
            }

            if(!$this->bulkDeleteProductsFromGroup($object))
                return false;
        }
        
        return parent::processDelete();
    }

    public function processBulkDelete()
    {
        if($this->table === 'product_groups')
        {
            foreach($this->boxes as $id)
            {
                $object = new $this->className($id);

                if(!$this->bulkDeleteProductsFromGroup($object))
                    return false;
            }
        }

        return parent::processBulkDelete();
    }
}
