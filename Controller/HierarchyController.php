<?php

namespace CPANA\HierarchyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * This controller contains actions for regular user who can just browse throuh hierarchy
 * It is using the class GroupHierarchyManager by accessing the service 'group_hierarchy_manager_neo4j'.
 *
 * @author Cristian Pana  <cristianpana86@yahoo.com>
 */
class HierarchyController extends Controller
{
    /**
     * @Route("/home", name="ghm_home")
     */
    public function indexAction(Request $request)
    {
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

            $hm = $this->get('group_hierarchy_manager_neo4j');
            $rows = $hm->searchUsersByProperty($searched_word, $hm->getDefaultPropertyUser());
            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Default:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'rows' => $rows,
                                    'default_value_user' => $hm->getDefaultPropertyUser(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Default:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'message' => "The user: $searched_word was not found!",
                                    'default_value_user' => $hm->getDefaultPropertyUser(),
                                ));
            }
        }

        if ($form_g->isSubmitted() && $form_g->isValid()) {
            $searched_word = $form_g['search']->getData();

            $hm = $this->get('group_hierarchy_manager_neo4j');
            $rows = $hm->searchGroupByProperty($searched_word, $hm->getDefaultPropertyGroup());
            if ($rows != null) {
                return $this->render('CPANAHierarchyBundle:Default:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'rows_g' => $rows,
                                    'default_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            } else {
                return $this->render('CPANAHierarchyBundle:Default:index.html.twig',
                                array(
                                    'form' => $form->createView(),
                                    'form_g' => $form_g->createView(),
                                    'message_g' => "The group: $searched_word was not found!",
                                    'default_value_group' => $hm->getDefaultPropertyGroup(),
                                ));
            }
        }

        $hm = $this->get('group_hierarchy_manager_neo4j');

        return $this->render('CPANAHierarchyBundle:Default:index.html.twig',
                        array(
                            'form' => $form->createView(),
                            'form_g' => $form_g->createView(),
                            'default_value_group' => $hm->getDefaultPropertyGroup(),
                        ));
    }

    /**
     * @Route("/show/user/{node_id}", name="ghm_show_user")
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

            return $this->render('CPANAHierarchyBundle:Default:show_user.html.twig',
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
     * @Route("/show/group/{node_id}", name="ghm_show_group")
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

        return $this->render('CPANAHierarchyBundle:Default:show_group.html.twig',
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
     * @Route("/managers/{matricule}", name="ghm_manager")
     */
    public function managersAction($matricule)
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $node = $hm->getOneUserByIdSQL($matricule);

        //$rows = $hm->getGroupHierarchyOfUser($matricule);

        $rows = $hm->getManagerOfUser($node);

        return $this->render('CPANAHierarchyBundle:Default:managers.html.twig',
                        array(
                            'result' => $rows,
                        ));
    }

    /**
     * @Route("/search_id", name="ghm_search_id_sql")
     */
    public function searchAction()
    {
        $hm = $this->get('group_hierarchy_manager_neo4j');
        $node = $hm->getOneUserByIdSQL($matricule);

        //$rows = $hm->getGroupHierarchyOfUser($matricule);

        $rows = $hm->getManagerOfUser($node);

        return $this->render('CPANAHierarchyBundle:Default:managers.html.twig',
                        array(
                            'result' => $rows,
                        ));
    }
}
