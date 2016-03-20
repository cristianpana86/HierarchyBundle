# HierarchyBundle
Hierarchy Management with Neo4j and Symfony

This Symfony bundle manages a company hierarchy retrieving and stroring information to a Neo4j database. 
The hierarchy is based on groups: PHP group is part of Software Developement group, which is part of IT, etc.
The users are members of these groups having differend kind of roles (manager, employee etc).

HierarchyBundle comes with a front where regular user can just browse data and an Admin area from where you can create, edit, delete info. 

I got the idea for this bundle from the Neo4j documentation: http://neo4j.com/docs/stable/examples-user-roles-in-graphs.html
HierarchyBundle is using the Neo4jPHP library https://github.com/jadell/neo4jphp

***
Installation
--------------------

Using composer:

	composer require cpana/hierarchybundle

Register the bundle in AppKernel:

	new CPANA\HierarchyBundle\CPANAHierarchyBundle(),

Add your parameters to app/config/config.yml:

	cpana_hierarchy:
		group_hierarchy_manager_neo4j:
			neo4j_user:  'user'
			neo4j_password:  'password'
			def_rel_type_group_to_group: 'PART_OF'
			def_rel_type_user_to_group: 'MEMBER_OF'
			root_group_id: '11111'
			manager_role_property: 'manager'
			default_property_group:  'name'
			default_property_user: 'name'

You need to specify which is the root group node of your hierarchy by prodiving the Neo4j Id of that node in parameter "root_group_id".

Import routes to app/config/routing.yml:

	cpana_hierarchy:
		resource: "@CPANAHierarchyBundle/Controller/"
		prefix:   /h
		type:     annotation

Install assets:

	php app/console assets:install

In your browser type your project path and add app_dev.php/h/home



