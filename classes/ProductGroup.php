<?php
/**
 * Product groups module for PrestaShop by Szymon Gałąska
 *
 *  @author    Szymon Gałąska <szymon.galaska.9@gmail.com>
 */

class ProductGroup extends ObjectModel
{
    /**
     * @var string Name of group
     */
    public $name;

    /**
     * @var bool Status of group
     */
    public $active;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'product_groups',
        'primary' => 'id_product_groups',
        'fields' => [
            'name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
                'required' => false,
            ],
            'active' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
                'required' => true,
            ],
        ],
    ];

    public function __construct($id = null, $name = null)
    {
        $this->def = Tag::getDefinition($this);
        $this->setDefinitionRetrocompatibility();

        if ($id)
            return parent::__construct($id);
        elseif ($name && Validate::isGenericName($name)) {
            $row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
            SELECT *
            FROM `' . _DB_PREFIX_ . 'product_groups`
            WHERE `name` LIKE \'' . pSQL($name) . '\'');

            if ($row) {
                $this->id = (int) $row['id_product_groups'];
                $this->name = $row['name'];
            }
        }
    }

    /**
     * Get products associated with this group
     * 
     * @return array
     */
    public function getProducts($active = null)
    {
        if (!$this->id)
            return [];

        $query = new DbQuery();

        $query->select('pgp.`id_product`');
        $query->from('product_groups_product', 'pgp');
        $query->where('`id_product_groups` = '.$this->id);

        if($active === true)
        {
            $query->leftJoin('product', 'p', 'p.`id_product` = pgp.`id_product`');
            $query->where('`active` = 1');
        }
        $query->orderBy('`position` ASC');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query->build());
    }


    /**
     * Get name of group
     * 
     * @return string|null
     */
    public function getName()
    {
        if (!$this->id)
            return '';

        $query = new DbQuery();

        $query->select('`name`');
        $query->from('product_groups', 'pg');
        $query->where('pg.`id_product_groups` = ' . $this->id);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query->build());
    }

    /**
     * Get list of products associated with this group
     * 
     * @return array
     */
    public function getProductGroupProductsByProductGroups()
    {
        if (!$this->id)
            return [];

        $query = new DbQuery();

        $query->select('pgp.`id_product_groups_product`');
        $query->from('product_groups_product', 'pgp');
        $query->where('pgp.`id_product_groups` = ' . $this->id);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query->build());
    }

    /**
     * Get number of next position
     * 
     * @return int
     */
    public function getNextPositionForGroup()
    {
        $sql = 'SELECT `position` FROM `'._DB_PREFIX_.'product_groups_product` WHERE `id_product_groups` = '.$this->id.' ORDER BY `position` DESC';
        $position = Db::getInstance()->getValue($sql);
        
        return $position !== false ? $position + 1 : 0;
    }
}