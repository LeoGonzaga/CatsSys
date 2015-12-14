<?php

namespace Authorization\Controller;

use Authorization\Form\RoleFilter;
use Authorization\Form\RoleForm;
use Database\Service\EntityManagerService;
use Exception;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Authorization\Entity\Role as EntityRole;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RoleController
 *
 * @author marcio
 */
class RoleController extends AbstractActionController
{

    use EntityManagerService;

    public function indexAction()
    {
        $entityManager = $this->getEntityManager();
        try {
            $roles = $entityManager->getRepository('Authorization\Entity\Role')
                    ->findBy([], ['roleId' => 'asc']);
            return new ViewModel(array(
                'roles' => $roles,
            ));
        } catch (Exception $dbSelectException) {
            return new ViewModel(array(
                'error' => $dbSelectException->getMessage(),
            ));
        }
    }

    /**
     * 
     * Done
     * @return ViewModel
     */
    public function createAction()
    {
        $request = $this->getRequest();

        $entityManager = $this->getEntityManager();
        $roles = $entityManager->getRepository('Authorization\Entity\Role')
                ->findAll();

        $formRoles = [];
        foreach ($roles as $role) {
            $formRoles[$role->getRoleId()] = $role->getRoleName();
        }
        $roleForm = new RoleForm($formRoles);


        if ($request->isPost()) {
            $data = $request->getPost();
            $roleForm->setInputFilter(new RoleFilter);
            $roleForm->setData($data);

            if ($roleForm->isValid()) {

                $data = $roleForm->getData();

                $role = new EntityRole();
                $role->setRoleName($data['role_name']);

//                echo '<pre>';
//                print_r($data['role_parent']);
//                exit;

                foreach ($data['role_parent'] as $parentRoleId) {
                    $role->addRole(
                            $entityManager->getReference('Authorization\Entity\Role', $parentRoleId)
                    );
                }

                try {
                    $entityManager->persist($role);
                    $entityManager->flush();

                    $this->redirect()->toRoute('authorization/default', array(
                        'controller' => 'role',
                        'action' => 'index',
                    ));
                } catch (Exception $ex) {
                    return new ViewModel(array(
                        'message' => $ex->getCode() . ': ' . $ex->getMessage(),
                    ));
                }
            }
        }

        return new ViewModel(array(
            'roleForm' => $roleForm,
        ));
    }

    public function editAction()
    {
        $entityManager = $this->getEntityManager();

//        $data = $entityManager->getReference('ATest', $id);
//        $data->setName('ORM Tested');
//        $entityManager->flush();
//        $entityManager->
//        
//        $role1->setRoleName('guest');
//        $entityManager->persist($role1);
//        $entityManager->flush();
    }

    public function deleteAction()
    {
        $roleId = $this->params()->fromRoute('id');

        if ($roleId) {
            $em = $this->getEntityManager();
            try {
                $role = $em->getReference('Authorization\Entity\Role', array('roleId' => $roleId));
                $em->remove($role);
                $em->flush();
                return new ViewModel(array(
                    'message' => 'Role deleted successfully',
                ));
            } catch (Exception $ex) {
                return new ViewModel(array(
                    'message' => $ex->getCode() . ': ' . $ex->getMessage(),
                ));
            }
        }

        return new ViewModel(array(
            'message' => 'Param id can\'t be empty',
        ));
    }

    public function changeActiveUserRoleAction()
    {

        $request = $this->getRequest();

        // If it's ajax call
        if ($request->isXmlHttpRequest()) {
            $data = Json::decode($this->getRequest()->getContent());

            $userContainer = new Container('User');
            if ($userContainer->id && in_array($data->role, $userContainer->allRoles)) {
                $userContainer->activeRole = $data->role;

                return new JsonModel(array(
                    'success' => true,
                    'message' => 'Role changed successfully.',
                ));
            }
            return new JsonModel(array(
                'error' => true,
                'message' => 'You don\'t have the specified role.',
            ));
        }
        return new JsonModel(array(
            'error' => true,
            'message' => 'Request without data.',
        ));
    }

}