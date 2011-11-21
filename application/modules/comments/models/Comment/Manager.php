<?php
/**
 * This is the Manager class for the comments table.
 *
 * @category Application
 * @package Comments
 * @subpackage Model
 *
 * @version  $Id: Comment.php 2011-11-21 11:59:34Z pavel.machekhin $
 */
class Comments_Model_Comment_Manager extends Core_Model_Manager
{
    /**
     * Get comments for some post and category
     *
     * @param integer $postId
     * @param integer $categoryAlias
     * @return array
     */
    public function findAll($postId, $categoryAlias)
    {
        $categoriesTable = new Categories_Model_Category_Table();
        $users = new Users_Model_Users_Table();

        $select = $this->getDbTable()->select(true);
        $select->setIntegrityCheck(false)
           ->joinLeft(
               array('u' => $users->info('name')),
               'userId = u.id',
               array('login', 'avatar', 'email', 'firstname', 'lastname')
            )
            ->join(
                array('category' => $categoriesTable->info('name')),
                'categoryId = category.id',
                array()
            )
            ->where('postId = ?', $postId)
            ->where('category.alias = ?', $categoryAlias);
        
        return $this->getDbTable()->fetchAll($select);
    }
}