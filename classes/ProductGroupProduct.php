<?php
/**
 * Product groups module for PrestaShop by Szymon Gałąska
 *
 *  @author    Szymon Gałąska <szymon.galaska.9@gmail.com>
 */

 class ProductGroupProduct extends ObjectModel
 {
    /**
     * @var int ID of product
     */
    public $id_product;

    /**
     * @var int ID of group
     */
    public $id_product_groups;

    /**
     * @var int position of product in group
     */
    public $position;
  
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'product_groups_product',
        'primary' => 'id_product_groups_product',
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true,
            ],
            'id_product_groups' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true,
            ],
            'position' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true,
            ]
        ],  
    ];

    public function __construct($id = null)
    {
        $this->def = Tag::getDefinition($this);
        $this->setDefinitionRetrocompatibility();

        return parent::__construct($id);
    }

    /**
     * Change position of product
     *
     * @param bool $direction Up (1) or Down (0)
     * @param int  $position  Current position of the attribute
     *
     * @return bool Update result
     */
    public function updatePosition($direction, $position)
    {   
        $sql = '
			SELECT pgp.`id_product_groups_product`, pgp.`position`, pgp.`id_product_groups`
			FROM `'._DB_PREFIX_.'product_groups_product` pgp
			WHERE pgp.`id_product_groups` = '.(int) $this->id_product_groups.'
			ORDER BY pgp.`position` ASC';

        if (!$res = Db::getInstance()->executeS($sql))
            return false;

           
        foreach ($res as $product) {
            if ((int) $product['id_product_groups_product'] == (int) $this->id) {
                $moved_product = $product;
            }
        }
        
        if (!isset($moved_product) || !isset($position))
            return false;

        $res1 = Db::getInstance()->execute('
			UPDATE `'._DB_PREFIX_.'product_groups_product`
			SET `position`= `position` '.($direction ? '- 1' : '+ 1').'
			WHERE `position`
			'.($direction
                ? '> '.(int) $moved_product['position'].' AND `position` <= '.(int) $position
                : '< '.(int) $moved_product['position'].' AND `position` >= '.(int) $position).'
			AND `id_product_groups`='.(int) $moved_product['id_product_groups']
        );


        $res2 = Db::getInstance()->execute('
			UPDATE `'._DB_PREFIX_.'product_groups_product`
			SET `position` = '.(int) $position.'
			WHERE `id_product_groups_product` = '.(int) $moved_product['id_product_groups_product']
        );
        
        return ($res1 && $res2);
    }

    /**
     * Delete object from db
     */
    public function delete()
    {
        self::cleanPositions($this->id_product_groups, $this->position);

        return parent::delete();
    }

    /**
     * Rearranges positions after deleting product
     */
    public static function cleanPositions($id_product_groups, $position)
    {
        $sql = 'UPDATE `'._DB_PREFIX_.'product_groups_product` SET `position` = `position` -1  WHERE
            `id_product_groups` = '.$id_product_groups.' AND `position` > '.$position;

        return Db::getInstance()->execute($sql);
    }
    
}