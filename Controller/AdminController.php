<?php

namespace CPANA\HierarchyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * This controller contains CRUD actions which should be available only for some admin users
 * It is using the class GroupHierarchyManager by accessing the service 'group_hierarchy_manager_neo4j'.
 *
 * @author Cristian Pana  <cristianpana86@yahoo.com>
 *
 * @Route("/admin")
 */
class AdminController extends Controller
{
    /**
     * @Route("/home", name="ghm_admin_home")
     */
    public function indexAction(Request $request)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');

        $form = $this->get('form.factory')->createNamedBuilder('search_user', 'form',  null, array())
            ->add('search', 'text')
            ->add('Search user', 'submit')
            ->getForm();

        $form_g = $this->get('form.factory')->createNamedBuilder('search_group', 'form',  null, array())
            ->add('search', 'text')
            ->add('Search group', 'submit')
            ->getForm();

        $form->handleRequest($request);
        $form_g->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searched_word = $form['search']->getData();

            $rows = $hm->searchUsersByProperty($searched_word, $hm->getDefaultPropertyUser());
            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Admin:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'rows' => $rows,
                                    'display_value_user' => $hm->getDefaultPropertyUser(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Admin:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'message' => "The user: $searched_word was not found!",
                                    'display_value_user' => $hm->getDefaultPropertyUser(),
                                ));
            }
        }

        if ($form_g->isSubmitted() && $form_g->isValid()) {
            $searched_word = $form_g['search']->getData();

            $rows = $hm->searchGroupByProperty($searched_word, $hm->getDefaultPropertyGroup());
            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Admin:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'rows_g' => $rows,
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Admin:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'message_g' => "The group: $searched_word was not found!",
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            }
        }

        return $this->render('CPANAHierarchyBundle:Admin:index.html.twig',
                        array(
                            'form' => $form->createView(),
                            'form_g' => $form_g->createView(),
                            'display_value_group' => $hm->getDefaultPropertyGroup(),
                        ));
    }

    /**
     * @Route("/show/user/{node_id}", name="ghm_admin_show_user")
     */
    public function showUserAction($node_id)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $node = $hm->getClient()->getNode($node_id);

        if ($node != null) {
            $direct_manager = $hm->getManagerOfUser($node);
            $peers = $hm->getPeersOfUser($node);

            //this code is used to show the complete hierarchy from root to current user

            $all_groups_above = $hm->getGroupHierarchyOfUser($node);

            if ($all_groups_above != null) {
                $current_group = $all_groups_above->current();
                $all_managers_above = array();

                foreach ($all_groups_above as $group) {
                    array_push($all_managers_above, $hm->getManagerOfGroupNode($group));
                }

                $all_managers_above = array_reverse($all_managers_above);

                //----------- breadcrumb all groups above -----------------------

                $groups_hierarchy_array = array();

                if (count($all_groups_above) > 0) {
                    foreach ($all_groups_above as $group) {
                        array_push($groups_hierarchy_array, $group);
                    }
                } else {
                    array_push($groups_hierarchy_array, $current_group);
                }
                $groups_hierarchy_array = array_reverse($groups_hierarchy_array);
                //---------------------------------------------------------------
            } else {
                $groups_hierarchy_array = null;
                $all_managers_above = null;
                $current_group = null;
            }
            $directs_users = null;
            $directs_groups = null;

            if ($hm->isUserManager($node)) {
                $directs_users = $hm->getDirectsUsers($node);
                $directs_groups = $hm->getDirectsGroups($node);
            }

            return $this->render('CPANAHierarchyBundle:Admin:show_user.html.twig',
                            array(
                                 'node' => $node,
                                 'current_group' => $current_group,
                                 'manager' => $direct_manager,
                                 'all_managers' => $all_managers_above,
                                 'groups_hierarchy' => $groups_hierarchy_array,
                                 'peers' => $peers,
                                 'directs_users' => $directs_users,
                                 'directs_groups' => $directs_groups,
                                 'defaultPropertyUser' => $hm->getDefaultPropertyUser(),
                                 'defaultPropertyGroup' => $hm->getDefaultPropertyGroup(),
                            ));
        } else {
            return $this->render('CPANAHierarchyBundle:Error:error_user.html.twig',
                            array(
                                'id' => $node_id,
                            ));
        }
    }

    /**
     * @Route("/show/group/{node_id}", name="ghm_admin_show_group")
     */
    public function showGroupAction($node_id)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $group_node = $hm->getClient()->getNode($node_id);

        $all_groups_above = $hm->getGroupHierarchyOfGroup($group_node);

        if ($all_groups_above != null) {
            $groups_hierarchy_array = array();

            if (count($all_groups_above) > 0) {
                foreach ($all_groups_above as $group) {
                    array_push($groups_hierarchy_array, $group);
                }
            } else {
                array_push($groups_hierarchy_array, $group_node);
            }

            $part_of_group = $groups_hierarchy_array[1];
            $groups_hierarchy_array = array_reverse($groups_hierarchy_array);
        } else {
            $part_of_group = null;
            $groups_hierarchy_array = null;
        }
        $group_managers = $hm->getManagersOfGroup($group_node);
        $user_members = $hm->getMembersOfGroup($group_node);
        $groups_members = $hm->getGroupsDirectsOfGroup($group_node);

        return $this->render('CPANAHierarchyBundle:Admin:show_group.html.twig',
                        array(
                             'node' => $group_node,
                             'managers' => $group_managers,
                             'part_of_group' => $part_of_group,
                             'groups_hierarchy' => $groups_hierarchy_array,
                             'user_members' => $user_members,
                             'groups_members' => $groups_members,
                             'defaultPropertyUser' => $hm->getDefaultPropertyUser(),
                             'defaultPropertyGroup' => $hm->getDefaultPropertyGroup(),
                        ));
    }
    /**
     * @Route("/node/{id}/{property}/{value}", name="ghm_admin_edit_property")
     */
    public function propertyEditAction($id, $property, $value)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $node = $hm->getClient()->getNode($id);

        if ($node != null) {
            $node->setProperty($property, $value)
                  ->save();
        }

        return $this->render('CPANAHierarchyBundle:Admin:edit_property.html.twig', array());
    }

    /**
     * @Route("/create/group", name="ghm_admin_create_group")
     */
    public function createGroupAction(Request $request)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');

        $form = $this->get('form.factory')->createNamedBuilder('create_group', 'form',  null, array())
            ->add('group_name', 'text')
            ->add('Create group', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $properties = array();
            $properties[$hm->defaultPropertyGroup] = $form->get('group_name')->getData();

            $hm->createGroup($properties);

            $message = 'You have successfully created a new group!';

            return $this->render('CPANAHierarchyBundle:Admin:create_group.html.twig',
                            array(
                                'message' => $message,
                                'form' => $form->createView(),
                            ));
        }

        return $this->render('CPANAHierarchyBundle:Admin:create_group.html.twig',
                        array(
                            'form' => $form->createView(),
                        ));
    }

    /**
     * @Route("/create/user", name="ghm_admin_create_user")
     */
    public function createUserAction(Request $request)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');

        $form = $this->get('form.factory')->createNamedBuilder('create_user', 'form',  null, array())
            ->add('user_identification', 'text')
            ->add('Create user', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $properties = array();
            $properties[$hm->defaultPropertyUser] = $form->get('user_identification')->getData();

            $hm->createUser($properties);

            $message = 'You have successfully created a new user!';

            return $this->render('CPANAHierarchyBundle:Admin:create_user.html.twig',
                            array(
                                'message' => $message,
                                'form' => $form->createView(),
                            ));
        }

        return $this->render('CPANAHierarchyBundle:Admin:create_user.html.twig',
                        array(
                            'form' => $form->createView(),
                        ));
    }

    /**
     * @Route("/edit/group/properties/{groupId}", name="ghm_admin_edit_group_properties")
     */
    public function editGroupPropertiesAction(Request $request, $groupId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $groupNode = $hm->getClient()->getNode($groupId);

        $form_builder = $this->get('form.factory')->createNamedBuilder('edit_group_properties', 'form',  null, array());

        foreach ($groupNode->getProperties() as $key => $value) {
            $form_builder->add($key, 'text', array('data' => $value));
        }

        $form_builder->add('Save', 'submit');
        $form = $form_builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //persist changes
            $data = $form->getData();
            $hm->editGroupProperty($groupNode, $data);

            $message = 'You have successfully edited the group properties!';

            return $this->render('CPANAHierarchyBundle:Admin:edit_group_properties.html.twig',
                            array(
                                'message' => $message,
                                'form' => $form->createView(),
                            ));
        }

        return $this->render('CPANAHierarchyBundle:Admin:edit_group_properties.html.twig',
                        array(
                            'form' => $form->createView(),
                        ));
    }

    /**
     * @Route("/delete/group/{groupId}", name="ghm_admin_delete_group")
     */
    public function deleteGroupAction(Request $request, $groupId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $groupNode = $hm->getClient()->getNode($groupId);

        $hm->deleteNode($groupNode);

        return $this->render('CPANAHierarchyBundle:Admin:delete_group.html.twig', array());
    }

    /**
     * @Route("/create/relation/from_group/{groupId}", name="ghm_admin_create_group_relation")
     */
    public function createGroupRelationAction(Request $request, $groupId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $groupNode = $hm->getClient()->getNode($groupId);

        $form = $this->get('form.factory')->createNamedBuilder('search_group', 'form',  null, array())
            ->add('search', 'text')
            ->add('Search group', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searched_word = $form['search']->getData();

            $hm = $this->get('group_hierarchy_manager_neo4j');
            $rows = $hm->searchGroupByProperty($searched_word, $hm->getDefaultPropertyGroup());

            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Admin:create_group_relation.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'groupOrigin' => $groupNode,
                                    'rows' => $rows,
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Admin:create_group_relation.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'groupOrigin' => $groupNode,
                                    'message' => "The group: $searched_word was not found!",
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            }
        }

        return $this->render('CPANAHierarchyBundle:Admin:create_group_relation.html.twig',
                        array(
                            'form' => $form->createView(),
                            'groupOrigin' => $groupNode,
                        ));
    }

    /**
     * @Route("/save/group/relation/{start_id}/{end_id}", name="ghm_admin_save_group_relation")
     */
    public function saveGroupRelationAction(Request $request, $start_id, $end_id)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($start_id);
        $endNode = $hm->getClient()->getNode($end_id);

        $start_nodes_array = array();
        array_push($start_nodes_array, $startNode);

        $hm->createRelations($start_nodes_array, $endNode, $hm->getDefRelTypeGroupToGroup());

        return $this->render('CPANAHierarchyBundle:Admin:save_group_relation.html.twig', array());
    }

    /**
     * @Route("/delete/group/relation/{startId}/{endId}", name="ghm_admin_delete_group_relation")
     */
    public function deleteGroupRelationAction(Request $request, $startId, $endId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($startId);
        $endNode = $hm->getClient()->getNode($endId);

        $hm->deleteRelations($startNode, $endNode, $hm->getDefRelTypeGroupToGroup());

        return $this->render('CPANAHierarchyBundle:Admin:delete_group_relation.html.twig', array());
    }

    /**
     * @Route("/delete/user_to_group/relation/{startId}/{endId}", name="ghm_admin_delete_user_group_relation")
     */
    public function deleteUserToGroupRelationAction(Request $request, $startId, $endId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($startId);
        $endNode = $hm->getClient()->getNode($endId);

        $hm->deleteRelations($startNode, $endNode, $hm->getDefRelTypeUserToGroup());

        return $this->render('CPANAHierarchyBundle:Admin:delete_group_relation.html.twig', array());
    }

    /**
     * @Route("/delete/group_to_group/relation/{startId}/{endId}", name="ghm_admin_delete_group_group_relation")
     */
    public function deleteGroupToGroupRelationAction(Request $request, $startId, $endId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($startId);
        $endNode = $hm->getClient()->getNode($endId);

        $hm->deleteRelations($startNode, $endNode, $hm->getDefRelTypeGroupToGroup());

        return $this->render('CPANAHierarchyBundle:Admin:delete_group_relation.html.twig', array());
    }

    /**
     * @Route("/edit/relation/{startId}/{endId}", name="ghm_admin_edit_relation")
     */
    public function editRelationPropertyAction(Request $request, $startId, $endId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($startId);
        $endNode = $hm->getClient()->getNode($endId);

        $relations = $hm->getAllRelationsBetween2Nodes($startNode, $endNode);

        $relation = $relations[0];

        $form_builder = $this->get('form.factory')->createNamedBuilder('edit_relation_properties', 'form',  null, array());

        foreach ($relation->getProperties() as $key => $value) {
            $form_builder->add($key, 'text', array('data' => $value));
        }

        $form_builder->add('Save', 'submit');
        $form = $form_builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //persist changes
            $data = $form->getData();
            $hm->editRelationProperty($relation, $data);

            $message = 'You have successfully edited the relation properties!';

            return $this->render('CPANAHierarchyBundle:Admin:edit_relation_properties.html.twig',
                            array(
                                'message' => $message,
                                'form' => $form->createView(),
                                'node' => $endNode,
                            ));
        }

        return $this->render('CPANAHierarchyBundle:Admin:edit_relation_properties.html.twig',
                        array(
                            'form' => $form->createView(),
                            ));
    }

    /**
     * @Route("/create/relation/user_to_group/{userId}", name="ghm_admin_create_user_to_group_relation")
     */
    public function createUserToGroupRelationAction(Request $request, $userId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $userNode = $hm->getClient()->getNode($userId);

        $form = $this->get('form.factory')->createNamedBuilder('search_group', 'form',  null, array())
            ->add('search', 'text')
            ->add('Search group', 'submit')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searched_word = $form['search']->getData();

            $hm = $this->get('group_hierarchy_manager_neo4j');
            $rows = $hm->searchGroupByProperty($searched_word, $hm->getDefaultPropertyGroup());

            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Admin:create_user_group_relation.html.twig',                array(
                                    'form' => $form->createView(),
                                    'userOrigin' => $userNode,
                                    'rows' => $rows,
                                    'display_value_user' => $hm->getDefaultPropertyUser(),
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Admin:create_user_group_relation.html.twig',                array(
                                    'form' => $form->createView(),
                                    'userOrigin' => $userNode,
                                    'message' => "The group: $searched_word was not found!",
                                    'display_value_user' => $hm->getDefaultPropertyUser(),
                                    'display_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            }
        }

        return $this->render('CPANAHierarchyBundle:Admin:create_user_group_relation.html.twig',
                        array(
                            'form' => $form->createView(),
                            'userOrigin' => $userNode,
                            'display_value_user' => $hm->getDefaultPropertyUser(),
                            'display_value_group' => $hm->getDefaultPropertyGroup(),
                        ));
    }

    /**
     * @Route("/save/user_to_group/relation/{start_id}/{end_id}", name="ghm_admin_save_user_to_group_relation")
     */
    public function saveUserToGroupRelationAction(Request $request, $start_id, $end_id)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $startNode = $hm->getClient()->getNode($start_id);
        $endNode = $hm->getClient()->getNode($end_id);

        $start_nodes_array = array();
        array_push($start_nodes_array, $startNode);

        $hm->createRelations($start_nodes_array, $endNode, $hm->getDefRelTypeUserToGroup());

        return $this->render('CPANAHierarchyBundle:Admin:save_user_group_relation.html.twig',
                        array(
                            'node' => $startNode,
                        ));
    }

    /**
     * @Route("/delete/user/{userId}", name="ghm_admin_delete_user")
     */
    public function deleteUserAction(Request $request, $userId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $userNode = $hm->getClient()->getNode($userId);

        $hm->deleteNode($userNode);

        return $this->render('CPANAHierarchyBundle:Admin:delete_user.html.twig', array());
    }

    /**
     * @Route("/edit/user/properties/{userId}", name="ghm_admin_edit_user_properties")
     */
    public function editUserPropertiesAction(Request $request, $userId)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $userNode = $hm->getClient()->getNode($userId);

        $form_builder = $this->get('form.factory')->createNamedBuilder('edit_user_properties', 'form',  null, array());

        foreach ($userNode->getProperties() as $key => $value) {
            $form_builder->add($key, 'text', array('data' => $value));
        }

        $form_builder->add('Save', 'submit');
        $form = $form_builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            //persist changes
            $data = $form->getData();
            $hm->editGroupProperty($userNode, $data);

            $message = 'You have successfully edited the user properties!';

            return $this->render('CPANAHierarchyBundle:Admin:edit_user_properties.html.twig',
                            array(
                                'message' => $message,
                                'form' => $form->createView(),
                            ));
        }

        return $this->render('CPANAHierarchyBundle:Admin:edit_user_properties.html.twig',
                        array(
                            'form' => $form->createView(),
                        ));
    }
}
