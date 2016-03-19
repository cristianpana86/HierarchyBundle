# HierarchyBundle
Hierarchy Management with Neo4j and Symfony

This Symfony bundle manages a company hierarchy retrieving and stroring information to a Neo4j database. 
The hierarchy is based on groups: PHP group is member of Software Developement group, which is member of IT, etc.
The users are members of these groups having differend kind of roles (manager, employee etc).

I got the idea for this bundle from the Neo4j documentation: http://neo4j.com/docs/stable/examples-user-roles-in-graphs.html
HierarchyBundle is using the Neo4jPHP library https://github.com/jadell/neo4jphp

***
Installation
--------------------

Using composer:

	composer require cpana/hierarchybundle

Register bundle in AppKernel:

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

Import routes to app/config/routing.yml:

	cpana_hierarchy:
		resource: "@CPANAHierarchyBundle/Controller/"
		prefix:   /h
		type:     annotation

Install assets:

	php app/console assets:install

In your browser type your project path and add app_dev.php/h/home

If you are using the example data I offered, search for 'kenny', you should see this:


