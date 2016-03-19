<?php

namespace CPANA\HierarchyBundle\Hierarchy;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Cypher\Query;
use Everyman\Neo4j\Relationship;
use Everyman\Neo4j\Query\ResultSet;


/**
 * This class contains a series of functions for manipulating group based hierachies
 * It is built on top of Everyman\Neo4j\ library
 *
 * @author Cristian Pana  <cristianpana86@yahoo.com>
 */
class GroupHierarchyManager
{
	/**
     * @var Everyman\Neo4j\Client
     */
    private $client;
	
	/**
	 * Exaple:  PART_OF, group N--PART_OF--> group X
     * @var string
     */	
    private $defRelTypeGroupToGroup;
	
	/**
	 * Exaple:  MEMBER_OF, user N--MEMBER_OF--> group X
     * @var string
     */	
    private $defRelTypeUserToGroup;
	
	
	/**
	 * Neo4j id of the hierarchy root node
     * @var string
     */	
    private $rootGroupId;
	
    /**
	 * Value for property "role" of the default user-group when the user is manager of given group
     * @var string
     */	
    private $managerRole; 
	
    /**
	 * Default Property of Group which is displayed now in views. Should represent the name of the group.
     * @var string
     */                                     
    public $defaultPropertyGroup;
	
	/**
	 * Default Property of User which is displayed now in views. Should represent the name of the user.
     * @var string
     */    
    public $defaultPropertyUser;
	

	/**
	 * Constructor
	 *
	 * @param string   $user
	 * @param string   $password
	 * @param string   $defRelTypeGroupToGroup
	 * @param string   $defRelTypeUserToGroup
	 * @param string   $rootGroupId
	 * @param string   $managerRole
	 * @param string   $defaultPropertyGroup
	 * @param string   $defaultPropertyUser
	 */
    public function __construct(
                                $user,
                                $password,
                                $defRelTypeGroupToGroup,
                                $defRelTypeUserToGroup,
								$rootGroupId,
								$managerRole,
								$defaultPropertyGroup,
								$defaultPropertyUser
                                ) 
	{
        $this->client = new Client();
        $this->client->getTransport()->setAuth($user, $password);

        $this->defRelTypeGroupToGroup = $defRelTypeGroupToGroup;
        $this->defRelTypeUserToGroup = $defRelTypeUserToGroup;
		$this->rootGroupId  = $rootGroupId;
		$this->managerRole  = $managerRole;
		$this->defaultPropertyGroup = $defaultPropertyGroup;
		$this->defaultPropertyUser  = $defaultPropertyUser;

    }
	/**
	 * @return Everyman\Neo4j\Client
	 */
    public function getClient()
    {
        return $this->client;
    }
	
	/**
	 * @return string
	 */
    public function getDefaultPropertyUser()
    {
        return $this->defaultPropertyUser;
    }
	
	/**
	 * @return string
	 */
    public function getDefaultPropertyGroup()
    {
        return $this->defaultPropertyGroup;
    }
	
	/**
	 * @return string
	 */
	public function getDefRelTypeGroupToGroup()
    {
        return $this->defRelTypeGroupToGroup;
    }
	
	/**
	 * @return string
	 */
	public function getDefRelTypeUserToGroup()
    {
        return $this->defRelTypeUserToGroup;
    }

    /**
	 * Return the hierarchy of managers above one user node
	 *
	 * @param  Everyman\Neo4j\Node   node
	 * @return array
	 */
    public function getManagersHierarchyAboveUser($node)
    {
        $all_groupes_above = $this->getGroupHierarchyOfUser($node);
        $all_managers_above = array();

        foreach ($all_groupes_above as $group) {
            array_push($all_managers_above, $this->getManagerOfGroupNode($group));
        }

        $all_managers_above = array_reverse($all_managers_above);
    }

    /**
	 * Return the hierarchy of of groups above one group node
	 *
	 * @param  Everyman\Neo4j\Node   group_node
	 * @return row| null
	 */
    public function getGroupHierarchyOfGroup($group_node)
    {
        $id = $group_node->getId();

        $queryString = ' MATCH p=group-[r:PART_OF*..]->d '.
                        ' WHERE ID(group)='.$id.' '.
                        ' AND ID(d)='.$this->rootGroupId.' '.
                        ' RETURN nodes(p) as nodes';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result->current()->current();
        } else {
            return;
        }
    }

    /**
	 * Return the hierarchy of of groups above one user node
	 *
	 * @param  Everyman\Neo4j\Node   node
	 * @return row| null
	 */
    public function getGroupHierarchyOfUser($node)
    {
        $id = $node->getId();

        $queryString = ' MATCH n-[rel:MEMBER_OF]->group '.
                        ' WHERE id(n)='.$id.' '.
                        ' WITH group '.
                        ' MATCH p=group-[r:PART_OF*..]->d '.
                        ' WHERE ID(d)='.$this->rootGroupId.' '.
                        ' RETURN nodes(p) as nodes';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result->current()->current();
        } else {
            return;
        }
    }

	/**
	 * Get manager of the given Group Node (just one manager even if there are move than one!!!)
	 *
	 * @param  Everyman\Neo4j\Node   group_node
	 * @return Everyman\Neo4j\Query\Row | null
	 */
    public function getManagerOfGroupNode($group_node)
    {
        $id = $group_node->getId();

        $queryString = ' MATCH n-[rel:'.$this->defRelTypeUserToGroup.']->r'.
                        ' WHERE ID(r)='.$id." and rel.role='".$this->managerRole."' ".
                        ' return n;';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result->current()->current();
        } else {
            return;
        }
    }

	/**
	 * Get peers of user (users with same role, in relation with same group)
	 *
	 * @param  Everyman\Neo4j\Node   user_node
	 * @return Everyman\Neo4j\Query\ResultSet | null
	 */
    public function getPeersOfUser($user_node)
    {
        $id = $user_node->getId();

        //first if the group where the user belongs(default rel) has manager (default relation with //property role=manager), not including himself if the user is manager in that group " x <> n"
        $queryString = ' MATCH n-[rel1:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE id(n)='.$id.' '.
                        ' WITH group,n,rel1 '.
                        ' MATCH x-[rel:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE rel.role=rel1.role  and x <> n '.
                        ' RETURN x';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result;
        } else {
            return;
        }
    }

	/**
	 * Returns True if user has outgoing relation with property equal to manager ($managerRole)
	 *
	 * @param  Everyman\Neo4j\Node   user_node
	 * @return boolean
	 */
    public function isUserManager($user_node)
    {
        $id = $user_node->getId();
        $is_manager = false;

        $outgoingRelationships = $user_node->getRelationships(array(), Relationship::DirectionOut);

        if (count($outgoingRelationships) != 0) {
            foreach ($outgoingRelationships as $relation) {
                if ($relation->getProperty('role') == $this->managerRole) {
                    $is_manager = true;

                    return $is_manager;
                }
            }
        }

        return $is_manager;
    }

	/**
	 * Get direct subordinates of user. The user should have role manager (isUserManager)
	 *
	 * @param  Everyman\Neo4j\Node   user_node
	 * @return row| null
	 */
    public function getDirectsUsers($user_node)
    {
        $id = $user_node->getId();

        $queryString = ' MATCH n-[rel1:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE id(n)='.$id.' '.
                        ' WITH group,n,rel1 '.
                        ' MATCH x-[rel:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE  x <> n '.
                        ' RETURN x';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result;
        } else {
            return;
        }
    }

	/**
	 * Get groups subordinates to the group to which the user ($user_node) belongs
	 *
	 * @param  Everyman\Neo4j\Node   user_node
	 * @return Everyman\Neo4j\Query\Row | null
	 */
    public function getDirectsGroups($user_node)
    {
        $id = $user_node->getId();

        $queryString = ' MATCH n-[rel1:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE id(n)='.$id.' '.
                        ' WITH group,n,rel1 '.
                        ' MATCH x-[rel:'.$this->defRelTypeGroupToGroup.']->group '.
                        ' RETURN x';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result;
        } else {
            return;
        }
    }

	/**
	 * Get managers of given group node
	 *
	 * @param  Everyman\Neo4j\Node   group_node
	 * @return array
	 */
    public function getManagersOfGroup($group_node)
    {
        $id = $group_node->getId();

        $incomingRelationships = $group_node->getRelationships(array(), Relationship::DirectionIn);
        $manager_user_array = array();

        if (count($incomingRelationships) != 0) {
            foreach ($incomingRelationships as $relation) {
                if ($relation->getProperty('role') == $this->managerRole) {
                    array_push($manager_user_array, $relation->getStartNode());
                }
            }
        }

        return $manager_user_array;
    }

	/**
	 * Get members of given group (user nodes found in a relation with group node)
	 *
	 * @param  Everyman\Neo4j\Node   group_node
	 * @return array
	 */
    public function getMembersOfGroup($group_node)
    {
        $id = $group_node->getId();

        $incomingRelationships = $group_node->getRelationships(array($this->defRelTypeUserToGroup), Relationship::DirectionIn);
        $members_array = array();

        if (count($incomingRelationships) != 0) {
            foreach ($incomingRelationships as $relation) {
                if ($relation->getProperty('role') != $this->managerRole) {
                    array_push($members_array, $relation->getStartNode());
                }
            }
        }

        return $members_array;
    }

	/**
	 * Return a list of groups subordinates to the given group
	 *
	 * @param  Everyman\Neo4j\Node   group_node
	 * @return array
	 */
    public function getGroupsDirectsOfGroup($group_node)
    {
        $id = $group_node->getId();

        $incomingRelationships = $group_node->getRelationships(array($this->defRelTypeGroupToGroup), Relationship::DirectionIn);

        $groups_array = array();

        if (count($incomingRelationships) != 0) {
            foreach ($incomingRelationships as $relation) {
                array_push($groups_array, $relation->getStartNode());
            }
        }

        return $groups_array;
    }
	
    /**
     *  Get direct manager. If the user itself is manager  or if the group to which the user belongs   *  does not have a manager search in hierarchy for first manager.
     *  If the user is not manager and the group to which he belongs has a manager, return it.
	 *
	 *  @param   Everyman\Neo4j\Node           start_node
     *  @param   Everyman\Neo4j\Node           end_node
	 *  @return  Everyman\Neo4j\Node | null
     */
    public function getManagerOfUser($user_node)
    {
        $id = $user_node->getId();

        //first if the group where the user belongs(default rel) has manager, not including himself if the user is manager in that group " x <> n"
        $queryString =  ' MATCH n-[rel:'.$this->defRelTypeUserToGroup.']->group '.
                        ' WHERE id(n)='.$id.' '.
                        ' WITH group,n '.
                        ' MATCH x-[rel:'.$this->defRelTypeUserToGroup.']->group '.
                        " WHERE rel.role='".$this->managerRole."' and x <> n ".
                        ' RETURN x';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result->current()->current();
        } else {
            //if program reaches this branch of if-else, means that the group where the user belongs 
            // does not have manager. 
            // get the  hierarchy of groups including the group to which the user belongs

            $h_nodes = $this->getGroupHierarchyOfUser($user_node);

            if ($h_nodes !== null) {
                //In foreach loop will pick the first manager from the group hierarchy up to Root
                foreach ($h_nodes as $g_node) {
                    $manager = $this->getManagerOfGroupNode($g_node);

                    //if the user is itself a manager do not return him as it's his own manager!!!
                    if (($manager != null) and ($manager->getId() != $user_node->getId())) {
                        return $manager;
                    }
                }
            }

            return;
        }
    }

    /**
	 *  Get all relations between two nodes
	 *	 
	 *  @param  Everyman\Neo4j\Node   start_node
     *  @param  Everyman\Neo4j\Node   end_node
     *  @return  array | null
     */
    public function getAllRelationsBetween2Nodes($start_node, $end_node)
    {
        $startId = $start_node->getId();
        $endId = $end_node->getId();

        $queryString = 'MATCH n-[rel]-r '
                        .'WHERE id(n)='.$startId.' and id(r)='.$endId.' '
                        .'return rel;';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result != null) {
            $relationships = array();

            foreach ($result as $row) {
                array_push($relationships, $row->current());
            }

            return $relationships;
        } else {
            return;
        }
    }

    /**
     * Get relation of a certain types between two given nodes
	 *
	 *  @param  Everyman\Neo4j\Node   start_node
     *  @param  Everyman\Neo4j\Node   end_node
	 *  @param  string                relation_type
	 * @return  array | null
     */
    public function getRelationsOfType($start_node, $end_node, $relation_type)
    {
        $startId = $start_node->getId();
        $endId = $end_node->getId();

        $queryString = 'MATCH n-[rel:'.$relation_type.']->r '
                        .'WHERE id(n)='.$startId.' and id(r)='.$endId.' '
                        .'return rel;';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result != null) {
            $relationships = array();

            foreach ($result as $row) {
                array_push($relationships, $row->current());
            }

            return $relationships;
        } else {
            return false;
        }
    }

	/**
     * Get relation of a certain types between two given nodes
	 *
	 *  @param  Everyman\Neo4j\Node   start_node
     *  @param  Everyman\Neo4j\Node   end_node
	 *  @param  string                rel_type
	 *  @return  array | null
     */
    public function checkIfRelationExists($start_node, $end_node, $rel_type)
    {
        $existing_relations = $this->getAllRelationsBetween2Nodes($start_node, $end_node); //retrieve all existing relations

        $flag = false;

        if ($existing_relations != null) {
            foreach ($existing_relations as $relation) {
                if ($relation->getType() == $rel_type) {
                    $flag = true;
                    break;
                }
            }
        }

        return $flag;
    }

	/**
     * Get all labels of a node
	 *
	 * @param  Everyman\Neo4j\Node   node
	 * @return  array | null
     */
    public function getLabelsOfNode(Node $node)
    {
        return $this->client->getLabels($node);
    }

    /**
	 *  Create node
	 *
     *  @param   array                properties
     *  @param   array                labels_text
	 *  @return  Everyman\Neo4j\Node
     */
    public function createNode($properties, $labels_text)
    {
        $node = $this->client->makeNode();   //create empty node 
        foreach ($properties as $key => $value) {  //add properties to node 
            $node->setProperty($key, $value);
        }
        $node = $node->save();                //save node

        $labels = array();                     //create array to store label objects 
        foreach ($labels_text as $text) {
            array_push($labels, $this->client->makeLabel($text));   //create label objects
        }
        $node->addLabels($labels);          //actually attach labels to node

        return $node;
    }

    /**
	 *  Update node
	 *
	 *  @param  Everyman\Neo4j\Node   node
     *  @param  array                 properties
     *  @param  array                 labels_text
     */
    public function updateNode($node, $properties, $labels_text)
    {
        foreach ($properties as $key => $value) {  //  set/add properties to node 
            $node->setProperty($key, $value);
        }

        $node = $node->save();                //save node

        //remove all labels before adding the current ones (even if only some change or none)
        $labels_old = $node->getLabels();
        $node->removeLabels($labels_old);

        $labels = array();                     //create array to store label objects 
        foreach ($labels_text as $text) {
            array_push($labels, $this->client->makeLabel($text));   //create label objects
        }

        $node->addLabels($labels);          //actually attach labels to node

        return $node;
    }


	/**
	 *  Creates relationship only if does not exist already
     *  Returns number of created relationships
	 *
	 *  @param  Everyman\Neo4j\Node   node
     *  @param  array                 properties
     *  @param  array                 labels_text
     */
    public function createRelations($start_nodes_array, $end_node, $rel_type, $rel_properties = null)
    {
        $counter = 0;

        foreach ($start_nodes_array as $start) {
            if (true != $this->checkIfRelationExists($start, $end_node, $rel_type)) {
                $relation = $start->relateTo($end_node, $rel_type);

                if ($rel_properties != null) {
                    foreach ($rel_properties as $property => $value) {
                        $relation->setProperty($property, $value);
                    }
                }
                if (null != $relation->save()) {
                    ++$counter;
                };  // increase counter for each  saved relation
            }
        }

        return $counter;
    }

	/**
	 *  @TODO: remove and use the delete function from  Everyman\Neo4j\Node
	 *
	 *  @param  string   id
     */
    public function deleteRelationById($id)
    {
        $relation = $this->client->getRelationship($id);
        $relation->delete();
    }
	
	/**
	 *  Delete relations of a given type between 2 nodes
	 *
	 *  @param  Everyman\Neo4j\Node   startNode
	 *  @param  Everyman\Neo4j\Node   endNode
	 *  @param  Everyman\Neo4j\Node   relationType
     */
	public function deleteRelations($startNode, $endNode, $relationType)
    {
		$queryString = ' MATCH start-[rel:'. $relationType .']->end ' .
		               ' WHERE id(start)='. $startNode->getId() .
					   ' AND id(end)='. $endNode->getId() .
                       ' DELETE rel';

        $query = new Query($this->client, $queryString);
        $query->getResultSet();

    }

    /**
	 *  Delete node and any IN or OUT relations using cypher query DETACH DELETE
	 *
	 *  @param  Everyman\Neo4j\Node   node
     */
    public function deleteNode($node)
    {
        $nodeId = $node->getId();

        $queryString = 'MATCH n WHERE id(n)='.$nodeId.' '
                         .' DETACH DELETE n';

        $query = new Query($this->client, $queryString);
        $query->getResultSet();
    }
    
    /**
	 *  Remove node and update hierarchy. This means that the incoming relations to the deleted node
	 *  will point to the father node 
	 *
	 *  @param  Everyman\Neo4j\Node   node
     */
    public function removeNodeWithHierarchyUpdate($node)
    {

        //get father node of the node to be deleted.
        $relationshipsOut = $node->getRelationships(array($this->default_relation_type), Relationship::DirectionOut);

        // if there is no relationship out means the node was either alone or was the root of an hierarchy
        if (count($relationshipsOut) == 0) {
            $this->deleteNode($node);

            return;
        }
        $father_node = $relationshipsOut[0]->getEndNode();

        $relationshipsIn = $node->getRelationships(array($this->default_relation_type), Relationship::DirectionIn);
        //if there is no relationship it means the node was terminal node
        if (count($relationshipsIn) == 0) {
            $this->deleteNode($node);

            return;
        }

        $children_nodes = array();

        foreach ($relationshipsIn as $rel) {
            array_push($children_nodes, $rel->getStartNode());
        }

        $this->deleteNode($node);

        $this->createRelations($children_nodes, $father_node, $this->default_relation_type);
    }

	/**
	 *  New node will take the exact position in hierarchy of the old node. The old node will not be
	 *  deleted, only it's relations will be deleted
	 *
	 *  @param  Everyman\Neo4j\Node   node
     */
    public function replaceNodeInHierarchy($old_node, $new_node)
    {
        $oldNodeOutgoingRelationships = $old_node->getRelationships(array(), Relationship::DirectionOut);
        $oldNodeIncomingRelationships = $old_node->getRelationships(array(), Relationship::DirectionIn);

        if ($oldNodeIncomingRelationships != null) {
            foreach ($oldNodeIncomingRelationships as $relation) {
                $start_node = $relation->getStartNode();
                $start_node->relateTo($new_node, $relation->getType())
                    ->save();
                $relation->delete();
            }
        }

        if ($oldNodeOutgoingRelationships != null) {
            foreach ($oldNodeOutgoingRelationships as $rel) {
                $end_node = $rel->getEndNode();
                $new_node->relateTo($end_node, $rel->getType())
                    ->save();
                $rel->delete();
            }
        }
    }

	/**
	 *  Insert a node between two nodes 
	 *
	 *  @param  Everyman\Neo4j\Node   new_node
	 *  @param  Everyman\Neo4j\Node   old_node
	 *  @param  Everyman\Neo4j\Node   old_end_node
	 *  @param  string                rel_type
     */
    public function insertNodeBetween2Nodes($new_node, $old_start_node, $old_end_node, $rel_type = null)
    {
        if ($rel_type == null) {
            $rel_type = $this->default_relation_type;
        }

        $old_relations = $this->getAllRelationsBetween2Nodes($old_start_node, $old_end_node);

        //relate old start node with the new inserted node using existing relations
        if ($old_relations != null) {
            foreach ($old_relations as $relation) {
                $old_start_node->relateTo($new_node, $relation->getType())
                    ->save();
                $relation->delete();
            }
        } else {
            $old_start_node->relateTo($new_node, $rel_type)
                    ->save();
        }

        //relate new node with old end node
        $new_node->relateTo($old_end_node, $rel_type)
                    ->save();
    }

    /**
	 *  If rel_type is missing default relation type will be used.
	 *
	 *  @param  Everyman\Neo4j\Node   new_node
	 *  @param  Everyman\Neo4j\Node   superior_node
	 *  @param  string                rel_type
     */
    public function insertTerminalNode($new_node, $superior_node, $rel_type = null)
    {
        if ($rel_type == null) {
            $rel_type = $this->default_relation_type;
        }

        $start_nodes_array = array();
        array_push($start_nodes_array, $new_node);

        $this->createRelations($start_nodes_array, $superior_node, $this->default_relation_type);
    }

	/**
	 *  Get hierarchy above node
	 *
	 *  @param  Everyman\Neo4j\Node   node
	 *  @param  string                rel_type
	 *  @return array
     */
    public function getHierarchyAboveNode($node, $rel_type)
    {
        $ceo = $this->client->getNode($this->root_node_id);

        $paths = $node->findPathsTo($ceo, $rel_type, Relationship::DirectionOut)
            ->setMaxDepth(50)
            ->getPaths();

        return $paths[0]->getNodes();
    }

	/**
	 *  Get child nodes of a node
	 *
	 *  @param  Everyman\Neo4j\Node   node
	 *  @param  string                rel_type
	 *  @return array | null
     */
    public function getDirectNodesBelow($node, $rel_type)
    {
        $incomingRelationships = $node->getRelationships(array($rel_type), Relationship::DirectionIn);

        $nodes = array();

        if ($incomingRelationships != null) {
            foreach ($incomingRelationships as $relation) {
                array_push($nodes, $relation->getStartNode());
            }

            return $nodes;
        } else {
            return;
        }
    }
	
	/**
	 *  Search users by property
	 *
	 *  @param  string                            search_value
	 *  @param  string                            property
	 *  @return Everyman\Neo4j\ResultSet  | null
     */
    public function searchUsersByProperty($search_value, $property)
    {
        $queryString = ' MATCH (n) '.
                        ' WHERE n.'.$property." =~ '(?i).*".$search_value.".*' ".
                        ' RETURN n';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result;
        } else {
            return;
        }
    }

	/**
	 *  Search groups by property
	 *
	 *  @param  string                            search_value
	 *  @param  string                            property
	 *  @return Everyman\Neo4j\ResultSet  | null
     */
    public function searchGroupByProperty($search_value, $property)
    {
        $queryString = ' MATCH (n) '.
                        ' WHERE n.'.$property." =~ '(?i).*".$search_value.".*' ".
                        ' RETURN n';

        $query = new Query($this->client, $queryString);
        $result = $query->getResultSet();

        if ($result->count() != 0) {
            return $result;
        } else {
            return;
        }
    }
	
	/**
	 *  Create new group node
	 *
	 *  @param  string  propertiesArray
     */
    public function createGroup($propertiesArray)
    {
        $groupNode = $this->client->makeNode();
		
		foreach ($propertiesArray as $key => $value) {
			$groupNode->setProperty($key, $value);
		}
		$savedNode = $groupNode->save();

		$label = $this->client->makeLabel('Group');
		$savedNode->addLabels(array($label));	
    }
	
    /**
	 *  Create new user node
	 *
	 *  @param  string    propertiesArray
     */
    public function createUser($propertiesArray)
    {
        $groupNode = $this->client->makeNode();
		
		foreach ($propertiesArray as $key => $value) {
			$groupNode->setProperty($key, $value);
		}
		$savedNode = $groupNode->save();

		$label = $this->client->makeLabel('User');
		$savedNode->addLabels(array($label));	
    }
	
	/**
	 *  Edit group property
	 *
	 *  @param  Everyman\Neo4j\Node        groupNode
	 *  @param  array                      propertiesArray
     */
    public function editGroupProperty($groupNode, $propertiesArray)
    {	
		foreach ($propertiesArray as $key => $value) {
			$groupNode->setProperty($key, $value);
		}
		$savedNode = $groupNode->save();	
    }
	
	/**
	 *  Edit Relation property
	 *
	 *  @param  Everyman\Neo4j\Relationship        relation
	 *  @param  array                              propertiesArray
     */
    public function editRelationProperty($relation, $propertiesArray)
    {	
		foreach ($propertiesArray as $key => $value) {
			$relation->setProperty($key, $value);
		}
		$savedNode = $relation->save();	
    }
}
